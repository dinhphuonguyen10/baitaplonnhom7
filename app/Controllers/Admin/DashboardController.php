<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\DashboardService;

/**
 * Controller trang Tổng quan quản trị (admin/index.php) và các trang chuyển hướng
 * cũ (admin/dashboard.php, admin/manager/index.php, admin/staff/index.php).
 */
class DashboardController extends BaseAdminController
{
    private ?DashboardService $dashboard;

    public function __construct(?Database $db = null, ?DashboardService $dashboard = null)
    {
        parent::__construct($db);
        $this->dashboard = $dashboard;
    }

    /** Dữ liệu cho trang Tổng quan (lọc theo ?period=&from=&to=). */
    protected function indexData(): array
    {
        $dashboard = $this->dashboard ?? new DashboardService($this->db);
        $period = Request::query('period', 'this_month');
        $from = Request::query('from', '');
        $to = Request::query('to', '');

        $data = $dashboard->overview(
            is_scalar($period) ? (string) $period : 'this_month',
            is_scalar($from) ? (string) $from : '',
            is_scalar($to) ? (string) $to : ''
        );
        $data['formatCompact'] = static fn(float $value): string => DashboardService::formatCompact($value);

        return array_merge($this->layoutData('index.php'), $data);
    }

    /** admin/dashboard.php: chuyển về trang tổng quan. */
    public function legacyDashboard(): void
    {
        $this->redirectTo('/admin/index.php');
    }

    /** admin/manager/index.php: chỉ quản lý, chuyển về trang chủ theo vai trò. */
    public function managerHome(): void
    {
        Auth::requireManager();
        Response::redirect(Url::adminHome((string) (Auth::adminRole() ?? 'manager')));
    }

    /** admin/staff/index.php: chỉ nhân viên, chuyển về trang tổng quan. */
    public function staffHome(): void
    {
        Auth::requireStaff();
        $this->redirectTo('/admin/index.php');
    }

    /** Action: hiển thị View admin/dashboard. */
    public function index(): void
    {
        $this->render('admin/dashboard', $this->indexData());
    }
}
