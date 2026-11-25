<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\Reports\AbstractReport;
use App\Services\Reports\CustomerReport;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\SalesReport;
use App\Services\SavedReportService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lớp ReportController - trang "Báo cáo và thống kê" (admin/reports.php), chỉ dành cho quản lý.
 *  - view=list:     danh sách báo cáo (mặc định) + báo cáo đã lưu
 *  - view=sales:    báo cáo bán hàng (SalesReport)
 *  - view=customer: báo cáo khách hàng (CustomerReport)
 *  - export=xlsx|csv: xuất báo cáo đang xem ra tệp
 */
class ReportController extends BaseAdminController
{
    protected bool $requireManager = true;

    private SavedReportService $savedReports;

    private ReportExporter $exporter;

    public function __construct(?Database $db = null, ?SavedReportService $savedReports = null, ?ReportExporter $exporter = null)
    {
        parent::__construct($db);
        $this->savedReports = $savedReports ?? new SavedReportService($this->db);
        $this->exporter = $exporter ?? new ReportExporter();
    }

    /** Xử lý yêu cầu và trả về mảng biến cho view (hoặc chuyển hướng / gửi tệp xuất). */
    protected function handle(): array
    {
        $adminUsername = Auth::adminUsername();
        $reportError = '';
        $reportNotice = Session::get('reports_notice');
        Session::remove('reports_notice');

        $savedReportsAvailable = true;
        try {
            $this->savedReports->ensureStorage();
        } catch (RuntimeException $e) {
            $savedReportsAvailable = false;
            $reportError = $e->getMessage();
        }

        $reportsCsrf = $this->fixedLengthCsrfToken('reports_csrf');

        if (Request::isPost() && $savedReportsAvailable) {
            $reportNotice = $this->handlePost($reportsCsrf, $adminUsername, $reportNotice);
        }

        // ── Tham số hiển thị ──
        $query = $_GET;
        $exportFormat = strtolower((string) ($query['export'] ?? ''));
        $isExport = in_array($exportFormat, ['xlsx', 'csv'], true);
        $reportView = (string) ($query['view'] ?? ($isExport ? 'sales' : 'list'));
        if (!in_array($reportView, ['list', 'sales', 'customer'], true)) {
            $reportView = $isExport ? 'sales' : 'list';
        }
        if ($isExport && $reportView === 'list') {
            $reportView = 'sales';
        }

        $savedReports = [];
        if ($savedReportsAvailable) {
            try {
                $savedReports = $this->savedReports->listReports();
            } catch (RuntimeException $e) {
                $reportError = $e->getMessage();
            }
        }

        // ── Báo cáo đã lưu: áp dụng cấu hình bộ lọc đã lưu ──
        $savedReportId = filter_var($query['report_id'] ?? null, FILTER_VALIDATE_INT);
        $currentSavedReport = null;
        if ($savedReportId !== false && $savedReportId > 0 && $savedReportsAvailable) {
            $currentSavedReport = $this->savedReports->find($savedReportId);
        }
        if ($currentSavedReport && !empty($currentSavedReport['config_json'])) {
            $savedConfig = json_decode((string) $currentSavedReport['config_json'], true);
            if (is_array($savedConfig)) {
                $hasReportOverrides = count(array_diff(array_keys($query), ['view', 'report_id'])) > 0;
                $query = $hasReportOverrides
                    ? array_merge($savedConfig, $query)
                    : array_merge($query, $savedConfig);
                $reportView = (string) ($query['view'] ?? $reportView);
            }
        }
        $customTitleParam = trim((string) ($query['title'] ?? ''));
        $activeReportTitle = $currentSavedReport['title'] ?? ($customTitleParam !== '' ? $customTitleParam : null);

        $common = array_merge($this->layoutData('reports.php'), [
            'adminUsername' => $adminUsername,
            'reportView' => $reportView,
            'reportNotice' => $reportNotice,
            'reportError' => $reportError,
            'reportsCsrf' => $reportsCsrf,
            'savedReportId' => $savedReportId,
            'currentSavedReport' => $currentSavedReport,
        ]);

        if ($reportView === 'list') {
            return array_merge($common, $this->listData($savedReports));
        }

        if ($reportView === 'customer') {
            return array_merge($common, $this->customerData($query, $activeReportTitle, $savedReportId, $isExport, $exportFormat));
        }

        return array_merge($common, $this->salesData($query, $activeReportTitle, $savedReportId, $reportError, $isExport, $exportFormat));
    }

    /**
     * Xử lý các thao tác POST. Thành công thì chuyển hướng; lỗi thì trả về thông báo lỗi,
     * thao tác không xác định thì giữ nguyên thông báo hiện tại.
     *
     * @param mixed $currentNotice
     * @return mixed
     */
    private function handlePost(string $reportsCsrf, string $adminUsername, $currentNotice)
    {
        $submittedToken = Request::post('csrf_token', '');
        $submittedAction = Request::post('action', '');
        $action = is_string($submittedAction) ? $submittedAction : '';
        if (!is_string($submittedToken) || !hash_equals($reportsCsrf, $submittedToken)) {
            return ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.'];
        }

        try {
            if ($action === 'save_report_state') {
                $config = $this->savedReports->saveState(
                    Request::post('report_id'),
                    Request::post('title', ''),
                    Request::post('report_type', ''),
                    Request::post('report_config', ''),
                    $adminUsername
                );
                Session::set('reports_notice', ['type' => 'success', 'message' => 'Đã lưu cấu hình báo cáo.']);
                Response::redirect(Url::base() . '/admin/reports.php?' . http_build_query($config));
            } elseif (in_array($action, ['create_saved_report', 'update_saved_report'], true)) {
                $message = $this->savedReports->saveTitle(
                    $action === 'create_saved_report',
                    Request::post('title', ''),
                    Request::post('report_type', ''),
                    Request::post('report_id'),
                    $adminUsername
                );
                Session::set('reports_notice', ['type' => 'success', 'message' => $message]);
                Response::redirect(Url::base() . '/admin/reports.php');
            } elseif ($action === 'delete_saved_report') {
                $message = $this->savedReports->delete(Request::post('report_id'));
                Session::set('reports_notice', ['type' => 'success', 'message' => $message]);
                Response::redirect(Url::base() . '/admin/reports.php');
            }
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ['type' => 'error', 'message' => $e->getMessage()];
        }

        return $currentNotice;
    }

