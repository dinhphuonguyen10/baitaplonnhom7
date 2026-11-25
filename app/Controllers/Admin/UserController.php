<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Services\StaffAccountService;
use InvalidArgumentException;

/**
 * Controller trang admin/users.php - quản lý tài khoản nội bộ (bảng `admins`):
 * tạo tài khoản, đổi mật khẩu, xóa tài khoản. Chỉ dành cho quản lý.
 */
class UserController extends BaseAdminController
{
    protected bool $requireManager = true;

    private const CSRF_KEY = 'staff_management_csrf';

    private StaffAccountService $accounts;

    public function __construct(?Database $db = null, ?StaffAccountService $accounts = null)
    {
        parent::__construct($db);
        $this->accounts = $accounts ?? new StaffAccountService();
    }

    /** Xử lý POST (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $csrfToken = $this->csrfToken(self::CSRF_KEY);
        $error = '';
        $success = '';

        if (Request::isPost()) {
            $token = Request::post('csrf_token', '');
            $action = Request::post('action', 'create');

            try {
                if (!$this->verifyCsrf(is_string($token) ? $token : null, self::CSRF_KEY)) {
                    $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
                } elseif ($action === 'delete_user') {
                    $success = $this->accounts->delete($this->targetId(), Auth::adminUsername());
                } elseif ($action === 'reset_password') {
                    $newPassword = Request::post('new_password');
                    $success = $this->accounts->resetPassword($this->targetId(), is_string($newPassword) ? $newPassword : '');
                } elseif ($action === 'create') {
                    $username = Request::post('username');
                    $password = Request::post('password');
                    $role = Request::post('role');
                    $success = $this->accounts->create(
                        is_string($username) ? trim($username) : '',
                        is_string($password) ? $password : '',
                        is_string($role) ? $role : 'staff'
                    );
                }
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return array_merge($this->layoutData('users.php'), [
            'error' => $error,
            'success' => $success,
            'csrfToken' => $csrfToken,
            'staffAccounts' => $this->accounts->listAccounts(),
        ]);
    }

    private function targetId(): int
    {
        return (int) Request::post('target_user_id', 0);
    }

    /** Action: hiển thị View admin/users. */
    public function index(): void
    {
        $this->render('admin/users', $this->handle());
    }
}
