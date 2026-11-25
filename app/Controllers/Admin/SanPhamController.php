<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Services\QuanLySanPhamService;
use Throwable;

/**
 * Controller trang admin/san_pham.php - thêm / sửa sản phẩm (SKU, khuyến mãi, ảnh), xóa mềm / khôi phục.
 * Không còn xóa vĩnh viễn. Mọi thao tác POST cần CSRF token riêng (khóa phiên products_csrf_token).
 */
class SanPhamController extends BaseAdminController
{
    private const CSRF_KEY = 'products_csrf_token';

    private QuanLySanPhamService $products;

    public function __construct(?Database $db = null, ?QuanLySanPhamService $products = null)
    {
        parent::__construct($db);
        $this->products = $products ?? new QuanLySanPhamService();
    }

    /** Xử lý form (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $layout = $this->layoutData('san_pham.php');
        $csrfToken = $this->csrfToken(self::CSRF_KEY);
        $message = '';
        $messageType = '';

        if (Request::isPost()) {
            $action = $_POST['action'] ?? '';
            if (!$this->verifyCsrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null, self::CSRF_KEY)) {
                $message = 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.';
                $messageType = 'error';
            } else {
                $result = $this->products->handleAction(
                    is_string($action) ? $action : '',
                    $_POST,
                    Request::file('image_file'),
                    (string) $layout['adminUsername']
                );
                if ($result !== null) {
                    [$message, $messageType] = $result;
                }
            }
        }

        // Chế độ xem: 'active' (đang hoạt động) hoặc 'trash' (thùng rác)
        $currentView = Request::query('view', 'active') === 'trash' ? 'trash' : 'active';

        try {
            $listing = $this->products->listing($currentView);
        } catch (Throwable $e) {
            error_log('Admin product list failed: ' . $e->getMessage());
            $listing = ['countActive' => 0, 'countTrash' => 0, 'categories' => [], 'products' => []];
            $message = 'Không thể tải danh sách sản phẩm.';
            $messageType = 'error';
        }

        return array_merge($layout, $listing, [
            'message' => $message,
            'messageType' => $messageType,
            'currentView' => $currentView,
            'productsCsrfToken' => $csrfToken,
        ]);
    }

    /** Action: hiển thị View admin/products. */
    public function index(): void
    {
        $this->render('admin/san_pham', $this->handle());
    }
}
