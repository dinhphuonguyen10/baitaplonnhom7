<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\DonHang;
use RuntimeException;
use Throwable;

/**
 * Lớp ThanhToanService - nghiệp vụ thanh toán VietQR phía khách hàng:
 *  - Lưu/đọc lượt mã QR đang hiệu lực trong phiên (khóa payment_qr_attempts[order_id])
 *  - Làm mới mã QR, ghi nhận kết quả khách báo, đổi sang thanh toán khi nhận hàng.
 *
 * Các phương thức nghiệp vụ trả về mảng kết quả gồm status/message/code (+ dữ liệu phụ)
 * để controller trả JSON đúng như trước.
 */
class ThanhToanService
{
    public const CSRF_KEY = 'payment_csrf';
    public const ATTEMPTS_KEY = 'payment_qr_attempts';

    private Database $db;
    private DonHang $orders;
    private VietQrService $vietQr;
    private HetHanDonHangService $expiry;

    public function __construct(?Database $db = null, ?VietQrService $vietQr = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->orders = new DonHang($this->db);
        $this->vietQr = $vietQr ?? new VietQrService();
        $this->expiry = new HetHanDonHangService($this->db);
    }

    // ─────────────────────────── Lượt QR trong phiên ───────────────────────────

    /** Lượt QR đã lưu trong phiên cho đơn hàng (hoặc null). */
    public static function storedAttempt(int $orderId)
    {
        $attempts = Session::get(self::ATTEMPTS_KEY, []);

        return is_array($attempts) ? ($attempts[$orderId] ?? null) : null;
    }

    public static function storeAttempt(int $orderId, array $attempt): void
    {
        $attempts = Session::get(self::ATTEMPTS_KEY, []);
        $attempts = is_array($attempts) ? $attempts : [];
        $attempts[$orderId] = $attempt;
        Session::set(self::ATTEMPTS_KEY, $attempts);
    }

    public static function forgetAttempt(int $orderId): void
    {
        $attempts = Session::get(self::ATTEMPTS_KEY);
        if (is_array($attempts)) {
            unset($attempts[$orderId]);
            Session::set(self::ATTEMPTS_KEY, $attempts);
        }
    }

    /** Lấy lượt QR còn hiệu lực trong phiên, hoặc tạo lượt mới và lưu lại. */
    public function currentOrNewAttempt(array $order, array $config): array
    {
        $orderId = (int) $order['id'];
        $attempt = self::storedAttempt($orderId);
        if (
            !is_array($attempt) ||
            empty($attempt['token']) ||
            empty($attempt['payload']) ||
            empty($attempt['reference']) ||
            (int) ($attempt['expires_at'] ?? 0) <= time()
        ) {
            $attempt = $this->vietQr->createAttempt($orderId, (float) $order['total_price'], $config);
            self::storeAttempt($orderId, $attempt);
        }

        return $attempt;
    }

    // ─────────────────────────── Đơn hàng QR ───────────────────────────

    /** Kiểm tra cấu trúc và tự hủy đơn nếu đã quá hạn thanh toán. */
    public function expireIfOverdue(int $orderId): void
    {
        $this->expiry->ensureSchema();
        $this->expiry->expireOrder($orderId);
    }

    /** Đơn QR của khách (cho trang thanh toán / làm mới QR). */
    public function findCustomerOrder(int $orderId, int $userId): ?array
    {
        return $this->orders->findQrPaymentForCustomer($orderId, $userId);
    }

    /** Đơn của khách (QR hoặc COD) cho trang thanh toán / biên nhận đặt hàng thành công. */
    public function findPaymentPageOrder(int $orderId, int $userId): ?array
    {
        return $this->orders->findForPaymentPage($orderId, $userId);
    }

    /** Các dòng sản phẩm cho biên nhận, kèm thành tiền từng dòng (item_total = đơn giá × số lượng). */
    public function receiptItems(int $orderId): array
    {
        $items = (new \App\Models\ChiTietDonHang($this->db))->listReceiptLines($orderId);
        foreach ($items as &$item) {
            $item['item_total'] = (float) $item['price'] * (int) $item['quantity'];
        }
        unset($item);

        return $items;
    }

