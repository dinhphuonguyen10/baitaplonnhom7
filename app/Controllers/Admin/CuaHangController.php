<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Services\CuaHangService;
use InvalidArgumentException;

/**
 * Controller trang admin/cua_hang.php - xem bản đồ và quản lý hệ thống cửa hàng.
 * Chỉ dành cho quản lý.
 */
class CuaHangController extends BaseAdminController
{
    protected bool $requireManager = true;

    private const CSRF_KEY = 'stores_csrf';

    private CuaHangService $stores;

    public function __construct(?Database $db = null, ?CuaHangService $stores = null)
    {
        parent::__construct($db);
        $this->stores = $stores ?? new CuaHangService();
    }

    /** Xử lý POST (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $csrfToken = $this->csrfToken(self::CSRF_KEY);
        $error = '';
        $success = '';

        if (Request::isPost()) {
            $token = Request::post('csrf_token', '');
            $action = Request::post('action', '');

            if (!$this->verifyCsrf(is_string($token) ? $token : null, self::CSRF_KEY)) {
                $error = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
            } elseif ($action === 'delete') {
                $success = $this->stores->delete((int) Request::post('id', 0));
            } elseif ($action === 'save') {
                try {
                    $success = $this->stores->save(
                        (int) Request::post('id', 0),
                        Request::string('name', '', 'post'),
                        Request::string('address', '', 'post'),
                        Request::string('phone', '', 'post'),
                        Request::string('hours', '', 'post'),
                        Request::string('status', '', 'post')
                    );
                } catch (InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                }
            }
        }

        $stores = $this->stores->listStores();

        return array_merge($this->layoutData('cua_hang.php'), [
            'error' => $error,
            'success' => $success,
            'csrfToken' => $csrfToken,
            'stores' => $stores,
            'firstStore' => $stores[0] ?? ['id' => 0, 'name' => 'Chưa có cửa hàng', 'address' => '', 'phone' => '', 'hours' => '', 'status' => 'inactive'],
        ]);
    }

    /** Action: hiển thị View admin/stores. */
    public function index(): void
    {
        $this->render('admin/cua_hang', $this->handle());
    }
}
