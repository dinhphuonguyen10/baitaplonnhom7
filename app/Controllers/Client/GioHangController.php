<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Response;

/**
 * Controller trang giỏ hàng (client/gio_hang.php).
 * Giỏ hàng do JavaScript tải; trang chỉ yêu cầu khách hàng đã đăng nhập.
 */
class GioHangController extends BaseClientController
{
    protected function indexData(): array
    {
        if (!Auth::isCustomer()) {
            Response::redirect('../index.php?login_required=cart');
        }

        return [
            'pageTitle' => 'Giỏ hàng - ÉCRIN JEWELRY',
            'bodyClass' => 'cart-page',
        ];
    }

    /** Action: hiển thị View client/cart. */
    public function index(): void
    {
        $this->render('client/gio_hang', $this->indexData());
    }
}
