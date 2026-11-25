<?php

namespace App\Core;

/**
 * Lớp Request - đọc dữ liệu từ yêu cầu HTTP hiện tại.
 */
final class Request
{
    private static ?array $jsonBody = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    /** Giá trị trong query string ($_GET). */
    public static function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /** Giá trị trong form POST ($_POST). */
    public static function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /** Giá trị từ POST, sau đó GET. */
    public static function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function string(string $key, string $default = '', string $source = 'input'): string
    {
        $value = $source === 'query' ? self::query($key) : ($source === 'post' ? self::post($key) : self::input($key));

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public static function int(string $key, int $default = 0, string $source = 'input'): int
    {
        $value = $source === 'query' ? self::query($key) : ($source === 'post' ? self::post($key) : self::input($key));
        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? $default : (int) $filtered;
    }

    /** Nội dung thô của body. */
    public static function rawBody(): string
    {
        return (string) file_get_contents('php://input');
    }

    /** Body JSON đã giải mã thành mảng (mảng rỗng nếu không hợp lệ). */
    public static function json(): array
    {
        if (self::$jsonBody === null) {
            $decoded = json_decode(self::rawBody(), true);
            self::$jsonBody = is_array($decoded) ? $decoded : [];
        }

        return self::$jsonBody;
    }

    public static function jsonValue(string $key, $default = null)
    {
        return self::json()[$key] ?? $default;
    }

    public static function file(string $key): ?array
    {
        return isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : null;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function isAjax(): bool
    {
        return strtolower((string) self::header('X-Requested-With')) === 'xmlhttprequest';
    }
}
