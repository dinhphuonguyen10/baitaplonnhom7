<?php

namespace App\Core;

/**
 * Lớp Response - gửi phản hồi JSON / chuyển hướng.
 */
final class Response
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE;

    /** Gửi header JSON (không kết thúc request). */
    public static function jsonHeaders(bool $noCache = false): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            if ($noCache) {
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            }
        }
    }

    /**
     * Trả JSON và kết thúc request.
     *
     * @return never
     */
    public static function json($data, int $status = 200, int $flags = self::JSON_FLAGS): void
    {
        http_response_code($status);
        self::jsonHeaders();
        echo json_encode($data, $flags);
        exit;
    }

    /** Trả JSON {status: success, ...} */
    public static function success(array $data = [], int $status = 200): void
    {
        self::json(array_merge(['status' => 'success'], $data), $status);
    }

    /** Trả JSON {status: error, message: ...} */
    public static function error(string $message, int $status = 400, array $extra = []): void
    {
        self::json(array_merge(['status' => 'error', 'message' => $message], $extra), $status);
    }

    /** Chuyển hướng và kết thúc request. */
    public static function redirect(string $url, int $status = 302): void
    {
        header('Location: ' . $url, true, $status);
        exit;
    }
}
