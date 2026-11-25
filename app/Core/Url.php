<?php

namespace App\Core;

/**
 * Lớp Url - tính đường dẫn gốc của ứng dụng và tạo URL tuyệt đối theo thư mục cài đặt.
 */
final class Url
{
    /** Đường dẫn gốc (VD: /baitaplonnhom7), không có dấu / ở cuối. */
    public static function base(): string
    {
        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $appBaseUrl = preg_replace('~/(?:client|admin|api|scripts)(?:/.*)?$~', '', $scriptDirectory);

        return rtrim((string) $appBaseUrl, '/');
    }

    /** Tạo URL từ đường dẫn tương đối với thư mục gốc, VD: Url::to('/client/cart.php'). */
    public static function to(string $path = ''): string
    {
        return self::base() . '/' . ltrim($path, '/');
    }

    /** Trang chủ quản trị theo vai trò. */
    public static function adminHome(string $role): string
    {
        return self::to(Auth::isAdminAreaRole($role) ? '/admin/index.php' : '/admin/login.php');
    }

    /** Tên file của script đang chạy, VD: products.php */
    public static function currentPage(): string
    {
        return basename((string) (parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: ''));
    }
}
