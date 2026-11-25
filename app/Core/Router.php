<?php

namespace App\Core;

/**
 * Lớp Router - định tuyến request tới Controller@action theo bảng app/routes.php.
 *
 * Mỗi file .php công khai (index.php, client/*.php, admin/*.php, api/**.php) chỉ gồm:
 *   require_once __DIR__ . '/<...>/app/bootstrap.php';
 *   App\Core\Router::dispatch();
 * nên URL cũ được giữ nguyên, còn toàn bộ xử lý đi qua Router -> Controller -> View.
 */
final class Router
{
    /** @var array<string, array> */
    private array $routes;

    public function __construct(?array $routes = null)
    {
        $this->routes = $routes ?? require APP_ROOT . '/app/routes.php';
    }

    /** Định tuyến request hiện tại. */
    public static function dispatch(): void
    {
        (new self())->handle(self::currentPath());
    }

    /**
     * Đường dẫn của script đang chạy, tính từ thư mục gốc ứng dụng. VD: /client/cart.php
     */
    public static function currentPath(): string
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = Url::base();
        $path = $base !== '' && strpos($script, $base) === 0 ? substr($script, strlen($base)) : $script;

        // Windows/Apache vẫn chạy file khi URL có dấu chấm thừa ở cuối (ho_so.php.) hoặc khác hoa/thường,
        // nên chuẩn hóa để khớp bảng route (toàn chữ thường).
        return '/' . strtolower(rtrim(ltrim($path, '/'), '.'));
    }

    /** Gọi action của controller tương ứng với $path. */
    public function handle(string $path): void
    {
        if (!isset($this->routes[$path])) {
            http_response_code(404);
            echo 'Không tìm thấy trang.';
            return;
        }

        [$controllerClass, $action] = $this->routes[$path];
        $arguments = $this->routes[$path][2] ?? [];

        $controller = new $controllerClass();
        $controller->{$action}(...$arguments);
    }

    /** @return array<string, array> */
    public function routes(): array
    {
        return $this->routes;
    }
}
