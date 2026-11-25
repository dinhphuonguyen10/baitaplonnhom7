<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\AuthException;
use App\Services\AuthService;

/**
 * Controller API xác thực khách hàng (api/auth/*.php):
 * check_session, login, logout, register, reset_password.
 */
class AuthController extends \App\Core\Controller
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /** GET api/auth/check_session.php - chỉ kiểm tra phiên khách hàng, không trả trạng thái quản trị. */
    public function checkSession(): void
    {
        // Phiên khách hàng phải còn khớp với tài khoản trong DB; tài khoản đã bị xóa thì đăng xuất.
        if (Session::get('role', '') === 'customer') {
            try {
                $user = $this->auth->refreshCustomerSession();
            } catch (\App\Core\DatabaseException $e) {
                Response::json(['status' => 'error', 'message' => 'Không thể kiểm tra phiên đăng nhập.'], 500, 0);
            }
            if ($user === null) {
                Response::json(['status' => 'error', 'message' => 'Not logged in'], 200, 0);
            }
            Response::json([
                'status' => 'success',
                'username' => Session::get('username'),
                'role' => Session::get('role'),
                'user_id' => Session::get('user_id'),
            ], 200, 0);
        }

        Response::json(['status' => 'error', 'message' => 'Not logged in'], 200, 0);
    }

    /** POST api/auth/login.php - body JSON {username, password}. */
    public function login(): void
    {
        $data = Request::json();
        if (!isset($data['username'], $data['password'])) {
            Response::error('Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!', 400);
        }

        $username = trim($this->scalar($data['username']));
        $password = $this->scalar($data['password']);

        try {
            $result = $this->auth->login($username, $password);
        } catch (AuthException $e) {
            Response::error($e->getMessage(), $e->httpStatus());
        }

        Response::json([
            'status' => 'success',
            'message' => 'Đăng nhập thành công!',
            'role' => $result['role'],
            'username' => $result['username'],
        ]);
    }

    /** api/auth/logout.php - đăng xuất phiên khách hàng, không ảnh hưởng phiên quản trị. */
    public function logout(): void
    {
        $this->auth->logoutCustomer();
        Response::json(['status' => 'success', 'message' => 'Đã đăng xuất thành công!'], 200, 0);
    }

    /** POST api/auth/register.php - body JSON {username, password, phone}. */
    public function register(): void
    {
        if (Request::method() !== 'POST') {
            Response::error('Phương thức yêu cầu không hợp lệ.', 405);
        }

        $data = json_decode(Request::rawBody(), true);
        if (!is_array($data)) {
            Response::error('Dữ liệu đăng ký không hợp lệ.', 400);
        }

        $username = isset($data['username']) && is_string($data['username']) ? trim($data['username']) : '';
        $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
        $phone = isset($data['phone']) && is_string($data['phone']) ? trim($data['phone']) : '';

        try {
            $this->auth->registerCustomer($username, $password, $phone);
        } catch (AuthException $e) {
            Response::error($e->getMessage(), $e->httpStatus());
        }

        Response::success(['message' => 'Đăng ký tài khoản thành công. Vui lòng đăng nhập.'], 201);
    }

    /** POST api/auth/reset_password.php - action verify | reset. */
    public function resetPassword(): void
    {
        if (Request::method() !== 'POST') {
            Response::error('Phương thức không được hỗ trợ.', 405);
        }

        $data = json_decode(Request::rawBody(), true);
        if (!is_array($data)) {
            Response::error('Dữ liệu không hợp lệ.', 400);
        }

        $action = $data['action'] ?? '';
        $username = trim($this->scalar($data['username'] ?? ''));

        try {
            $phone = $this->auth->normalizeResetPhone(trim($this->scalar($data['phone'] ?? '')));

            if ($action === 'verify') {
                $this->auth->verifyResetAccount($username, $phone);
                Response::success(['message' => 'Xác minh thành công.']);
            }

            if ($action === 'reset') {
                $this->auth->resetPassword(
                    $username,
                    $phone,
                    $this->scalar($data['password'] ?? ''),
                    $this->scalar($data['confirm_password'] ?? '')
                );
                Response::success(['message' => 'Đặt lại mật khẩu thành công!']);
            }
        } catch (AuthException $e) {
            Response::error($e->getMessage(), $e->httpStatus());
        }

        Response::error('Thao tác không hợp lệ.', 400);
    }

    /** Chuyển giá trị JSON vô hướng thành chuỗi (mảng/đối tượng => chuỗi rỗng). */
    private function scalar($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
