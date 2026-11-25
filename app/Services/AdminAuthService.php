<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\DatabaseException;
use App\Models\Admin;
use InvalidArgumentException;

/**
 * Lớp AdminAuthService - xác thực đăng nhập cổng quản trị (bảng `admins`).
 * Phiên admin dùng khóa riêng và có thể tồn tại song song với phiên khách hàng.
 */
class AdminAuthService
{
    private Admin $admins;

    public function __construct(?Admin $admins = null)
    {
        $this->admins = $admins ?? new Admin();
    }

    /**
     * Kiểm tra thông tin đăng nhập, trả về tài khoản (id, username, password, role) nếu hợp lệ.
     *
     * @throws InvalidArgumentException khi thiếu dữ liệu / sai thông tin / lỗi hệ thống
     */
    public function attempt(string $username, string $password): array
    {
        if ($username === '' || $password === '') {
            throw new InvalidArgumentException('Vui lòng nhập tên đăng nhập và mật khẩu.');
        }

        try {
            $user = $this->admins->findForLogin($username);
        } catch (DatabaseException $exception) {
            throw new InvalidArgumentException('Không thể đăng nhập lúc này. Vui lòng thử lại sau.');
        }

        if (!$user || !Auth::isAdminAreaRole($user['role'] ?? null) || !password_verify($password, (string) $user['password'])) {
            throw new InvalidArgumentException('Thông tin đăng nhập không đúng hoặc tài khoản không có quyền quản trị.');
        }

        return $user;
    }

    /** Đăng nhập và lưu phiên quản trị (giữ nguyên phiên khách hàng). */
    public function login(string $username, string $password): array
    {
        $user = $this->attempt($username, $password);
        Auth::loginAdmin((int) $user['id'], (string) $user['username'], (string) $user['role']);

        return $user;
    }
}
