<?php

namespace App\Core;

/**
 * Lớp Auth - quản lý trạng thái đăng nhập của khách hàng và quản trị viên.
 *
 * Khóa phiên:
 *  - Khách hàng: user_id, username, role = 'customer'
 *  - Quản trị:   admin_user_id, admin_username, admin_role (admin | manager | staff)
 */
final class Auth
{
    public const ADMIN_AREA_ROLES = ['admin', 'manager', 'staff'];
    public const MANAGER_ROLES = ['admin', 'manager'];

    // ───────────── Khách hàng ─────────────

    public static function loginCustomer(int $userId, string $username): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('username', $username);
        Session::set('role', 'customer');
    }

    public static function logoutCustomer(): void
    {
        Session::remove('user_id', 'username', 'role');
    }

    public static function isCustomer(): bool
    {
        return isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
    }

    /** ID khách hàng đang đăng nhập (chỉ khi role = customer). */
    public static function customerId(): ?int
    {
        return self::isCustomer() ? (int) $_SESSION['user_id'] : null;
    }

    public static function customerUsername(): ?string
    {
        return self::isCustomer() && isset($_SESSION['username']) ? (string) $_SESSION['username'] : null;
    }

    /** ID người dùng trong phiên, không kiểm tra vai trò (tương thích code cũ). */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    // ───────────── Quản trị viên ─────────────

    public static function loginAdmin(int $adminId, string $username, string $role): void
    {
        Session::regenerate();
        Session::set('admin_user_id', $adminId);
        Session::set('admin_username', $username);
        Session::set('admin_role', $role);
    }

    public static function logoutAdmin(): void
    {
        Session::remove('admin_user_id', 'admin_username', 'admin_role', 'staff_management_csrf');
    }

    public static function adminId(): ?int
    {
        return isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null;
    }

    public static function adminUsername(): string
    {
        return (string) ($_SESSION['admin_username'] ?? $_SESSION['username'] ?? '');
    }

    public static function adminRole(): ?string
    {
        $role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? null;

        return $role === null ? null : (string) $role;
    }

    public static function isAdmin(): bool
    {
        return isset($_SESSION['admin_user_id']) && self::isAdminAreaRole($_SESSION['admin_role'] ?? null);
    }

    public static function isManager(): bool
    {
        return self::isManagerRole(self::adminRole());
    }

    public static function isStaff(): bool
    {
        return self::isStaffRole(self::adminRole());
    }

    public static function isAdminAreaRole(?string $role): bool
    {
        return in_array($role, self::ADMIN_AREA_ROLES, true);
    }

    public static function isManagerRole(?string $role): bool
    {
        return in_array($role, self::MANAGER_ROLES, true);
    }

    public static function isStaffRole(?string $role): bool
    {
        return $role === 'staff';
    }

    /** Bắt buộc đã đăng nhập khu vực quản trị, nếu không chuyển về trang đăng nhập admin. */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            Response::redirect(Url::to('/admin/login.php'));
        }
    }

    /** Bắt buộc vai trò quản lý (manager/admin). */
    public static function requireManager(): void
    {
        self::requireAdmin();
        if (!self::isManager()) {
            Response::redirect(Url::adminHome((string) self::adminRole()));
        }
    }

    /** Bắt buộc vai trò nhân viên. */
    public static function requireStaff(): void
    {
        self::requireAdmin();
        if (!self::isStaff()) {
            Response::redirect(Url::adminHome((string) self::adminRole()));
        }
    }
}
