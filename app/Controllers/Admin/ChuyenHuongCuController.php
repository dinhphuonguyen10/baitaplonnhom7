<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Url;

/**
 * Controller chuyển hướng các đường dẫn quản trị cũ đã được gộp.
 */
class ChuyenHuongCuController extends Controller
{
    /** admin/thanh_toan.php: trang thanh toán đã gộp vào trang đơn hàng (301). */
    public function payments(): void
    {
        $this->redirect(Url::to('/admin/don_hang.php'), 301);
    }

    /**
     * client/quan_ly_danh_muc.php (đường dẫn cũ): quản trị viên được chuyển về admin/danh_muc.php,
     * chưa đăng nhập quản trị thì chuyển tới trang đăng nhập admin.
     */
    public function clientCategories(): void
    {
        if (!\App\Core\Auth::isAdmin()) {
            $this->redirect(Url::to('/admin/dang_nhap.php'));
        }
        $this->redirect(Url::to('/admin/danh_muc.php'));
    }
}
