<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Response;

/**
 * Controller trang giỏ hàng (client/cart.php).
 * Giỏ hàng do JavaScript tải; trang chỉ yêu cầu khách hàng đã đăng nhập.
 */
class CartPageController extends BaseClientController
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
        $this->render('client/cart', $this->indexData());
    }
}
