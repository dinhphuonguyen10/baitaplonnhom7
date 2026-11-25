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
}
