<?php
/**
 * app/bootstrap.php
 * Điểm khởi động chung của ứng dụng theo hướng đối tượng:
 *  - Đăng ký autoloader PSR-4 cho namespace App\ (thư mục app/)
 *  - Khởi động phiên làm việc (Session)
 *
 * Mọi file trang/API chỉ cần: require_once __DIR__ . '/<...>/app/bootstrap.php';
 */

declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('VIEW_PATH')) {
    define('VIEW_PATH', __DIR__ . '/Views');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = __DIR__ . '/' . $relative . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

App\Core\Session::start();
