<?php

namespace App\Core;

/**
 * Lớp Session - bao bọc $_SESSION.
 * Phiên đăng nhập admin và khách hàng được lưu ở các khóa riêng, tồn tại tối đa 7 ngày.
 */
final class Session
{
    public const LIFETIME = 7 * 24 * 60 * 60;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        ini_set('session.gc_maxlifetime', (string) self::LIFETIME);
        session_set_cookie_params([
            'lifetime' => self::LIFETIME,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /** Đóng ghi phiên sớm để không khóa các request song song (VD: polling chat). */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * Lấy (hoặc tạo mới) CSRF token lưu trong phiên theo tên.
     */
    public static function csrfToken(string $name = 'csrf_token'): string
    {
        if (empty($_SESSION[$name]) || !is_string($_SESSION[$name])) {
            $_SESSION[$name] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$name];
    }

    public static function verifyCsrf(?string $token, string $name = 'csrf_token'): bool
    {
        $expected = $_SESSION[$name] ?? '';

        return is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /** Lưu thông báo hiển thị một lần (flash message). */
    public static function flash(string $key, $value = null)
    {
        $flashKey = '_flash_' . $key;
        if (func_num_args() > 1) {
            $_SESSION[$flashKey] = $value;

            return null;
        }

        $stored = $_SESSION[$flashKey] ?? null;
        unset($_SESSION[$flashKey]);

        return $stored;
    }
}
