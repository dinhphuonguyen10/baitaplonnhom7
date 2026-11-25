<?php

namespace App\Controllers\Client;

use App\Core\Url;
use App\Core\View;

/**
 * Controller trang quên mật khẩu (client/quen_mat_khau.php).
 * Xác minh và đặt lại mật khẩu được thực hiện qua api/auth/dat_lai_mat_khau.php.
 */
class QuenMatKhauController extends BaseClientController
{
    protected function show(): array
    {
        $appBaseUrl = Url::base();

        return [
            'appBaseUrl' => $appBaseUrl,
            'apiUrl' => View::e($appBaseUrl . '/api/auth/dat_lai_mat_khau.php'),
            'homeUrl' => View::e($appBaseUrl . '/index.php'),
        ];
    }

    /** Action: hiển thị View client/forgot_password. */
    public function index(): void
    {
        $this->render('client/quen_mat_khau', $this->show());
    }
}
