<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Session;
use App\Services\DonHangService;
use App\Services\ThanhToanService;

/**
 * Lớp TraCuuDonHangController - trang "Đơn hàng của tôi" (client/tracuudh.php).
 * Danh sách đơn được tải bằng JavaScript qua api/orders/don_hang_cua_toi.php; trang cần dữ liệu layout
 * và các CSRF token cho thao tác hủy đơn / đổi phương thức thanh toán.
 */
class TraCuuDonHangController extends BaseClientController
{
    protected function handle(): array
    {
        $username = Auth::customerUsername();
        $orderCancelCsrf = '';
        $paymentCsrf = '';
        if ($username !== null && $username !== '') {
            $orderCancelCsrf = Session::csrfToken(DonHangService::CANCEL_CSRF_KEY);
            $paymentCsrf = Session::csrfToken(ThanhToanService::CSRF_KEY);
        }

        return [
            'pageTitle' => 'Đơn hàng của tôi - ÉCRIN JEWELRY',
            'bodyClass' => 'my-orders-page',
            'orderCancelCsrf' => $orderCancelCsrf,
            'paymentCsrf' => $paymentCsrf,
        ];
    }

    /** Action: hiển thị View client/order_tracking. */
    public function index(): void
    {
        $this->render('client/tra_cuu_don_hang', $this->handle());
    }
}
