<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\PaymentService;
use App\Services\VietQrService;
use Throwable;

/**
 * Lớp PaymentPageController - trang thanh toán VietQR / biên nhận đặt hàng (client/payment.php).
 * Kiểm tra quyền, hạn thanh toán, chuẩn bị lượt mã QR (đơn BANK_QR) hoặc biên nhận
 * "Đặt hàng thành công" (đơn COD, đơn QR đã thanh toán / chờ đối soát) cùng các cờ trạng thái.
 */
class PaymentPageController extends BaseClientController
{
    private const CANCELLED_STATUSES = ['Đã hủy', 'Canceled', 'cancelled', 'huy'];

    protected function handle(): array
    {
        // Chỉ cho phép khách hàng đăng nhập thanh toán
        if (!Auth::isCustomer()) {
            Response::redirect('../index.php');
        }

        $userId = (int) Auth::userId();
        $orderId = filter_var(Request::query('order_id'), FILTER_VALIDATE_INT);
        if (!$orderId || $orderId < 1) {
            $this->abort(400, 'Mã đơn hàng không hợp lệ.');
        }

        $service = new PaymentService();
        try {
            $service->expireIfOverdue((int) $orderId);
        } catch (Throwable $e) {
            error_log('VietQR expiration check failed: ' . $e->getMessage());
            $this->abort(500, 'Không thể kiểm tra thời hạn thanh toán lúc này.');
        }

        // Lấy đơn hàng thuộc tài khoản hiện tại
        $order = $service->findPaymentPageOrder((int) $orderId, $userId);
        if (!$order) {
            $this->abort(404, 'Không tìm thấy đơn hàng.');
        }
        if (!in_array($order['payment_method'], ['BANK_QR', 'COD'], true)) {
            $this->abort(404, 'Đơn hàng không có phương thức thanh toán hợp lệ.');
        }

        $isCodOrder = $order['payment_method'] === 'COD';
        $paymentConfig = null;
        if (!$isCodOrder) {
            $paymentConfig = (new VietQrService())->getConfig();
            if ($paymentConfig === null) {
                $this->abort(503, 'Chưa cấu hình tài khoản VietQR. Vui lòng liên hệ cửa hàng.');
            }
        }

        if (in_array($order['status'], self::CANCELLED_STATUSES, true)) {
            $this->abort(200, 'Đơn hàng đã bị hủy, không thể thanh toán.');
        }
        if (
            !$isCodOrder
            && (int) $order['is_expired'] === 1
            && in_array($order['status'], ['Chờ xác nhận', 'Pending'], true)
        ) {
            $this->abort(200, 'Đơn hàng đã hết thời gian thanh toán. Vui lòng đặt lại đơn hàng.');
        }

        // Tạo token bảo vệ yêu cầu xác nhận
        $csrfToken = Session::csrfToken(PaymentService::CSRF_KEY);

        $isPaid = $order['payment_status'] === 'Đã thanh toán';
        $isAwaitingConfirmation = $order['payment_status'] === 'Chờ xác nhận thanh toán';
        $isCanceled = in_array($order['status'], self::CANCELLED_STATUSES, true);
        $isFailed = $order['payment_status'] === 'Thanh toán thất bại';
        $isRetryRequest = Request::query('retry') === '1';
        $showFailedNotice = $isFailed && !$isRetryRequest;
        $isPaymentDeadlineExpired = $isFailed
            && $order['payment_deadline'] !== null
            && (int) $order['payment_deadline'] <= time();
        $isOrderSuccess = !$isCanceled && ($isPaid || $isAwaitingConfirmation || $isCodOrder);
        $showPaymentQr = !$isCodOrder && !$isPaid && !$isAwaitingConfirmation && !$isCanceled && !$isPaymentDeadlineExpired;

        $orderItems = [];
        if ($isOrderSuccess) {
            try {
                $orderItems = $service->receiptItems((int) $orderId);
            } catch (Throwable $e) {
                error_log('VietQR success receipt load failed: ' . $e->getMessage());
                $this->abort(500, 'Không thể tải thông tin đơn hàng lúc này.');
            }
        }

        $qrAttempt = $showPaymentQr ? $service->currentOrNewAttempt($order, $paymentConfig) : null;

        return [
            'paymentConfig' => $paymentConfig,
            'order' => $order,
            'csrfToken' => $csrfToken,
            'appBaseUrl' => Url::base(),
            'isCodOrder' => $isCodOrder,
            'isPaid' => $isPaid,
            'isAwaitingConfirmation' => $isAwaitingConfirmation,
            'isCanceled' => $isCanceled,
            'isFailed' => $isFailed,
            'isRetryRequest' => $isRetryRequest,
            'showFailedNotice' => $showFailedNotice,
            'isPaymentDeadlineExpired' => $isPaymentDeadlineExpired,
            'isOrderSuccess' => $isOrderSuccess,
            'showPaymentQr' => $showPaymentQr,
            'orderItems' => $orderItems,
            'qrAttempt' => $qrAttempt,
            'amount' => number_format((int) round((float) $order['total_price']), 0, ',', '.'),
        ];
    }

    /** Kết thúc request với mã HTTP và thông báo dạng văn bản. */
    private function abort(int $status, string $message): void
    {
        http_response_code($status);
        exit($message);
    }

    /** Action: hiển thị View client/payment. */
    public function index(): void
    {
        $this->render('client/payment', $this->handle());
    }
}
