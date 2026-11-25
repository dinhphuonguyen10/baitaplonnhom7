<?php

namespace App\Services;

use App\Core\DatabaseException;
use App\Models\Admin;
use InvalidArgumentException;

/**
 * Lớp StaffAccountService - nghiệp vụ quản lý tài khoản nội bộ (nhân viên/quản lý):
 * tạo tài khoản, đổi mật khẩu, xóa tài khoản. Trả về thông báo thành công,
 * ném InvalidArgumentException kèm thông báo lỗi tiếng Việt khi thất bại.
 */
class StaffAccountService
{
    public const ROLES = ['staff', 'manager'];

    private Admin $admins;

    public function __construct(?Admin $admins = null)
    {
        $this->admins = $admins ?? new Admin();
    }

    /** Danh sách tài khoản nội bộ. */
    public function listAccounts(): array
    {
        return $this->admins->listForManagement();
    }

    /** Tạo tài khoản nhân viên/quản lý mới. */
    public function create(string $username, string $password, string $role): string
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'staff';

        if ($username === '' || strlen($username) < 3 || strlen($username) > 100) {
            throw new InvalidArgumentException('Tên đăng nhập phải từ 3 đến 100 ký tự.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Mật khẩu phải có tối thiểu 8 ký tự.');
        }
        if ($this->admins->usernameExists($username)) {
            throw new InvalidArgumentException('Tên đăng nhập đã được sử dụng.');
        }

        try {
            $this->admins->createAccount($username, password_hash($password, PASSWORD_DEFAULT), $role);
        } catch (DatabaseException $exception) {
            throw new InvalidArgumentException('Không thể tạo tài khoản lúc này.');
        }

        $roleLabel = $role === 'manager' ? 'Quản lý' : 'Nhân viên';

        return "Đã tạo tài khoản {$roleLabel} '{$username}' thành công.";
    }

    /** Đặt lại mật khẩu cho tài khoản nội bộ. */
    public function resetPassword(int $targetId, string $newPassword): string
    {
        if ($targetId <= 0) {
            throw new InvalidArgumentException('Vui lòng chọn tài khoản cần đổi mật khẩu.');
        }
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('Mật khẩu mới phải có tối thiểu 8 ký tự.');
        }

        $target = $this->admins->findBasicById($targetId);
        if (!$target) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản nhân viên.');
        }

        try {
            $this->admins->updatePasswordHash($targetId, password_hash($newPassword, PASSWORD_DEFAULT));
        } catch (DatabaseException $exception) {
            throw new InvalidArgumentException('Không thể cập nhật mật khẩu lúc này.');
        }

        return "Đã cập nhật mật khẩu mới cho tài khoản '{$target['username']}' thành công!";
    }

    /** Xóa tài khoản nội bộ (không cho xóa tài khoản đang đăng nhập). */
    public function delete(int $targetId, string $currentUsername): string
    {
        if ($targetId <= 0) {
            throw new InvalidArgumentException('Tài khoản cần xóa không hợp lệ.');
        }

        $target = $this->admins->findBasicById($targetId);
        if (!$target) {
            throw new InvalidArgumentException('Không tìm thấy tài khoản cần xóa.');
        }
        if ((string) $target['username'] === $currentUsername) {
            throw new InvalidArgumentException('Không thể xóa tài khoản đang đăng nhập.');
        }

        try {
            $this->admins->deleteAccount($targetId);
        } catch (DatabaseException $exception) {
            throw new InvalidArgumentException('Không thể xóa tài khoản lúc này.');
        }

        return "Đã xóa tài khoản '{$target['username']}' thành công.";
    }
}
