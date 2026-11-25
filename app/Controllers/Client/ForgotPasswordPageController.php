<?php

namespace App\Controllers\Client;

use App\Core\Url;
use App\Core\View;

/**
 * Controller trang quên mật khẩu (client/quen_mat_khau.php).
 * Xác minh và đặt lại mật khẩu được thực hiện qua api/auth/reset_password.php.
 */
class ForgotPasswordPageController extends BaseClientController
{
    protected function show(): array
    {
        $appBaseUrl = Url::base();

        return [
            'appBaseUrl' => $appBaseUrl,
            'apiUrl' => View::e($appBaseUrl . '/api/auth/reset_password.php'),
            'homeUrl' => View::e($appBaseUrl . '/index.php'),
        ];
    }

    /** Action: hiển thị View client/forgot_password. */
    public function index(): void
    {
        $this->render('client/forgot_password', $this->show());
    }
}
