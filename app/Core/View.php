<?php

namespace App\Core;

/**
 * Lớp View - tiện ích hiển thị: escape HTML, định dạng tiền, nạp partial.
 */
final class View
{
    /** Escape chuỗi để in ra HTML an toàn. */
    public static function e($value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /** Định dạng tiền VNĐ: 1.250.000 */
    public static function money($amount): string
    {
        return number_format((float) $amount, 0, ',', '.');
    }

    /** JSON an toàn để nhúng vào thẻ <script>. */
    public static function json($value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
    }

    /** URL tài nguyên tĩnh kèm phiên bản theo thời gian sửa file (chống cache). */
    public static function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = APP_ROOT . '/' . $path;
        $version = is_file($file) ? '?v=' . filemtime($file) : '';

        return Url::to($path) . $version;
    }

    /** Đường dẫn tuyệt đối tới file View trong app/Views. */
    public static function path(string $view): string
    {
        return VIEW_PATH . '/' . ltrim($view, '/') . '.php';
    }

    /**
     * Hiển thị một View (V trong mô hình MVC): giải nén $data thành biến rồi nạp file
     * app/Views/{$view}.php. VD: View::render('client/cart', ['pageTitle' => 'Giỏ hàng']);
     */
    public static function render(string $view, array $data = []): void
    {
        $file = self::path($view);
        if (!is_file($file)) {
            throw new \RuntimeException('Không tìm thấy view: ' . $view);
        }

        // Nạp view trong phạm vi riêng; EXTR_SKIP để dữ liệu không ghi đè $__file / $__data.
        (static function (string $__file, array $__data): void {
            extract($__data, EXTR_SKIP);
            include $__file;
        })($file, $data);
    }
}