    /** Dữ liệu trang danh sách báo cáo. */
    private function listData(array $savedReports): array
    {
        $salesReportLinks = [];
        foreach (SalesReport::SECTIONS as $section => $title) {
            $salesReportLinks[] = [$section, $title, '#' . SalesReport::SECTION_TARGETS[$section]];
        }
        $customerReportLinks = [];
        foreach (CustomerReport::TYPES as $report => $title) {
            $customerReportLinks[] = [$report, $title];
        }

        return [
            'savedReports' => $savedReports,
            'salesReportLinks' => $salesReportLinks,
            'customerReportLinks' => $customerReportLinks,
        ];
    }

    /** Dữ liệu báo cáo khách hàng (hoặc xuất tệp). */
    private function customerData(array $query, ?string $activeReportTitle, $savedReportId, bool $isExport, string $exportFormat): array
    {
        $range = ReportPeriod::fromInput(
            (string) ($query['period'] ?? 'this_month'),
            (string) ($query['from'] ?? ''),
            (string) ($query['to'] ?? '')
        );
        // Khoảng ngày tùy chọn không hợp lệ => quay về "tháng này".
        $period = $range->isValid() ? $range->period() : 'this_month';

        $report = new CustomerReport($range, (string) ($query['customer_report'] ?? 'new'), $activeReportTitle, $this->db);
        $report->load();
        $this->exportIfRequested($report, $isExport, $exportFormat);

        $exportParams = [
            'view' => 'customer',
            'customer_report' => $report->type(),
            'period' => $period,
            'from' => $range->startDate(),
            'to' => $range->endDate(),
            'export' => 'xlsx',
        ];
        if ($savedReportId) {
            $exportParams['report_id'] = $savedReportId;
        }

        return [
            'customerReport' => $report->type(),
            'customerError' => $report->error(),
            'customerTitle' => $report->title(),
            'customerRange' => $report->rangeLabel(),
            'customerDetailHeaders' => $report->detailHeaders(),
            'customerDetailRows' => $report->detailRows(),
            'customerExportQueryXlsx' => http_build_query($exportParams),
            'period' => $period,
            'startDate' => $range->startDate(),
            'endDate' => $range->endDate(),
        ];
    }

    /** Dữ liệu báo cáo bán hàng (hoặc xuất tệp). */
    private function salesData(array $query, ?string $activeReportTitle, $savedReportId, string $reportError, bool $isExport, string $exportFormat): array
    {
        $range = ReportPeriod::fromInput(
            (string) ($query['period'] ?? 'this_month'),
            (string) ($query['from'] ?? ''),
            (string) ($query['to'] ?? '')
        );

        $report = new SalesReport(
            $range,
            (string) ($query['section'] ?? 'revenue'),
            $query['category_id'] ?? 0,
            $activeReportTitle,
            $reportError,
            $this->db
        );
        $report->load();
        $this->exportIfRequested($report, $isExport, $exportFormat);

        $exportParams = [
            'view' => 'sales',
            'section' => $report->section(),
            'period' => $range->period(),
            'from' => $range->startDate(),
            'to' => $range->endDate(),
            'category_id' => $report->categoryId(),
            'export' => 'xlsx',
        ];
        if ($savedReportId) {
            $exportParams['report_id'] = $savedReportId;
        }

        return [
            'reportError' => $report->error(),
            'salesSection' => $report->section(),
            'salesSectionTargets' => SalesReport::SECTION_TARGETS,
            'salesTitle' => $report->title(),
            'categories' => $report->categories(),
            'categoryId' => $report->categoryId(),
            'summary' => $report->summary(),
            'detailHeaders' => $report->detailHeaders(),
            'detailRows' => $report->detailRows(),
            'selectedPeriodLabel' => $range->label(),
            'exportQueryXlsx' => http_build_query($exportParams),
            'period' => $range->period(),
            'startDate' => $range->startDate(),
            'endDate' => $range->endDate(),
        ];
    }

    /** Xuất tệp nếu có yêu cầu export và báo cáo không lỗi (kết thúc request). */
    private function exportIfRequested(AbstractReport $report, bool $isExport, string $exportFormat): void
    {
        if ($isExport && !$report->hasError()) {
            $this->exporter->export($report, $exportFormat);
        }
    }

    /** Token CSRF 64 ký tự hex lưu trong phiên; tạo lại nếu thiếu hoặc sai định dạng. */
    private function fixedLengthCsrfToken(string $name): string
    {
        $token = Session::get($name);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set($name, $token);
        }

        return $token;
    }

    /** Action: hiển thị View admin/reports. */
    public function index(): void
    {
        $this->render('admin/reports', $this->handle());
    }
}
