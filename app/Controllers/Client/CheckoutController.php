<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Url;
use App\Models\Address;
use App\Services\VietQrService;

/**
 * Lớp CheckoutController - dữ liệu cho trang đặt hàng (client/checkout.php):
 * cấu hình VietQR, tên khách đang đăng nhập và sổ địa chỉ đã lưu.
 */
class CheckoutController extends BaseClientController
{
    protected function handle(): array
    {
        $qrPaymentConfig = (new VietQrService())->getConfig();
        $username = Auth::customerUsername();
        $customerUsername = Auth::isCustomer() && $username !== null ? $username : null;

        $checkoutAddresses = [];
        if ($customerUsername !== null) {
            $checkoutAddresses = (new Address())->listForUser((int) Auth::userId(), true);
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
        $this->render('client/checkout', $this->handle());
    }
}
