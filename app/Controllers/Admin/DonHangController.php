<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Helpers\DinhDangDonHangQuanTri;
use App\Services\QuanLyDonHangService;
use App\Services\HetHanDonHangService;
use RuntimeException;
use Throwable;

/**
 * Lớp DonHangController - trang quản trị "Đơn hàng & Thanh toán" (admin/don_hang.php).
 * Xử lý POST cập nhật trạng thái / xác nhận thanh toán rồi trả dữ liệu cho view.
 */
class DonHangController extends BaseAdminController
{
    private QuanLyDonHangService $service;

    private HetHanDonHangService $expiry;

    public function __construct(?Database $db = null, ?QuanLyDonHangService $service = null, ?HetHanDonHangService $expiry = null)
    {
        parent::__construct($db);
        $this->service = $service ?? new QuanLyDonHangService($this->db);
        $this->expiry = $expiry ?? new HetHanDonHangService($this->db);
    }

    /** Xử lý yêu cầu và trả về mảng biến cho view. */
    protected function handle(): array
    {
        $orderNotice = '';
        $paymentNotice = null;

        try {
            $this->expiry->expireOverdueOrders();
        } catch (Throwable $e) {
            error_log('Admin VietQR expiration sweep failed: ' . $e->getMessage());
            $orderNotice = 'Không thể tự động cập nhật các đơn VietQR hết hạn lúc này.';
        }

        // CSRF: token riêng cho form trạng thái và token chung cho xác nhận thanh toán.
        $ordersCsrf = $this->csrfToken('orders_csrf');
        $csrfToken = $this->fixedLengthCsrfToken('admin_csrf_token');

        $action = Request::isPost() ? (Request::post('action') ?? '') : null;

        if ($action === 'update_status') {
            $token = Request::post('csrf_token', '');
            if (!is_string($token) || !hash_equals($ordersCsrf, $token)) {
                $orderNotice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
            } else {
                $orderNotice = $this->service->updateStatus(
                    (int) Request::post('order_id', 0),
                    trim((string) Request::post('status', '')),
                    Auth::adminUsername()
                );
            }
        }

        if (in_array($action, ['confirm_cod', 'confirm_bank_qr'], true)) {
            $paymentMethod = $action === 'confirm_bank_qr' ? 'BANK_QR' : 'COD';
            $submittedToken = Request::post('csrf_token', '');
            if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
                $paymentNotice = ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.'];
            } else {
                $paymentNotice = $this->service->confirmPayment(
                    filter_var(Request::post('order_id'), FILTER_VALIDATE_INT),
                    $paymentMethod
                );
            }
        }

        $orders = [];
        $ordersError = '';
        try {
            $orders = $this->service->listOrders();
        } catch (RuntimeException $e) {
            $ordersError = $e->getMessage();
        }

        return array_merge($this->layoutData('don_hang.php'), [
            'orderStatuses' => DinhDangDonHangQuanTri::STATUSES,
            'orderNotice' => $orderNotice,
            'paymentNotice' => $paymentNotice,
            'ordersCsrf' => $ordersCsrf,
            'csrfToken' => $csrfToken,
            'orders' => $orders,
            'ordersError' => $ordersError,
            'totalOrders' => count($orders),
            'statCounts' => $this->service->statistics($orders),
        ]);
    }

    /** Token CSRF 64 ký tự hex lưu trong phiên; tạo lại nếu thiếu hoặc sai định dạng. */
    private function fixedLengthCsrfToken(string $name): string
    {
        $token = Session::get($name);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set($name, $token);
        }

        return $token;
    }

    /** Action: hiển thị View admin/orders. */
    public function index(): void
    {
        $this->render('admin/don_hang', $this->handle());
    }
}
