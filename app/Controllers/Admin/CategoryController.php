<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Services\CategoryService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controller trang admin/categories.php - thêm / sửa / xóa danh mục sản phẩm.
 */
class CategoryController extends BaseAdminController
{
    private CategoryService $categories;

    public function __construct(?Database $db = null, ?CategoryService $categories = null)
    {
        parent::__construct($db);
        $this->categories = $categories ?? new CategoryService();
    }

    /** Xử lý form (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $message = '';
        $messageType = '';

        if (Request::isPost()) {
            try {
                $result = $this->dispatch((string) Request::post('action', ''));
                if ($result !== null) {
                    $message = $result;
                    $messageType = 'success';
                }
            } catch (InvalidArgumentException | RuntimeException $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }

        return array_merge($this->layoutData('categories.php'), [
            'message' => $message,
            'messageType' => $messageType,
            'categories' => $this->categories->listForAdmin(),
        ]);
    }

    private function dispatch(string $action): ?string
    {
        switch ($action) {
            case 'add':
                return $this->categories->add(Request::string('name', '', 'post'));
            case 'edit':
                return $this->categories->edit((int) Request::post('id', 0), Request::string('name', '', 'post'));
            case 'delete':
                return $this->categories->delete((int) Request::post('id', 0));
            default:
                return null;
        }
    }

    /** Action: hiển thị View admin/categories. */
    public function index(): void
    {
        $this->render('admin/categories', $this->handle());
    }
}
