<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\DanhMucService;
use DomainException;
use InvalidArgumentException;
use Throwable;

/**
 * Controller trang admin/danh_muc.php - thêm / sửa / xóa mềm / khôi phục danh mục sản phẩm.
 * Mọi thao tác POST cần CSRF token riêng (khóa phiên categories_csrf_token).
 */
class DanhMucController extends BaseAdminController
{
    private const CSRF_KEY = 'categories_csrf_token';

    private DanhMucService $categories;

    public function __construct(?Database $db = null, ?DanhMucService $categories = null)
    {
        parent::__construct($db);
        $this->categories = $categories ?? new DanhMucService();
    }

    /** Xử lý form (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $layout = $this->layoutData('danh_muc.php');
        $csrfToken = $this->csrfToken(self::CSRF_KEY);
        $message = '';
        $messageType = '';

        if (Request::isPost()) {
            try {
                if (!$this->verifyCsrf(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null, self::CSRF_KEY)) {
                    throw new DomainException('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
                }
                $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
                $message = $this->categories->handleAction($action, $_POST, Auth::adminUsername(), Request::file('image_file'));
                $messageType = 'success';
            } catch (Throwable $e) {
                if (!($e instanceof DomainException) && !($e instanceof InvalidArgumentException)) {
                    error_log('Category management failed: ' . $e->getMessage());
                }
                $message = $e instanceof DomainException || $e instanceof InvalidArgumentException
                    ? $e->getMessage()
                    : DanhMucService::MSG_SYSTEM_ERROR;
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
        $this->render('admin/danh_muc', $this->handle());
    }
}
