<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Url;
use App\Models\SanPham;

/**
 * Lớp TroChuyenController (khách hàng) - trang client/tro_chuyen.php: khung chat với cửa hàng,
 * có thể kèm sản phẩm được chia sẻ (?product_id=...).
 */
class TroChuyenController extends BaseClientController
{
    /** Giờ hỗ trợ hằng ngày (giờ Việt Nam), khớp dòng "08:00 – 22:00" trên trang. */
    private const SUPPORT_OPEN_HOUR = 8;
    private const SUPPORT_CLOSE_HOUR = 22;

    /** Dữ liệu hiển thị cho trang chat của khách. */
    protected function handle(): array
    {
        $username = Auth::customerUsername();
        $isCustomerLoggedIn = $username !== null && $username !== '';

        $chatProduct = null;
        $requestedProductId = filter_var(Request::query('product_id'), FILTER_VALIDATE_INT);
        if ($isCustomerLoggedIn && $requestedProductId !== false && $requestedProductId !== null && $requestedProductId > 0) {
            $product = (new SanPham())->findActiveIdAndName((int) $requestedProductId);
            $chatProduct = $product === null ? null : ['id' => $product['id'], 'name' => $product['name']];
        }

        $hour = (int) date('G');

        return [
            'isSupportHours' => $hour >= self::SUPPORT_OPEN_HOUR && $hour < self::SUPPORT_CLOSE_HOUR,
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
        $this->render('client/tro_chuyen', $this->handle());
    }
}
