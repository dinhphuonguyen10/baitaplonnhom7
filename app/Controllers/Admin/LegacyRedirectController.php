<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Url;

/**
 * Controller chuyển hướng các đường dẫn quản trị cũ đã được gộp.
 */
class LegacyRedirectController extends Controller
{
    /** admin/payments.php: trang thanh toán đã gộp vào trang đơn hàng (301). */
    public function payments(): void
    {
        $this->redirect(Url::to('/admin/orders.php'), 301);
    }

    /**
     * client/categories.php (đường dẫn cũ): quản trị viên được chuyển về admin/categories.php,
     * chưa đăng nhập quản trị thì chuyển tới trang đăng nhập admin.
     */
    public function clientCategories(): void
    {
        if (!\App\Core\Auth::isAdmin()) {
            $this->redirect(Url::to('/admin/login.php'));
        }
        $this->redirect(Url::to('/admin/categories.php'));
    }
}
