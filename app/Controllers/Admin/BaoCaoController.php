<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\Reports\BaoCaoCoSo;
use App\Services\Reports\BaoCaoKhachHang;
use App\Services\Reports\XuatBaoCao;
use App\Services\Reports\KhoangThoiGianBaoCao;
use App\Services\Reports\BaoCaoDoanhThu;
use App\Services\BaoCaoDaLuuService;
use App\Services\BaoCaoDaXuatService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lớp BaoCaoController - trang "Báo cáo và thống kê" (admin/bao_cao.php), chỉ dành cho quản lý.
 *  - view=list:     danh sách báo cáo (mặc định) + báo cáo đã lưu
 *  - view=sales:    báo cáo bán hàng (BaoCaoDoanhThu)
 *  - view=customer: báo cáo khách hàng (BaoCaoKhachHang)
 *  - export=xlsx|csv: xuất báo cáo đang xem ra tệp
 */
class BaoCaoController extends BaseAdminController
{
    protected bool $requireManager = true;

    private BaoCaoDaLuuService $savedReports;

    private XuatBaoCao $exporter;

    private BaoCaoDaXuatService $exportedReports;

    public function __construct(?Database $db = null, ?BaoCaoDaLuuService $savedReports = null, ?XuatBaoCao $exporter = null, ?BaoCaoDaXuatService $exportedReports = null)
    {
        parent::__construct($db);
        $this->savedReports = $savedReports ?? new BaoCaoDaLuuService($this->db);
        $this->exporter = $exporter ?? new XuatBaoCao();
        $this->exportedReports = $exportedReports ?? new BaoCaoDaXuatService($this->db);
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

        $exportedReportsAvailable = true;
        try {
            $this->exportedReports->ensureStorage();
        } catch (RuntimeException $e) {
            $exportedReportsAvailable = false;
            $reportError = $reportError ?: $e->getMessage();
        }

        $reportsCsrf = $this->fixedLengthCsrfToken('reports_csrf');

        if (Request::isPost() && $savedReportsAvailable && $exportedReportsAvailable) {
            $reportNotice = $this->handlePost($reportsCsrf, $adminUsername, $reportNotice);
        }

        // ── Tham số hiển thị ──
        $query = $_GET;
        $exportFormat = strtolower((string) ($query['export'] ?? ''));
        $isExport = in_array($exportFormat, ['xlsx', 'csv'], true);
        $reportView = (string) ($query['view'] ?? ($isExport ? 'sales' : 'list'));
        if (!in_array($reportView, ['list', 'sales', 'customer', 'exported'], true)) {
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

        $common = array_merge($this->layoutData('bao_cao.php'), [
            'adminUsername' => $adminUsername,
            'reportView' => $reportView,
            'reportNotice' => $reportNotice,
            'reportError' => $reportError,
            'reportsCsrf' => $reportsCsrf,
            'savedReportId' => $savedReportId,
            'currentSavedReport' => $currentSavedReport,
        ]);

        if ($reportView === 'list') {
            $exportedReports = [];
            if ($exportedReportsAvailable) {
                try {
                    $exportedReports = $this->exportedReports->listEntries();
                } catch (RuntimeException $e) {
                    $common['reportError'] = $common['reportError'] ?: $e->getMessage();
                }
            }

            return array_merge($common, $this->listData($savedReports), ['exportedReports' => $exportedReports]);
        }

        if ($reportView === 'exported') {
            $exportedEntry = $exportedReportsAvailable
                ? $this->exportedReports->findWithSnapshot((int) ($query['id'] ?? 0))
                : null;
            if ($exportedEntry === null) {
                Session::set('reports_notice', ['type' => 'error', 'message' => 'Không tìm thấy báo cáo đã xuất.']);
                Response::redirect(Url::base() . '/admin/bao_cao.php');
            }

            // Xuất lại tệp từ bản đã lưu (đúng dữ liệu tại thời điểm xuất trước đó).
            if ($isExport && $exportedEntry['snapshot'] !== null) {
                $customName = Request::query('filename');
                $fileName = XuatBaoCao::safeFilename(is_string($customName) ? $customName : null)
                    ?? XuatBaoCao::safeFilename((string) $exportedEntry['name'])
                    ?? 'bao-cao';
                $rows = BaoCaoDaXuatService::exportRowsFromSnapshot($exportedEntry);
                if ($exportFormat === 'csv') {
                    $this->exporter->sendCsv($fileName . '.csv', $rows);
                } else {
                    $this->exporter->sendExcel($fileName . '.xlsx', $rows);
                }
            }

            return array_merge($common, ['exportedEntry' => $exportedEntry]);
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
                Response::redirect(Url::base() . '/admin/bao_cao.php?' . http_build_query($config));
            } elseif ($action === 'update_saved_report') {
                $message = $this->savedReports->saveTitle(
                    false,
                    Request::post('title', ''),
                    Request::post('report_type', ''),
                    Request::post('report_id'),
                    $adminUsername
                );
                Session::set('reports_notice', ['type' => 'success', 'message' => $message]);
                Response::redirect(Url::base() . '/admin/bao_cao.php');
            } elseif ($action === 'delete_exported_report') {
                $message = $this->exportedReports->delete(Request::post('report_id'));
                Session::set('reports_notice', ['type' => 'success', 'message' => $message]);
                Response::redirect(Url::base() . '/admin/bao_cao.php');
            } elseif ($action === 'delete_saved_report') {
                $message = $this->savedReports->delete(Request::post('report_id'));
                Session::set('reports_notice', ['type' => 'success', 'message' => $message]);
                Response::redirect(Url::base() . '/admin/bao_cao.php');
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
        foreach (BaoCaoDoanhThu::SECTIONS as $section => $title) {
            $salesReportLinks[] = [$section, $title, '#' . BaoCaoDoanhThu::SECTION_TARGETS[$section]];
        }
        $customerReportLinks = [];
        foreach (BaoCaoKhachHang::TYPES as $report => $title) {
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
        $range = KhoangThoiGianBaoCao::fromInput(
            (string) ($query['period'] ?? 'this_month'),
            (string) ($query['from'] ?? ''),
            (string) ($query['to'] ?? '')
        );
        // Khoảng ngày tùy chọn không hợp lệ => quay về "tháng này".
        $period = $range->isValid() ? $range->period() : 'this_month';

        $report = new BaoCaoKhachHang($range, (string) ($query['customer_report'] ?? 'new'), $activeReportTitle, $this->db);
        $report->load();
        $this->exportIfRequested($report, $isExport, $exportFormat, 'customer', [
            'view' => 'customer',
            'customer_report' => $report->type(),
            'period' => 'custom',
            'from' => $range->startDate(),
            'to' => $range->endDate(),
        ]);

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
        $range = KhoangThoiGianBaoCao::fromInput(
            (string) ($query['period'] ?? 'this_month'),
            (string) ($query['from'] ?? ''),
            (string) ($query['to'] ?? '')
        );

        $report = new BaoCaoDoanhThu(
            $range,
            (string) ($query['section'] ?? 'revenue'),
            $query['category_id'] ?? 0,
            $activeReportTitle,
            $reportError,
            $this->db
        );
        $report->load();
        $this->exportIfRequested($report, $isExport, $exportFormat, 'sales', [
            'view' => 'sales',
            'section' => $report->section(),
            'period' => 'custom',
            'from' => $range->startDate(),
            'to' => $range->endDate(),
            'category_id' => $report->categoryId(),
        ]);

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
            'salesSectionTargets' => BaoCaoDoanhThu::SECTION_TARGETS,
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
    private function exportIfRequested(BaoCaoCoSo $report, bool $isExport, string $exportFormat, string $reportType, array $config): void
    {
        if ($isExport && !$report->hasError()) {
            $customName = Request::query('filename');
            $customName = is_string($customName) ? $customName : null;
            // Ghi vào danh sách "Báo cáo đã xuất" kèm dữ liệu tại thời điểm xuất.
            $this->exportedReports->recordExport(
                XuatBaoCao::safeFilename($customName) ?? $report->title(),
                $reportType,
                $config,
                $exportFormat,
                Auth::adminUsername(),
                $this->snapshotOf($report, $config)
            );
            $this->exporter->export($report, $exportFormat, $customName);
        }
    }

    /** Dữ liệu báo cáo tại thời điểm xuất (hiển thị lại đúng như lúc xuất, không tính lại từ dữ liệu hiện tại). */
    private function snapshotOf(BaoCaoCoSo $report, array $config): array
    {
        $snapshot = [
            'title' => $report->title(),
            'from' => (string) ($config['from'] ?? ''),
            'to' => (string) ($config['to'] ?? ''),
            'headers' => $report->detailHeaders(),
            'rows' => $report->detailRows(),
            'summary' => [],
            'exported_at' => date('Y-m-d H:i:s'),
        ];
        if ($report instanceof BaoCaoDoanhThu) {
            $summary = $report->summary();
            $snapshot['category'] = $report->categoryId() > 0 ? (string) ($report->categories()[$report->categoryId()] ?? '') : 'Tất cả danh mục';
            $snapshot['summary'] = [
                ['Doanh thu đã giao', number_format((float) $summary['revenue'], 0, ',', '.') . ' đ'],
                ['Tổng đơn hàng', number_format((int) $summary['total_orders'])],
                ['Đơn hàng đã giao', number_format((int) $summary['completed_orders'])],
                ['Đơn hàng đã hủy', number_format((int) $summary['cancelled_orders'])],
                ['Khách hàng mới', number_format((int) $summary['new_customers'])],
                ['Sản phẩm đã bán', number_format((int) $summary['items_sold'])],
            ];
        }

        return $snapshot;
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
        $this->render('admin/bao_cao', $this->handle());
    }
}
