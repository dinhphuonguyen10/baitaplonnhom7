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
 * Lớp PaymentPageController - trang thanh toán VietQR (client/payment.php).
 * Kiểm tra quyền, hạn thanh toán, chuẩn bị lượt mã QR và các cờ trạng thái cho giao diện.
 */
class PaymentPageController extends BaseClientController
{
    protected function handle(): array
    {
        // Chỉ cho phép khách hàng đăng nhập thanh toán
        if (!Auth::isCustomer()) {
            Response::redirect('../index.php');
        }

        $paymentConfig = (new VietQrService())->getConfig();
        if ($paymentConfig === null) {
            $this->abort(503, 'Chưa cấu hình tài khoản VietQR. Vui lòng liên hệ cửa hàng.');
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
        $order = $service->findCustomerOrder((int) $orderId, $userId);
        if (!$order || $order['payment_method'] !== 'BANK_QR') {
            $this->abort(404, 'Không tìm thấy đơn hàng thanh toán QR.');
        }

        // Tạo token bảo vệ yêu cầu xác nhận
        $csrfToken = Session::csrfToken(PaymentService::CSRF_KEY);

        $isPaid = $order['payment_status'] === 'Đã thanh toán';
        $isAwaitingConfirmation = $order['payment_status'] === 'Chờ xác nhận thanh toán';
        $isCanceled = in_array($order['status'], ['Đã hủy', 'Canceled', 'cancelled', 'huy'], true);
        $isFailed = $order['payment_status'] === 'Thanh toán thất bại';
        $isPaymentDeadlineExpired = $isFailed
            && $order['payment_deadline'] !== null
            && (int) $order['payment_deadline'] <= time();
        $showPaymentQr = !$isPaid && !$isAwaitingConfirmation && !$isCanceled && !$isPaymentDeadlineExpired;

        $qrAttempt = $showPaymentQr ? $service->currentOrNewAttempt($order, $paymentConfig) : null;

        return [
            'paymentConfig' => $paymentConfig,
            'order' => $order,
            'csrfToken' => $csrfToken,
            'appBaseUrl' => Url::base(),
            'isPaid' => $isPaid,
            'isAwaitingConfirmation' => $isAwaitingConfirmation,
            'isCanceled' => $isCanceled,
            'isFailed' => $isFailed,
            'isPaymentDeadlineExpired' => $isPaymentDeadlineExpired,
            'showPaymentQr' => $showPaymentQr,
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