    /**
     * Làm mới mã QR cho đơn (đã kiểm tra hạn ở bước trước).
     *
     * @return array{status:string, message:string, code:int, attempt:?array}
     */
    public function refreshAttempt(array $order, int $orderId, array $config): array
    {
        if (!$order || $order['payment_method'] !== 'BANK_QR') {
            return $this->result('error', 'Không tìm thấy đơn hàng thanh toán QR.', 404);
        }
        if (in_array($order['status'], ['Đã hủy', 'Canceled'], true)) {
            return $this->result('error', 'Đơn hàng đã bị hủy.', 409);
        }
        if (in_array($order['payment_status'], ['Đã thanh toán', 'Chờ xác nhận thanh toán'], true)) {
            return $this->result('error', 'Đơn hàng đang chờ hoặc đã được cửa hàng xác nhận.', 409);
        }
        if (
            $order['payment_status'] === 'Thanh toán thất bại' &&
            $order['payment_deadline'] !== null &&
            (int) $order['payment_deadline'] <= time()
        ) {
            return $this->result('error', 'Đã hết thời hạn thanh toán.', 409);
        }

        $attempt = $this->vietQr->createAttempt($orderId, (float) $order['total_price'], $config);
        self::storeAttempt($orderId, $attempt);

        return $this->result('success', 'Đã tạo mã QR mới.', 200, ['attempt' => $attempt]);
    }

    /**
     * Đổi đơn VietQR (đang thất bại, còn hạn) sang thanh toán khi nhận hàng.
     *
     * @return array{status:string, message:string, code:int}
     */
    public function switchToCod(int $orderId, int $userId): array
    {
        $transactionStarted = false;
        try {
            $this->expireIfOverdue($orderId);

            $this->db->beginTransaction();
            $transactionStarted = true;
            $order = $this->orders->lockQrOrderForMethodChange($orderId, $userId);

            if (
                !$order ||
                $order['payment_method'] !== 'BANK_QR' ||
                $order['payment_status'] !== 'Thanh toán thất bại' ||
                !in_array($order['status'], ['Chờ xác nhận', 'Pending'], true) ||
                (int) $order['deadline_passed'] === 1
            ) {
                $this->db->rollback();

                return $this->result('error', 'Không thể đổi phương thức: đơn không còn trong thời hạn thanh toán.', 409);
            }

            if ($this->orders->switchQrOrderToCod($orderId, $userId) !== 1) {
                throw new RuntimeException('Đơn hàng đã thay đổi, vui lòng tải lại trang.');
            }

            $this->db->commit();

            return $this->result('success', 'Đã đổi đơn hàng sang thanh toán khi nhận hàng.', 200);
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('VietQR payment method change failed: ' . $e->getMessage());

            return $this->result('error', 'Không thể đổi phương thức thanh toán lúc này.', 500);
        }
    }

