<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Url;
use App\Models\Product;

/**
 * Lớp ChatController (khách hàng) - trang client/chat.php: khung chat với cửa hàng,
 * có thể kèm sản phẩm được chia sẻ (?product_id=...).
 */
class ChatController extends BaseClientController
{
    /** Dữ liệu hiển thị cho trang chat của khách. */
    protected function handle(): array
    {
        $username = Auth::customerUsername();
        $isCustomerLoggedIn = $username !== null && $username !== '';

        $chatProduct = null;
        $requestedProductId = filter_var(Request::query('product_id'), FILTER_VALIDATE_INT);
        if ($isCustomerLoggedIn && $requestedProductId !== false && $requestedProductId !== null && $requestedProductId > 0) {
            $product = (new Product())->find((int) $requestedProductId);
            $chatProduct = $product === null ? null : ['id' => $product['id'], 'name' => $product['name']];
        }

        return [
            'pageTitle' => 'Chat với cửa hàng - ÉCRIN JEWELRY',
            'isCustomerLoggedIn' => $isCustomerLoggedIn,
            'chatUsername' => $username ?? 'Quý khách',
            'chatProduct' => $chatProduct,
            'appBaseUrl' => Url::base(),
        ];
    }

    /** Action: hiển thị View client/chat. */
    public function index(): void
    {
        $this->render('client/chat', $this->handle());
    }
}
