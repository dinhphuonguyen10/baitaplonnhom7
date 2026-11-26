<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use App\Core\Url;
use App\Core\View;

/**
 * Lớp trừu tượng BaseAdminController - lớp cha cho các controller trang quản trị.
 *  - Constructor bắt buộc đăng nhập khu vực quản trị (Auth::requireAdmin)
 *  - Lớp con đặt $requireManager = true để chỉ cho phép quản lý (manager/admin)
 *  - Cung cấp tiện ích CSRF và dữ liệu chung cho layout (sidebar/topbar)
 */
abstract class BaseAdminController extends \App\Core\Controller
{
    /** Menu sidebar quản trị: [icon, nhãn, đường dẫn, tên file, chỉ dành cho quản lý]. */
    public const SIDE_MENU = [
        ['icon' => '▦', 'label' => 'Tổng quan',                    'href' => '/admin/index.php',     'file' => 'index.php'],
        ['icon' => '◇', 'label' => 'Quản lý sản phẩm',             'href' => '/admin/san_pham.php',  'file' => 'san_pham.php'],
        ['icon' => '☆', 'label' => 'Quản lý đánh giá',             'href' => '/admin/danh_gia.php',   'file' => 'danh_gia.php'],
        ['icon' => '▧', 'label' => 'Quản lý danh mục',             'href' => '/admin/danh_muc.php','file' => 'danh_muc.php'],
        ['icon' => '♙', 'label' => 'Quản lý tài khoản', 'href' => '/admin/tai_khoan.php',     'file' => 'tai_khoan.php', 'manager_only' => true],
        ['icon' => '▤', 'label' => 'Đơn hàng & Thanh toán',         'href' => '/admin/don_hang.php',    'file' => 'don_hang.php'],
        ['icon' => '☏', 'label' => 'Quản lý chat',                 'href' => '/admin/tro_chuyen.php',      'file' => 'tro_chuyen.php'],
        ['icon' => '▥', 'label' => 'Báo cáo và thống kê',          'href' => '/admin/bao_cao.php',   'file' => 'bao_cao.php', 'manager_only' => true],
        ['icon' => '⌖', 'label' => 'Quản lý hệ thống cửa hàng',    'href' => '/admin/cua_hang.php',    'file' => 'cua_hang.php', 'manager_only' => true],
        ['icon' => '⌂', 'label' => 'Về cửa hàng chính',           'href' => '/index.php',           'file' => 'storefront-home'],
    ];

    /** Đặt true ở lớp con nếu trang chỉ dành cho quản lý. */
    protected bool $requireManager = false;

    protected Database $db;

    public function __construct(?Database $db = null)
    {
        if ($this->requireManager) {
            Auth::requireManager();
        } else {
            Auth::requireAdmin();
        }

        $this->db = $db ?? Database::getInstance();
    }

    /** Lấy (hoặc tạo) CSRF token theo tên khóa phiên. */
    protected function csrfToken(string $name = 'csrf_token'): string
    {
        return Session::csrfToken($name);
    }

    /** Kiểm tra CSRF token gửi lên (mặc định trường POST csrf_token). */
    protected function verifyCsrf(?string $token, string $name = 'csrf_token'): bool
    {
        return Session::verifyCsrf($token, $name);
    }

    /**
     * Dữ liệu chung cho layout quản trị: $isManager, $roleLabel, $adminUsername, $appBaseUrl, $escape.
     */
    protected function layoutData(string $currentPage = ''): array
    {
        $isManager = Auth::isManager();

        return [
            'isManager' => $isManager,
            'roleLabel' => $isManager ? 'Quản lý' : 'Nhân viên',
            'adminUsername' => Auth::adminUsername(),
            'appBaseUrl' => Url::base(),
            'escape' => static fn($value): string => View::e($value),
            'adminCurrentPage' => $currentPage !== '' ? $currentPage : Url::currentPage(),
        ];
    }

    /**
     * Bổ sung dữ liệu cho sidebar quản trị (app/Views/layouts/thanh_ben_quan_tri.php) khi render View.
     */
    protected function shareLayoutData(array $data): array
    {
        $isManager = Auth::isManager();
        $data += [
            'appBaseUrl' => Url::base(),
            'escape' => static fn($value): string => View::e($value),
        ];
        $data['isManager'] = $isManager;
        $data['roleLabel'] = $isManager ? 'Quản lý' : 'Nhân viên';
        $data['currentScript'] = $data['adminCurrentPage'] ?? Url::currentPage();
        $data['sideMenu'] = array_values(array_filter(
            self::SIDE_MENU,
            static fn(array $item): bool => empty($item['manager_only']) || $isManager
        ));

        return $data;
    }

    /** Chuyển hướng tới đường dẫn trong ứng dụng và kết thúc request. */
    protected function redirectTo(string $path, int $status = 302): void
    {
        \App\Core\Response::redirect(Url::to($path), $status);
    }
}
