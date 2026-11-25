<?php

namespace App\Controllers\Client;

use App\Core\Session;
use App\Core\Url;
use App\Models\DiaChi;
use App\Models\KhachHang;
use App\Services\VietQrService;

/**
 * Lớp DatHangController - dữ liệu cho trang đặt hàng (client/dat_hang.php):
 * cấu hình VietQR, tên khách đang đăng nhập (đã xác thực lại với CSDL) và sổ địa chỉ đã lưu.
 */
class DatHangController extends BaseClientController
{
    protected function handle(): array
    {
        $qrPaymentConfig = (new VietQrService())->getConfig();
        $customerUsername = null;
        $checkoutAddresses = [];

        if (Session::get('role', '') === 'customer') {
            // Xác thực lại tài khoản trong phiên; phiên trỏ tới tài khoản không còn tồn tại thì đăng xuất khách.
            $checkoutUserId = filter_var(Session::get('user_id'), FILTER_VALIDATE_INT);
            $checkoutUser = $checkoutUserId !== false && $checkoutUserId > 0
                ? (new KhachHang())->find((int) $checkoutUserId)
                : null;

            if ($checkoutUser) {
                $checkoutUserId = (int) $checkoutUser['id'];
                Session::set('user_id', $checkoutUserId);
                Session::set('username', (string) $checkoutUser['username']);
                $customerUsername = (string) $checkoutUser['username'];
                $checkoutAddresses = (new DiaChi())->listForUser($checkoutUserId, true);
            } else {
                Session::remove('user_id', 'username', 'role');
            }
        }

        return [
            'qrPaymentConfig' => $qrPaymentConfig,
            'customerUsername' => $customerUsername,
            'checkoutAddresses' => $checkoutAddresses,
            'pageTitle' => 'Đặt hàng - ÉCRIN JEWELRY',
            'appBaseUrl' => Url::base(),
        ];
    }

    /** Action: hiển thị View client/checkout. */
    public function index(): void
    {
        $this->render('client/dat_hang', $this->handle());
    }
}
