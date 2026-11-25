<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\AdminAuthService;
use InvalidArgumentException;

/**
 * Controller đăng nhập / đăng xuất cổng quản trị (admin/login.php, admin/logout.php).
 * Không kế thừa BaseAdminController vì trang đăng nhập không yêu cầu đã đăng nhập.
 */
class LoginController extends \App\Core\Controller
{
    private const CSRF_KEY = 'admin_login_csrf';

    private ?AdminAuthService $auth;

    public function __construct(?AdminAuthService $auth = null)
    {
        $this->auth = $auth;
    }

    /** Xử lý form đăng nhập và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        // Đã đăng nhập quản trị từ trước thì chuyển hướng ngay về trang tổng quan
        if (Auth::isAdmin()) {
            Response::redirect(Url::adminHome((string) Auth::adminRole()));
        }

        $csrfToken = Session::csrfToken(self::CSRF_KEY);
        $error = '';

        if (Request::isPost()) {
            $token = Request::post('csrf_token', '');
            $username = Request::string('username', '', 'post');
            $password = Request::post('password');
            $password = is_string($password) ? $password : '';

            if (!Session::verifyCsrf(is_string($token) ? $token : null, self::CSRF_KEY)) {
                $error = 'Phiên đăng nhập không hợp lệ. Vui lòng tải lại trang và thử lại.';
            } else {
                try {
                    $user = $this->authService()->login($username, $password);
                    Session::remove(self::CSRF_KEY);
                    Response::redirect(Url::adminHome((string) $user['role']));
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                }
            }
        }

        return [
            'error' => $error,
            'csrfToken' => $csrfToken,
        ];
    }

    /** Đăng xuất quản trị, giữ nguyên phiên khách hàng. */
    public function logout(): void
    {
        Auth::logoutAdmin();
        Response::redirect(Url::to('/admin/login.php'));
    }

    /** Khởi tạo dịch vụ khi cần (tránh kết nối CSDL khi chỉ hiển thị form). */
    private function authService(): AdminAuthService
    {
        if ($this->auth === null) {
            $this->auth = new AdminAuthService();
        }

        return $this->auth;
    }

    /** Action: hiển thị View admin/login. */
    public function index(): void
    {
        $this->render('admin/login', $this->handle());
    }
}
