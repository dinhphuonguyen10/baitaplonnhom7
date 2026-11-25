<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CategoryService;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Controller trang admin/categories.php - thêm / sửa / xóa mềm / khôi phục danh mục sản phẩm.
 * Mọi thao tác POST cần CSRF token riêng (khóa phiên categories_csrf_token).
 */
class CategoryController extends BaseAdminController
{
    private const CSRF_KEY = 'categories_csrf_token';

    private CategoryService $categories;

    public function __construct(?Database $db = null, ?CategoryService $categories = null)
    {
        parent::__construct($db);
        $this->categories = $categories ?? new CategoryService();
    }

    /** Xử lý form (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $layout = $this->layoutData('categories.php');
        $csrfToken = $this->csrfToken(self::CSRF_KEY);
        $message = '';
        $messageType = '';

        if (Request::isPost()) {
            try {
                if (!$this->verifyCsrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null, self::CSRF_KEY)) {
                    throw new DomainException('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
                }
                $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
                $message = $this->categories->handleAction($action, $_POST, Auth::adminUsername());
                $messageType = 'success';
            } catch (Throwable $e) {
                if (!($e instanceof DomainException) && !($e instanceof InvalidArgumentException)) {
                    error_log('Category management failed: ' . $e->getMessage());
                }
                $message = $e instanceof DomainException || $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : CategoryService::MSG_SYSTEM_ERROR;
                $messageType = 'error';
            }
        }

        try {
            $categories = $this->categories->listForAdmin((string) $layout['appBaseUrl']);
        } catch (Throwable $e) {
            error_log('Admin category list failed: ' . $e->getMessage());
            $categories = [];
            $message = 'Không thể tải danh sách danh mục.';
            $messageType = 'error';
        }

        return array_merge($layout, [
            'message' => $message,
            'messageType' => $messageType,
            'categories' => $categories,
            'categoriesCsrfToken' => $csrfToken,
        ]);
    }

    /** Action: hiển thị View admin/categories. */
    public function index(): void
    {
        $this->render('admin/categories', $this->handle());
    }
}