    /**
     * Ghi nhận kết quả thanh toán khách báo ('success' | 'failed').
     *
     * @return array{status:string, message:string, code:int, payment_status:?string, payment_deadline:?int}
     */
    public function reportResult(int $orderId, int $userId, string $result, string $qrToken): array
    {
        $transactionStarted = false;

        try {
            $this->expireIfOverdue($orderId);

            $this->db->beginTransaction();
            $transactionStarted = true;
            $order = $this->orders->lockQrOrderForPaymentReport($orderId, $userId);

            if (!$order || $order['payment_method'] !== 'BANK_QR') {
                $this->db->rollback();

                return $this->report('error', 'Không tìm thấy đơn hàng thanh toán QR.', null, 404);
            }
            if (in_array($order['status'], ['Đã hủy', 'Canceled', 'cancelled', 'huy'], true)) {
                $this->db->rollback();

                return $this->report('error', 'Đơn hàng đã hết thời hạn hoặc đã bị hủy.', null, 409);
            }
            if ($order['payment_status'] === 'Đã thanh toán') {
                $this->db->commit();
                if ($result === 'success') {
                    return $this->report('success', 'Cửa hàng đã xác nhận khoản thanh toán này.', 'Đã thanh toán');
                }

                return $this->report('error', 'Đơn hàng đã được xác nhận thanh toán.', 'Đã thanh toán', 409);
            }
            if ($result === 'success' && $order['payment_status'] === 'Chờ xác nhận thanh toán') {
                $this->db->commit();

                return $this->report(
                    'success',
                    'Yêu cầu thanh toán của bạn đang chờ cửa hàng xác nhận.',
                    'Chờ xác nhận thanh toán'
                );
            }

            $reopeningFailedReport = $result === 'failed' && $order['payment_status'] === 'Chờ xác nhận thanh toán';
            if ($result === 'failed' && !in_array($order['status'], ['Chờ xác nhận', 'Pending'], true)) {
                $this->db->rollback();

                return $this->report('error', 'Đơn hàng đã được xử lý nên không thể mở lại thời hạn thanh toán.', null, 409);
            }

            if (
                $result === 'success' &&
                $order['payment_status'] === 'Thanh toán thất bại' &&
                $order['payment_deadline'] !== null &&
                (int) $order['payment_deadline'] <= time()
            ) {
                $this->db->rollback();
                $transactionStarted = false;
                $this->expiry->expireOrder($orderId);

                return $this->report('error', 'Đã hết thời hạn thanh toán.', null, 409);
            }

            if (!$reopeningFailedReport) {
                $activeAttempt = self::storedAttempt($orderId);
                if (
                    !is_array($activeAttempt) ||
                    empty($activeAttempt['token']) ||
                    (int) ($activeAttempt['expires_at'] ?? 0) <= time() ||
                    !hash_equals((string) $activeAttempt['token'], $qrToken)
                ) {
                    $this->db->rollback();

                    return $this->report('error', 'Mã QR đã hết hạn. Hãy dùng mã mới rồi gửi lại xác nhận.', null, 410);
                }
            }

            $newPaymentStatus = $result === 'success' ? 'Chờ xác nhận thanh toán' : 'Thanh toán thất bại';
            $affected = $newPaymentStatus === 'Thanh toán thất bại'
                ? $this->orders->markQrPaymentFailed($orderId, $userId, $newPaymentStatus)
                : $this->orders->markQrPaymentAwaitingConfirmation($orderId, $userId, $newPaymentStatus);

            if ($affected !== 1 && $order['payment_status'] !== $newPaymentStatus) {
                throw new RuntimeException('Không thể cập nhật trạng thái thanh toán.');
            }

            $paymentDeadline = $this->orders->paymentDeadlineTimestamp($orderId);

            $this->db->commit();
            $transactionStarted = false;

            if ($result === 'success') {
                self::forgetAttempt($orderId);

                return $this->report(
                    'success',
                    'Đã gửi yêu cầu. Đơn hàng đang chờ cửa hàng xác nhận thanh toán.',
                    $newPaymentStatus
                );
            }

            return $this->report(
                'success',
                $reopeningFailedReport
                    ? 'Đã mở lại thanh toán. Bạn có 30 phút để hoàn tất hoặc đổi sang thanh toán khi nhận hàng.'
                    : 'Đã ghi nhận thanh toán chưa thành công. Bạn có 30 phút để thanh toán hoặc đổi phương thức.',
                $newPaymentStatus,
                200,
                $paymentDeadline === null ? null : (int) $paymentDeadline
            );
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            error_log('VietQR payment report failed: ' . $e->getMessage());

            return $this->report('error', 'Không thể cập nhật trạng thái thanh toán lúc này.', null, 500);
        }
    }

    private function result(string $status, string $message, int $code, array $extra = []): array
    {
        return array_merge(['status' => $status, 'message' => $message, 'code' => $code], $extra);
    }

    private function report(string $status, string $message, ?string $paymentStatus, int $code = 200, ?int $deadline = null): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'payment_status' => $paymentStatus,
            'payment_deadline' => $deadline,
            'code' => $code,
        ];
    }
}
