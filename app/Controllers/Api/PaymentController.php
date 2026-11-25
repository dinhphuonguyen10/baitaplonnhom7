<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PaymentService;
use App\Services\VietQrService;
use Throwable;

/**
 * Lớp PaymentController - các API thanh toán VietQR của khách hàng (api/payment/*.php).
 * Mọi API yêu cầu đăng nhập tài khoản khách hàng và CSRF token payment_csrf.
 */
class PaymentController extends \App\Core\Controller
{
    /** POST api/payment/change_vietqr_method.php - đổi sang thanh toán khi nhận hàng. */
    public function changeMethod(): void
    {
        Response::jsonHeaders();
        $this->requireCustomer();
        $this->requirePost();

        $data = Request::json();
        $orderId = $data !== [] ? filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT) : false;
        if ($orderId === false || $orderId < 1) {
            $this->respond('error', 'Mã đơn hàng không hợp lệ.');
        }
        $this->requireCsrf($data);

        $result = (new PaymentService())->switchToCod((int) $orderId, (int) Auth::userId());
        $this->respond($result['status'], $result['message'], $result['code']);
    }

    /** POST api/payment/refresh_vietqr.php - tạo mã QR mới cho đơn. */
    public function refresh(): void
    {
        Response::jsonHeaders();
        $this->requireCustomer();

        $config = (new VietQrService())->getConfig();
        if ($config === null) {
            $this->respond('error', 'Cửa hàng chưa cấu hình tài khoản VietQR.', 503);
        }
        $this->requirePost();

        $data = Request::json();
        if (!is_array(json_decode(Request::rawBody(), true))) {
            $this->respond('error', 'Dữ liệu không hợp lệ.');
        }
        $orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$orderId || $orderId < 1) {
            $this->respond('error', 'Mã đơn hàng không hợp lệ.');
        }
        $this->requireCsrf($data);

        $userId = (int) Auth::userId();
        $service = new PaymentService();
        try {
            $service->expireIfOverdue((int) $orderId);
        } catch (Throwable $e) {
            error_log('VietQR expiration check failed: ' . $e->getMessage());
            $this->respond('error', 'Không thể kiểm tra thời hạn thanh toán lúc này.', 500);
        }

        try {
            $order = $service->findCustomerOrder((int) $orderId, $userId);
        } catch (Throwable $e) {
            error_log('VietQR refresh prepare failed: ' . $e->getMessage());
            $this->respond('error', 'Hệ thống không thể tạo mã QR lúc này.', 500);
        }

        $result = $service->refreshAttempt($order ?? [], (int) $orderId, $config);
        $response = ['status' => $result['status'], 'message' => $result['message']];
        if (isset($result['attempt'])) {
            $response['qr_token'] = $result['attempt']['token'];
            $response['transfer_reference'] = $result['attempt']['reference'];
            $response['qr_payload'] = $result['attempt']['payload'];
            $response['expires_in'] = 60;
        }
        Response::json($response, $result['code']);
    }

    /** POST api/payment/report_vietqr.php - khách báo kết quả chuyển khoản. */
    public function report(): void
    {
        Response::jsonHeaders();
        if (!Auth::isCustomer()) {
            $this->respondReport('error', 'Vui lòng đăng nhập.', null, 401);
        }
        if (!Request::isPost()) {
            $this->respondReport('error', 'Phương thức không hợp lệ.', null, 405);
        }

        $data = Request::json();
        if (!is_array(json_decode(Request::rawBody(), true))) {
            $this->respondReport('error', 'Dữ liệu không hợp lệ.');
        }

        $orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
        $result = (string) ($data['result'] ?? '');
        if (!$orderId || $orderId < 1 || !in_array($result, ['success', 'failed'], true)) {
            $this->respondReport('error', 'Thông tin xác nhận thanh toán không hợp lệ.');
        }

        if (!Session::verifyCsrf((string) ($data['csrf_token'] ?? ''), PaymentService::CSRF_KEY)) {
            $this->respondReport('error', 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', null, 403);
        }

        $outcome = (new PaymentService())->reportResult(
            (int) $orderId,
            (int) Auth::userId(),
            $result,
            (string) ($data['qr_token'] ?? '')
        );
        $this->respondReport(
            $outcome['status'],
            $outcome['message'],
            $outcome['payment_status'],
            $outcome['code'],
            $outcome['payment_deadline']
        );
    }

    private function requireCustomer(): void
    {
        if (!Auth::isCustomer()) {
            $this->respond('error', 'Vui lòng đăng nhập.', 401);
        }
    }

    private function requirePost(): void
    {
        if (!Request::isPost()) {
            $this->respond('error', 'Phương thức không hợp lệ.', 405);
        }
    }

    private function requireCsrf(array $data): void
    {
        if (!Session::verifyCsrf((string) ($data['csrf_token'] ?? ''), PaymentService::CSRF_KEY)) {
            $this->respond('error', 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', 403);
        }
    }

    private function respond(string $status, string $message, int $code = 400): void
    {
        Response::json(['status' => $status, 'message' => $message], $code);
    }

    private function respondReport(string $status, string $message, ?string $paymentStatus = null, int $code = 400, ?int $deadline = null): void
    {
        Response::json([
            'status' => $status,
            'message' => $message,
            'payment_status' => $paymentStatus,
            'payment_deadline' => $deadline,
        ], $code);
    }
}
