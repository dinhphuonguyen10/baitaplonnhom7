<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Services\ProductAdminService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controller trang admin/products.php - CRUD sản phẩm, tải ảnh, xóa mềm / khôi phục / xóa vĩnh viễn.
 */
class ProductController extends BaseAdminController
{
    private ProductAdminService $products;

    public function __construct(?Database $db = null, ?ProductAdminService $products = null)
    {
        parent::__construct($db);
        $this->products = $products ?? new ProductAdminService();
    }

    /** Xử lý form (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $layout = $this->layoutData('products.php');
        $message = '';
        $messageType = '';

        if (Request::isPost()) {
            try {
                $result = $this->dispatch((string) Request::post('action', ''), (string) $layout['adminUsername']);
                if ($result !== null) {
                    $message = $result;
                    $messageType = 'success';
                }
            } catch (InvalidArgumentException | RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }

        // Chế độ xem: 'active' (đang hoạt động) hoặc 'trash' (thùng rác)
        $currentView = Request::query('view', 'active') === 'trash' ? 'trash' : 'active';

        return array_merge($layout, $this->products->listing($currentView), [
            'message' => $message,
            'messageType' => $messageType,
            'currentView' => $currentView,
        ]);
    }

    private function dispatch(string $action, string $adminUsername): ?string
    {
        $id = (int) Request::post('id', 0);

        switch ($action) {
            case 'add':
                return $this->products->create($_POST, Request::file('image_file'));
            case 'edit':
                return $this->products->update($_POST, Request::file('image_file'));
            case 'delete':
                return $this->products->softDelete($id, $adminUsername);
            case 'restore':
                return $this->products->restore($id);
            case 'force_delete':
                return $this->products->forceDelete($id);
            default:
                return null;
        }
    }

    /** Action: hiển thị View admin/products. */
    public function index(): void
    {
        $this->render('admin/products', $this->handle());
    }
}
