<?php
require_once __DIR__ . '/../include/admin_guard.php';
requireManagerRole();
require_once __DIR__ . '/../config/db.php';

$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$sendCsvFile = static function (string $filename, array $rows): void {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        error_log('CSV report export could not open output stream.');
        http_response_code(500);
        exit('Không thể tạo tệp CSV. Vui lòng thử lại.');
    }
    // Write UTF-8 BOM so Microsoft Excel opens Vietnamese characters correctly
    fwrite($output, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        $sanitizedRow = array_map(static function ($cell) {
            $val = (string) $cell;
            if (preg_match('/^[\s]*[=+\-@]/u', $val)) {
                return "'" . $val;
            }
            return $val;
        }, array_values($row));
        fputcsv($output, $sanitizedRow);
    }
    fclose($output);
    exit;
};

$sendExcelFile = static function (string $filename, array $rows) use (&$sendCsvFile): void {
    if (!class_exists(ZipArchive::class)) {
        $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $sendCsvFile($csvFilename, $rows);
        return;
    }

    $worksheetRows = '';
    foreach ($rows as $rowIndex => $row) {
        $rowNumber = $rowIndex + 1;
        $worksheetRows .= '<row r="' . $rowNumber . '">';
        foreach (array_values($row) as $columnIndex => $value) {
            $columnNumber = $columnIndex + 1;
            $columnName = '';
            while ($columnNumber > 0) {
                $remainder = ($columnNumber - 1) % 26;
                $columnName = chr(65 + $remainder) . $columnName;
                $columnNumber = intdiv($columnNumber - 1, 26);
            }
            $cellReference = $columnName . $rowNumber;
            if (is_int($value) || is_float($value)) {
                $numericValue = is_float($value)
                    ? rtrim(rtrim(sprintf('%.10F', $value), '0'), '.')
                    : (string) $value;
                $worksheetRows .= '<c r="' . $cellReference . '"><v>' . $numericValue . '</v></c>';
            } else {
                $cellValue = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $worksheetRows .= '<c r="' . $cellReference . '" t="inlineStr"><is><t xml:space="preserve">'
                    . $cellValue . '</t></is></c>';
            }
        }
        $worksheetRows .= '</row>';
    }

    $workbookFiles = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Bao cao" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $worksheetRows . '</sheetData></worksheet>',
    ];

    $temporaryFile = tempnam(sys_get_temp_dir(), 'report_xlsx_');
    if ($temporaryFile === false) {
        $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $sendCsvFile($csvFilename, $rows);
        return;
    }

    $archive = new ZipArchive();
    if ($archive->open($temporaryFile, ZipArchive::OVERWRITE) !== true) {
        unlink($temporaryFile);
        $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $sendCsvFile($csvFilename, $rows);
        return;
    }
    foreach ($workbookFiles as $path => $content) {
        if (!$archive->addFromString($path, $content)) {
            $archive->close();
            unlink($temporaryFile);
            $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
            $sendCsvFile($csvFilename, $rows);
            return;
        }
    }
    if (!$archive->close()) {
        unlink($temporaryFile);
        $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $sendCsvFile($csvFilename, $rows);
        return;
    }

    $fileContents = file_get_contents($temporaryFile);
    if (!unlink($temporaryFile)) {
        error_log('Excel report export could not remove temporary file: ' . $temporaryFile);
    }
    if ($fileContents === false) {
        $csvFilename = preg_replace('/\.xlsx$/i', '.csv', $filename);
        $sendCsvFile($csvFilename, $rows);
        return;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($fileContents));
    header('X-Content-Type-Options: nosniff');
    echo $fileContents;
    exit;
};
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'reports.php';
$reportError = '';
$reportNotice = $_SESSION['reports_notice'] ?? null;
unset($_SESSION['reports_notice']);

$savedReportsTableSql = "CREATE TABLE IF NOT EXISTS admin_saved_reports (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(250) NOT NULL,
    report_type enum('sales', 'customer') NOT NULL,
    created_by varchar(100) NOT NULL DEFAULT '',
    created_at datetime NOT NULL DEFAULT current_timestamp(),
    updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_admin_saved_reports_type (report_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
$savedReportsAvailable = $conn->query($savedReportsTableSql);
if (!$savedReportsAvailable) {
    $reportError = 'Không thể khởi tạo danh sách báo cáo đã lưu.';
    error_log('Admin saved reports table setup failed: ' . $conn->error);
}

if (!isset($_SESSION['reports_csrf']) || !is_string($_SESSION['reports_csrf']) || strlen($_SESSION['reports_csrf']) !== 64) {
    $_SESSION['reports_csrf'] = bin2hex(random_bytes(32));
}
$reportsCsrf = $_SESSION['reports_csrf'];
$reportTypes = ['sales' => 'Bán hàng', 'customer' => 'Khách hàng'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $savedReportsAvailable) {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $submittedAction = $_POST['action'] ?? '';
    $action = is_string($submittedAction) ? $submittedAction : '';
    if (!is_string($submittedToken) || !hash_equals($reportsCsrf, $submittedToken)) {
        $reportNotice = ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.'];
    } elseif (in_array($action, ['create_saved_report', 'update_saved_report'], true)) {
        $submittedTitle = $_POST['title'] ?? '';
        $submittedType = $_POST['report_type'] ?? '';
        $title = is_string($submittedTitle) ? trim($submittedTitle) : '';
        $reportType = is_string($submittedType) ? $submittedType : '';
        $reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
        if (!preg_match('/^.{1,250}$/us', $title) || !isset($reportTypes[$reportType])) {
            $reportNotice = ['type' => 'error', 'message' => 'Vui lòng nhập tên báo cáo hợp lệ và chọn loại báo cáo.'];
        } elseif ($action === 'update_saved_report' && ($reportId === false || $reportId < 1)) {
            $reportNotice = ['type' => 'error', 'message' => 'Báo cáo cần sửa không hợp lệ.'];
        } else {
            if ($action === 'create_saved_report') {
                $saveReportStmt = $conn->prepare('INSERT INTO admin_saved_reports (title, report_type, created_by) VALUES (?, ?, ?)');
                if ($saveReportStmt) {
                    $saveReportStmt->bind_param('sss', $title, $reportType, $adminUsername);
                }
            } else {
                $saveReportStmt = $conn->prepare('UPDATE admin_saved_reports SET title = ?, report_type = ? WHERE id = ?');
                if ($saveReportStmt) {
                    $saveReportStmt->bind_param('ssi', $title, $reportType, $reportId);
                }
            }

            if (!$saveReportStmt) {
                $reportNotice = ['type' => 'error', 'message' => 'Không thể lưu báo cáo.'];
                error_log('Admin saved report prepare failed: ' . $conn->error);
            } elseif (!$saveReportStmt->execute()) {
                $reportNotice = ['type' => 'error', 'message' => 'Không thể lưu báo cáo.'];
                error_log('Admin saved report save failed: ' . $saveReportStmt->error);
                $saveReportStmt->close();
            } else {
                $saveReportStmt->close();
                $_SESSION['reports_notice'] = [
                    'type' => 'success',
                    'message' => $action === 'create_saved_report' ? 'Đã thêm báo cáo.' : 'Đã cập nhật báo cáo.',
                ];
                header('Location: ' . $appBaseUrl . '/admin/reports.php');
                exit;
            }
        }
    } elseif ($action === 'delete_saved_report') {
        $reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
        if ($reportId === false || $reportId < 1) {
            $reportNotice = ['type' => 'error', 'message' => 'Báo cáo cần xóa không hợp lệ.'];
        } else {
            $deleteReportStmt = $conn->prepare('DELETE FROM admin_saved_reports WHERE id = ?');
            if (!$deleteReportStmt) {
                $reportNotice = ['type' => 'error', 'message' => 'Không thể xóa báo cáo.'];
                error_log('Admin saved report delete prepare failed: ' . $conn->error);
            } else {
                $deleteReportStmt->bind_param('i', $reportId);
                if (!$deleteReportStmt->execute()) {
                    $reportNotice = ['type' => 'error', 'message' => 'Không thể xóa báo cáo.'];
                    error_log('Admin saved report delete failed: ' . $deleteReportStmt->error);
                } elseif ($deleteReportStmt->affected_rows !== 1) {
                    $reportNotice = ['type' => 'error', 'message' => 'Không tìm thấy báo cáo cần xóa.'];
                } else {
                    $deleteReportStmt->close();
                    $_SESSION['reports_notice'] = ['type' => 'success', 'message' => 'Đã xóa báo cáo.'];
                    header('Location: ' . $appBaseUrl . '/admin/reports.php');
                    exit;
                }
                $deleteReportStmt->close();
            }
        }
    }
}

$exportFormat = strtolower((string) ($_GET['export'] ?? ''));
$isExport = in_array($exportFormat, ['xlsx', 'csv'], true);
$reportView = (string) ($_GET['view'] ?? ($isExport ? 'sales' : 'list'));
if (!in_array($reportView, ['list', 'sales', 'customer'], true)) {
    $reportView = $isExport ? 'sales' : 'list';
}
if ($isExport && $reportView === 'list') {
    $reportView = 'sales';
}

$salesSections = [
    'revenue' => 'Doanh thu theo thời gian',
    'category' => 'Doanh thu theo danh mục',
    'products' => 'Sản phẩm bán chạy',
    'orders' => 'Đơn hàng theo trạng thái',
];
$salesSectionTargets = [
    'revenue' => 'sales-revenue-table',
    'category' => 'sales-category-table',
    'products' => 'sales-top-products',
    'orders' => 'sales-order-status',
];
$customerReports = [
    'new' => 'Khách hàng mới theo thời gian',
    'repeat' => 'Khách hàng mua trở lại',
    'revenue' => 'Khách hàng theo doanh thu',
    'orders' => 'Khách hàng theo số đơn hàng',
];
$savedReports = [];
if ($savedReportsAvailable) {
    $savedReportsResult = $conn->query('SELECT id, title, report_type FROM admin_saved_reports ORDER BY created_at, id');
    if (!$savedReportsResult) {
        $reportError = 'Không thể tải danh sách báo cáo đã lưu.';
        error_log('Admin saved reports query failed: ' . $conn->error);
    } else {
        while ($savedReport = $savedReportsResult->fetch_assoc()) {
            $savedReports[] = $savedReport;
        }
    }
}
$savedReportId = filter_var($_GET['report_id'] ?? null, FILTER_VALIDATE_INT);
$currentSavedReport = null;
if ($savedReportId !== false && $savedReportId > 0 && $savedReportsAvailable) {
    $findReportStmt = $conn->prepare('SELECT id, title, report_type FROM admin_saved_reports WHERE id = ?');
    if ($findReportStmt) {
        $findReportStmt->bind_param('i', $savedReportId);
        if ($findReportStmt->execute()) {
            $savedResult = $findReportStmt->get_result();
            if ($savedResult && ($savedRow = $savedResult->fetch_assoc())) {
                $currentSavedReport = $savedRow;
            }
        }
        $findReportStmt->close();
    }
}
$customTitleParam = trim((string) ($_GET['title'] ?? ''));
$activeReportTitle = $currentSavedReport['title'] ?? ($customTitleParam !== '' ? $customTitleParam : null);
$salesSection = (string) ($_GET['section'] ?? 'revenue');
if (!isset($salesSections[$salesSection])) {
    $salesSection = 'revenue';
}

if ($reportView === 'list') {
    $salesReportLinks = [
        ['revenue', $salesSections['revenue'], '#' . $salesSectionTargets['revenue']],
        ['category', $salesSections['category'], '#' . $salesSectionTargets['category']],
        ['products', $salesSections['products'], '#' . $salesSectionTargets['products']],
        ['orders', $salesSections['orders'], '#' . $salesSectionTargets['orders']],
    ];
    $customerReportLinks = [
        ['new', $customerReports['new']],
        ['repeat', $customerReports['repeat']],
        ['revenue', $customerReports['revenue']],
        ['orders', $customerReports['orders']],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Báo cáo và thống kê - ÉCRIN Jewelry</title>
        <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
        <style>
            .report-index-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
            .report-index-search { width: 100%; height: 42px; margin-bottom: 18px; padding: 0 14px; border: 1px solid #dce2e9; border-radius: 8px; background: #fff; color: var(--text); }
            .report-index-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 16px; }
            .report-index-card { overflow: hidden; border: 1px solid #e5e9ef; border-radius: 10px; background: #fff; box-shadow: var(--shadow); }
            .report-index-card h2 { display: flex; align-items: center; gap: 10px; margin: 0; padding: 18px 20px; border-bottom: 1px solid #e9edf2; color: var(--navy); font-size: 16px; }
            .report-index-icon { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 8px; background: #e8f3ff; color: #1685ff; font-size: 17px; }
            .report-index-list { padding: 6px 20px 12px; }
            .report-index-item { display: block; position: relative; padding: 14px 8px 14px 24px; border-bottom: 1px dashed #e4e8ed; color: #23364b; transition: color .15s, background .15s; }
            .report-index-item:last-child { border-bottom: 0; }
            .report-index-item::before { position: absolute; top: 21px; left: 8px; width: 6px; height: 6px; border-radius: 50%; background: #a4adb8; content: ''; }
            .report-index-item:hover, .report-index-item:focus-visible { border-radius: 6px; background: #f4f8fc; color: #087fe8; outline: none; }
            .report-index-item strong { display: block; font-size: 13px; font-weight: 500; }
            .report-saved-item { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px dashed #e4e8ed; }
            .report-saved-item:last-child { border-bottom: 0; }
            .report-saved-link { flex: 1; min-width: 0; color: #23364b; }
            .report-saved-link:hover { color: #087fe8; }
            .report-saved-link strong { display: block; overflow: hidden; font-size: 13px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
            .report-saved-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 5px; }
            .report-row-action { display: inline-grid; width: 30px; height: 30px; place-items: center; border: 0; border-radius: 5px; background: transparent; color: #8793a2; cursor: pointer; font-size: 16px; }
            .report-row-action:hover { background: #eef5fc; color: #087fe8; }
            .report-row-action svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
            .report-row-delete { display: inline-flex; margin: 0; }
            .report-index-heading .report-add-button { min-height: 40px; padding: 0 15px; border: 0; border-radius: 7px; background: #0784f9; color: #fff; font-weight: 600; cursor: pointer; }
            .report-index-heading .report-add-button:hover { background: #006fd6; }
            .report-notice { margin: 0 0 16px; padding: 11px 14px; border: 1px solid #cfe7d7; border-radius: 7px; background: #f0faf3; color: #28643a; }
            .report-notice.error { border-color: #f3c4c4; background: #fff2f2; color: #9c2f2f; }
            .report-modal[hidden] { display: none; }
            .report-modal { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .report-modal-card { width: min(100%, 560px); overflow: hidden; border-radius: 9px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .report-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 17px 24px; border-bottom: 1px solid #e6e9ed; }
            .report-modal-header h2 { margin: 0; color: #17283d; font-size: 21px; }
            .report-modal-close { border: 0; background: transparent; color: #98a2af; cursor: pointer; font-size: 25px; line-height: 1; }
            .report-modal-body { padding: 22px 24px 24px; }
            .report-modal-label { display: block; margin-bottom: 8px; color: #4b5a6b; font-size: 13px; }
            .report-modal-label b { color: #e34747; }
            .report-title-wrap { position: relative; }
            .report-title-input { width: 100%; height: 44px; padding: 0 60px 0 12px; border: 1px solid #cfd5dc; border-radius: 7px; color: #17283d; }
            .report-title-count { position: absolute; top: 14px; right: 12px; color: #8995a4; font-size: 12px; }
            .report-type-label { display: block; margin: 21px 0 10px; color: #4b5a6b; font-size: 13px; }
            .report-type-options { display: flex; flex-wrap: wrap; gap: 14px; }
            .report-type-option { position: relative; width: 200px; min-height: 125px; padding: 15px; border: 1px solid #d5dbe2; border-radius: 8px; cursor: pointer; text-align: center; }
            .report-type-option:has(input:checked) { border-color: #0784f9; background: #f1f8ff; color: #0784f9; }
            .report-type-option input { position: absolute; width: 1px; height: 1px; opacity: 0; }
            .report-type-option .type-icon { display: block; margin-bottom: 5px; color: #0784f9; font-size: 24px; }
            .report-type-option strong, .report-type-option small { display: block; }
            .report-type-option strong { font-size: 15px; }
            .report-type-option small { margin-top: 5px; color: #64758a; font-size: 12px; line-height: 1.4; }
            .report-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 15px 20px; border-top: 1px solid #e6e9ed; }
            .report-modal-footer button { min-width: 82px; height: 39px; padding: 0 15px; border: 1px solid #0784f9; border-radius: 7px; background: #fff; color: #0784f9; cursor: pointer; font-weight: 600; }
            .report-modal-footer button[type=submit] { background: #0784f9; color: #fff; }
            .report-modal-footer button[type=submit]:hover { background: #006fd6; }
            .report-index-empty { display: none; grid-column: 1 / -1; padding: 24px; color: var(--muted); text-align: center; }
            .report-export-modal { position: fixed; inset: 0; z-index: 99999; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .report-export-card { width: min(100%, 480px); overflow: hidden; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .report-export-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 12px; }
            .report-export-header h2 { margin: 0; color: #111827; font-size: 20px; font-weight: 700; }
            .report-export-close { border: 0; background: transparent; color: #9ca3af; cursor: pointer; font-size: 24px; line-height: 1; padding: 4px; }
            .report-export-close:hover { color: #4b5563; }
            .report-export-body { padding: 10px 24px 28px; color: #1f2937; font-size: 15px; line-height: 1.5; }
            .report-export-body p { margin: 0; }
            .report-export-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding: 0 24px 24px; }
            .report-export-footer button, .report-export-footer a { display: inline-flex; min-width: 82px; height: 38px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; box-sizing: border-box; transition: all .15s ease; }
            .report-export-footer .report-export-cancel { border: 1px solid #0084ff; background: #fff; color: #0084ff; }
            .report-export-footer .report-export-cancel:hover { background: #f0f7ff; }
            .report-export-footer .report-export-confirm { border: 1px solid #0084ff; background: #0084ff; color: #fff; }
            .report-export-footer .report-export-confirm:hover { background: #0070d8; border-color: #0070d8; }
            .report-index-export-btn { min-height: 40px; padding: 0 16px; border: 1px solid #0784f9; border-radius: 7px; background: #fff; color: #0784f9; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; transition: all .15s ease; }
            .report-index-export-btn:hover { background: #f0f7ff; border-color: #006fd6; color: #006fd6; }
            @media (max-width: 850px) { .report-index-grid { grid-template-columns: 1fr; } }
            @media (max-width: 560px) { .report-index-heading { align-items: flex-start; flex-direction: column; } .report-index-card h2 { padding: 15px; } .report-index-list { padding-right: 12px; padding-left: 12px; } .report-modal-header { padding: 15px; } .report-modal-body { padding: 18px 15px; } .report-type-option { width: calc(50% - 7px); min-width: 130px; } }
        </style>
    </head>
    <body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb">Trang chủ <span>/</span> <strong>Báo cáo và thống kê</strong></div>
                <div class="admin-profile">
                    <div class="avatar">QL</div>
                    <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
                </div>
            </header>
            <section class="page-content">
                <div class="report-index-heading">
                    <h1>Danh sách báo cáo</h1>
                    <button class="report-add-button" type="button" id="report-add">⊕ Thêm mới báo cáo</button>
                </div>
                <?php if (is_array($reportNotice) && ($reportNotice['message'] ?? '') !== ''): ?>
                    <p class="report-notice<?php echo ($reportNotice['type'] ?? '') === 'error' ? ' error' : ''; ?>" role="status"><?php echo $escape($reportNotice['message']); ?></p>
                <?php endif; ?>
                <?php if ($reportError !== ''): ?><p class="report-notice error" role="alert"><?php echo $escape($reportError); ?></p><?php endif; ?>
                <input type="search" class="report-index-search" id="report-search" placeholder="Tìm kiếm tên báo cáo" aria-label="Tìm kiếm tên báo cáo">
                <div class="report-index-grid" id="report-list">
                    <section class="report-index-card" aria-labelledby="sales-reports-heading">
                        <h2 id="sales-reports-heading"><span class="report-index-icon" aria-hidden="true">$</span>BÁO CÁO BÁN HÀNG</h2>
                        <div class="report-index-list">
                            <?php foreach ($salesReportLinks as [$section, $title, $anchor]): ?>
                                <a class="report-index-item" data-report-name="<?php echo $escape($title); ?>" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . http_build_query(['view' => 'sales', 'section' => $section]) . $anchor); ?>">
                                    <strong><?php echo $escape($title); ?></strong>
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($savedReports as $savedReport): ?>
                                <?php if ($savedReport['report_type'] !== 'sales') continue; ?>
                                <div class="report-saved-item">
                                    <a class="report-saved-link" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . http_build_query(['view' => 'sales', 'section' => 'revenue', 'report_id' => (int) $savedReport['id']])); ?>">
                                        <strong><?php echo $escape($savedReport['title']); ?></strong>
                                    </a>
                                    <div class="report-saved-actions">
                                        <button class="report-row-action report-edit" type="button" data-id="<?php echo (int) $savedReport['id']; ?>" data-title="<?php echo $escape($savedReport['title']); ?>" data-type="sales" aria-label="Sửa <?php echo $escape($savedReport['title']); ?>" title="Sửa">✎</button>
                                        <form method="post" class="report-row-delete" onsubmit="return confirm('Bạn có chắc muốn xóa báo cáo này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                                            <input type="hidden" name="action" value="delete_saved_report">
                                            <input type="hidden" name="report_id" value="<?php echo (int) $savedReport['id']; ?>">
                                            <button class="report-row-action" type="submit" aria-label="Xóa <?php echo $escape($savedReport['title']); ?>" title="Xóa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path></svg></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section class="report-index-card" aria-labelledby="customer-reports-heading">
                        <h2 id="customer-reports-heading"><span class="report-index-icon" aria-hidden="true">♙</span>BÁO CÁO KHÁCH HÀNG</h2>
                        <div class="report-index-list">
                            <?php foreach ($customerReportLinks as [$report, $title]): ?>
                                <a class="report-index-item" data-report-name="<?php echo $escape($title); ?>" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . http_build_query(['view' => 'customer', 'customer_report' => $report])); ?>">
                                    <strong><?php echo $escape($title); ?></strong>
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($savedReports as $savedReport): ?>
                                <?php if ($savedReport['report_type'] !== 'customer') continue; ?>
                                <div class="report-saved-item">
                                    <a class="report-saved-link" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . http_build_query(['view' => 'customer', 'customer_report' => 'revenue', 'report_id' => (int) $savedReport['id']])); ?>">
                                        <strong><?php echo $escape($savedReport['title']); ?></strong>
                                    </a>
                                    <div class="report-saved-actions">
                                        <button class="report-row-action report-edit" type="button" data-id="<?php echo (int) $savedReport['id']; ?>" data-title="<?php echo $escape($savedReport['title']); ?>" data-type="customer" aria-label="Sửa <?php echo $escape($savedReport['title']); ?>" title="Sửa">✎</button>
                                        <form method="post" class="report-row-delete" onsubmit="return confirm('Bạn có chắc muốn xóa báo cáo này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                                            <input type="hidden" name="action" value="delete_saved_report">
                                            <input type="hidden" name="report_id" value="<?php echo (int) $savedReport['id']; ?>">
                                            <button class="report-row-action" type="submit" aria-label="Xóa <?php echo $escape($savedReport['title']); ?>" title="Xóa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path></svg></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <p class="report-index-empty" id="report-search-empty">Không tìm thấy báo cáo phù hợp.</p>
                </div>
            </section>
        </main>
    </div>
    <div class="report-modal" id="report-modal" hidden>
        <form class="report-modal-card" method="post" id="report-form">
            <div class="report-modal-header">
                <h2 id="report-modal-heading">Thêm mới báo cáo</h2>
                <button class="report-modal-close" type="button" aria-label="Đóng">×</button>
            </div>
            <div class="report-modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                <input type="hidden" name="action" id="report-action" value="create_saved_report">
                <input type="hidden" name="report_id" id="report-id" value="">
                <label class="report-modal-label" for="report-title">Tên báo cáo<b>*</b></label>
                <div class="report-title-wrap">
                    <input class="report-title-input" type="text" name="title" id="report-title" maxlength="250" required>
                    <span class="report-title-count" id="report-title-count">0/250</span>
                </div>
                <span class="report-type-label">Loại báo cáo</span>
                <div class="report-type-options">
                    <label class="report-type-option">
                        <input type="radio" name="report_type" value="sales" checked>
                        <span class="type-icon" aria-hidden="true">$</span>
                        <strong>Bán hàng</strong>
                        <small>Báo cáo doanh thu theo thời gian</small>
                    </label>
                    <label class="report-type-option">
                        <input type="radio" name="report_type" value="customer">
                        <span class="type-icon" aria-hidden="true">♙</span>
                        <strong>Khách hàng</strong>
                        <small>Báo cáo khách hàng theo giá trị đơn hàng</small>
                    </label>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-modal-cancel">Hủy</button>
                <button type="submit" id="report-submit">Thêm mới</button>
            </div>
        </form>
    </div>
    <div class="report-export-modal" id="list-export-modal" style="display:none">
        <section class="report-export-card" role="dialog" aria-modal="true" aria-labelledby="list-export-heading" tabindex="-1">
            <header class="report-export-header">
                <h2 id="list-export-heading">Xuất báo cáo</h2>
                <button class="report-export-close" type="button" aria-label="Đóng" id="list-export-close">×</button>
            </header>
            <div class="report-export-body">
                <p>Bạn có muốn xuất báo cáo? Báo cáo này sẽ được xuất ra file excel</p>
            </div>
            <footer class="report-export-footer">
                <button type="button" class="report-export-cancel" id="list-export-cancel">Hủy</button>
                <a class="report-export-confirm" id="list-export-confirm" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?view=sales&section=revenue&export=xlsx'); ?>" onclick="setTimeout(() => { document.getElementById('list-export-modal').style.display='none'; }, 200);">Xuất báo cáo</a>
            </footer>
        </section>
    </div>
    <script>
        const reportSearch = document.getElementById('report-search');
        const reportItems = Array.from(document.querySelectorAll('.report-index-item, .report-saved-item'));
        const emptyMessage = document.getElementById('report-search-empty');
        reportSearch.addEventListener('input', () => {
            const query = reportSearch.value.trim().toLocaleLowerCase('vi');
            let visibleCount = 0;
            reportItems.forEach((item) => {
                const visible = item.textContent.toLocaleLowerCase('vi').includes(query);
                item.hidden = !visible;
                if (visible) visibleCount++;
            });
            document.querySelectorAll('.report-index-card').forEach((card) => {
                card.hidden = !card.querySelector('.report-index-item:not([hidden]), .report-saved-item:not([hidden])');
            });
            emptyMessage.style.display = visibleCount ? 'none' : 'block';
        });

        const reportModal = document.getElementById('report-modal');
        const reportForm = document.getElementById('report-form');
        const reportTitle = document.getElementById('report-title');
        const reportTitleCount = document.getElementById('report-title-count');
        const reportAction = document.getElementById('report-action');
        const reportModalHeading = document.getElementById('report-modal-heading');
        const reportSubmit = document.getElementById('report-submit');
        const reportId = document.getElementById('report-id');
        const reportTypeInputs = Array.from(reportForm.querySelectorAll('input[name="report_type"]'));
        const closeReportModal = () => {
            reportModal.hidden = true;
            document.body.style.overflow = '';
        };
        const openReportModal = (mode, report = null) => {
            const isEdit = mode === 'edit';
            reportAction.value = isEdit ? 'update_saved_report' : 'create_saved_report';
            reportId.value = isEdit ? report.id : '';
            reportModalHeading.textContent = isEdit ? 'Sửa tên báo cáo' : 'Thêm mới báo cáo';
            reportSubmit.textContent = isEdit ? 'Lưu' : 'Thêm mới';
            reportTitle.value = isEdit ? report.title : '';
            reportTypeInputs.forEach((input) => { input.checked = input.value === (isEdit ? report.type : 'sales'); });
            reportTitleCount.textContent = `${reportTitle.value.length}/250`;
            reportModal.hidden = false;
            document.body.style.overflow = 'hidden';
            reportTitle.focus();
        };
        document.getElementById('report-add').addEventListener('click', () => openReportModal('create'));
        document.querySelectorAll('.report-edit').forEach((button) => {
            button.addEventListener('click', () => openReportModal('edit', {
                id: button.dataset.id,
                title: button.dataset.title,
                type: button.dataset.type
            }));
        });
        reportTitle.addEventListener('input', () => { reportTitleCount.textContent = `${reportTitle.value.length}/250`; });
        reportModal.querySelectorAll('.report-modal-close, .report-modal-cancel').forEach((button) => {
            button.addEventListener('click', closeReportModal);
        });
        reportModal.addEventListener('click', (event) => {
            if (event.target === reportModal) closeReportModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !reportModal.hidden) closeReportModal();
        });

        const listExportModal = document.getElementById('list-export-modal');
        const listExportOpen = document.getElementById('list-export-open');
        const listExportClose = document.getElementById('list-export-close');
        const listExportCancel = document.getElementById('list-export-cancel');

        if (listExportOpen && listExportModal) {
            listExportOpen.addEventListener('click', () => {
                listExportModal.style.display = 'grid';
            });
            const closeListExport = () => { listExportModal.style.display = 'none'; };
            if (listExportClose) listExportClose.addEventListener('click', closeListExport);
            if (listExportCancel) listExportCancel.addEventListener('click', closeListExport);
            listExportModal.addEventListener('click', (e) => {
                if (e.target === listExportModal) closeListExport();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && listExportModal.style.display !== 'none') closeListExport();
            });
        }
    </script>
    </body>
    </html>
    <?php
    exit;
}

if ($reportView === 'customer') {
    $customerReport = (string) ($_GET['customer_report'] ?? 'new');
    if (!isset($customerReports[$customerReport])) {
        $customerReport = 'new';
    }

    $today = new DateTimeImmutable('today');
    $period = (string) ($_GET['period'] ?? 'this_month');
    $validPeriods = ['today', 'yesterday', 'last_7', 'this_month', 'last_month', 'last_30', 'custom'];
    if (!in_array($period, $validPeriods, true)) {
        $period = 'this_month';
    }
    $startInput = (string) ($_GET['from'] ?? '');
    $endInput = (string) ($_GET['to'] ?? '');
    $dateFrom = $today->modify('first day of this month');
    $dateTo = $today;
    $customerError = '';
    if ($period === 'today') {
        $dateFrom = $today;
        $dateTo = $today;
    } elseif ($period === 'yesterday') {
        $dateFrom = $today->modify('-1 day');
        $dateTo = $dateFrom;
    } elseif ($period === 'last_7') {
        $dateFrom = $today->modify('-6 days');
    } elseif ($period === 'last_month') {
        $dateFrom = $today->modify('first day of last month');
        $dateTo = $today->modify('last day of last month');
    } elseif ($period === 'last_30') {
        $dateFrom = $today->modify('-29 days');
    } elseif ($period === 'custom') {
        $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
        $parsedEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
        if ($parsedStart && $parsedEnd && $parsedStart->format('Y-m-d') === $startInput
            && $parsedEnd->format('Y-m-d') === $endInput && $parsedStart <= $parsedEnd) {
            $dateFrom = $parsedStart;
            $dateTo = $parsedEnd;
        } else {
            $period = 'this_month';
            $customerError = 'Khoảng thời gian không hợp lệ. Vui lòng chọn ngày bắt đầu và kết thúc hợp lệ.';
        }
    }
    $startDate = $dateFrom->format('Y-m-d');
    $endDate = $dateTo->format('Y-m-d');
    $endExclusive = $dateTo->modify('+1 day')->format('Y-m-d');
    $customerRows = [];
    $customerSql = "SELECT u.id, u.username, u.phone, u.created_at,
                           COUNT(DISTINCT o.id) AS order_count,
                           COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng','Đã giao') THEN o.total_price ELSE 0 END),0) AS revenue,
                           MAX(o.created_at) AS last_order
                    FROM users u
                    LEFT JOIN orders o ON o.user_id = u.id
                      AND o.created_at >= ? AND o.created_at < ?
                      AND o.status NOT IN ('Đã hủy','Canceled','cancelled','huy')";
    if ($customerReport === 'new') {
        $customerSql .= ' WHERE u.created_at >= ? AND u.created_at < ?';
    }
    $customerSql .= ' GROUP BY u.id, u.username, u.phone, u.created_at';
    if ($customerReport === 'repeat') {
        $customerSql .= ' HAVING COUNT(DISTINCT o.id) >= 2';
    } elseif ($customerReport !== 'new') {
        $customerSql .= ' HAVING COUNT(DISTINCT o.id) > 0';
    }
    if ($customerReport === 'revenue') {
        $customerSql .= ' ORDER BY revenue DESC, order_count DESC';
    } elseif ($customerReport === 'orders' || $customerReport === 'repeat') {
        $customerSql .= ' ORDER BY order_count DESC, revenue DESC';
    } else {
        $customerSql .= ' ORDER BY u.created_at DESC';
    }
    $customerSql .= ' LIMIT 100';

    $customerStmt = $conn->prepare($customerSql);
    if (!$customerStmt) {
        $customerError = 'Không thể tải báo cáo khách hàng.';
        error_log('Admin customer report prepare failed: ' . $conn->error);
    } else {
        if ($customerReport === 'new') {
            $customerStmt->bind_param('ssss', $startDate, $endExclusive, $startDate, $endExclusive);
        } else {
            $customerStmt->bind_param('ss', $startDate, $endExclusive);
        }
        if (!$customerStmt->execute()) {
            $customerError = 'Không thể tải báo cáo khách hàng.';
            error_log('Admin customer report query failed: ' . $customerStmt->error);
        } else {
            $customerResult = $customerStmt->get_result();
            if ($customerResult) {
                while ($row = $customerResult->fetch_assoc()) {
                    $customerRows[] = $row;
                }
            }
        }
        $customerStmt->close();
    }
    $customerTitle = $activeReportTitle ?: ($customerReports[$customerReport] ?? 'Báo cáo khách hàng');
    $customerRange = $startDate . ' - ' . $endDate;
    $customerDetailHeaders = $customerReport === 'new'
        ? ['Tên tài khoản', 'Số điện thoại', 'Ngày đăng ký', 'Đơn trong kỳ']
        : ['Tên tài khoản', 'Số điện thoại', 'Số đơn trong kỳ', 'Doanh thu đã giao', 'Đơn gần nhất'];
    $customerDetailRows = [];
    foreach ($customerRows as $customer) {
        if ($customerReport === 'new') {
            $customerDetailRows[] = [
                (string) $customer['username'],
                (string) ($customer['phone'] ?: '—'),
                date('d/m/Y H:i', strtotime($customer['created_at'])),
                number_format((int) $customer['order_count']),
            ];
        } else {
            $customerDetailRows[] = [
                (string) $customer['username'],
                (string) ($customer['phone'] ?: '—'),
                number_format((int) $customer['order_count']),
                number_format((float) $customer['revenue'], 0, ',', '.') . ' đ',
                $customer['last_order'] ? date('d/m/Y H:i', strtotime($customer['last_order'])) : '—',
            ];
        }
    }

    if ($isExport && $customerError === '') {
        $excelRows = [
            ['BÁO CÁO KHÁCH HÀNG - ÉCRIN JEWELRY'],
            ['Loại báo cáo', $customerTitle],
            ['Khoảng thời gian', $customerRange],
            ['Thời gian xuất', date('d/m/Y H:i')],
            [],
            $customerDetailHeaders,
        ];
        foreach ($customerRows as $customer) {
            if ($customerReport === 'new') {
                $excelRows[] = [
                    (string) $customer['username'],
                    (string) ($customer['phone'] ?: '—'),
                    date('d/m/Y H:i', strtotime($customer['created_at'])),
                    (int) $customer['order_count'],
                ];
            } else {
                $excelRows[] = [
                    (string) $customer['username'],
                    (string) ($customer['phone'] ?: '—'),
                    (int) $customer['order_count'],
                    (float) $customer['revenue'],
                    $customer['last_order'] ? date('d/m/Y H:i', strtotime($customer['last_order'])) : '—',
                ];
            }
        }
        $filename = 'bao-cao-khach-hang-' . $customerReport . '-' . $startDate . '-den-' . $endDate;
        if ($exportFormat === 'csv') {
            $sendCsvFile($filename . '.csv', $excelRows);
        } else {
            $sendExcelFile($filename . '.xlsx', $excelRows);
        }
    }

    $customerExportParams = [
        'view' => 'customer',
        'customer_report' => $customerReport,
        'period' => $period,
        'from' => $startDate,
        'to' => $endDate,
        'export' => 'xlsx',
    ];
    if ($savedReportId) {
        $customerExportParams['report_id'] = $savedReportId;
    }
    $customerExportQueryXlsx = http_build_query($customerExportParams);
    $customerExportQueryCsv = http_build_query([
        'view' => 'customer',
        'customer_report' => $customerReport,
        'period' => $period,
        'from' => $startDate,
        'to' => $endDate,
        'export' => 'csv',
    ]);
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $escape($customerTitle); ?> - ÉCRIN Jewelry</title>
        <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
        <style>
            .customer-report-panel { margin-bottom: 18px; padding: 20px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
            .customer-table-wrap { overflow-x: auto; }
            .customer-table { width: 100%; border-collapse: collapse; }
            .customer-table th, .customer-table td { border-bottom: 1px solid #eef1f5; text-align: left; }
            .customer-table td:first-child { font-weight: 600; }
            .customer-detail-heading .report-back-button { display: inline-grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #718198; font-size: 20px; }
            .customer-detail-heading .report-back-button:hover { color: #0784f9; }
            .customer-detail-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
            .customer-detail-heading h1 { font-size: 23px; }
            .customer-detail-actions { display: flex; align-items: center; gap: 18px; margin-left: auto; }
            .customer-detail-actions a, .customer-detail-actions button { padding: 0; border: 0; background: transparent; color: #34465b; cursor: pointer; font-size: 13px; font-weight: 600; }
            .customer-filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; }
            .customer-filterbar > select, .customer-filterbar > input { min-height: 39px; padding: 8px 10px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: var(--text); }
            .customer-filterbar > select { min-width: 150px; }
            .customer-filterbar > input { width: 145px; }
            .customer-filterbar .report-button { min-height: 39px; padding: 0 14px; border: 1px solid var(--navy); border-radius: 7px; background: var(--navy); color: #fff; font-weight: 600; cursor: pointer; }
            .customer-filterbar .report-button:hover { background: var(--navy-soft); color: #fff; }
            .customer-compare-label { display: inline-flex; min-height: 39px; align-items: center; padding: 0 12px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #526276; font-size: 12px; }
            .customer-table-panel { overflow: hidden; padding: 18px 0 0; }
            .customer-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 18px 14px; }
            .customer-table th { padding: 12px 14px; background: #f2f4f6; color: #46576a; font-size: 12px; white-space: nowrap; }
            .customer-table td { padding: 12px 14px; font-size: 13px; white-space: nowrap; }
            .customer-table tbody tr:nth-child(even) { background: #f3f5f7; }
            .customer-table tbody tr[hidden] { display: none; }
            .customer-table-footer { display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 12px 18px; color: #526276; font-size: 12px; }
            .customer-table-footer select, .customer-table-footer button { min-height: 32px; border: 1px solid #dce2e9; border-radius: 5px; background: #fff; color: #526276; }
            .customer-table-footer button { min-width: 32px; cursor: pointer; }
            .customer-table-footer button:disabled { opacity: .4; cursor: default; }
            .customer-copy-status { color: #16835d; font-size: 12px; }
            .customer-empty { padding: 18px 0; color: var(--muted); text-align: center; }
            .customer-error { margin-bottom: 16px; padding: 12px 15px; border: 1px solid #f3c4c4; border-radius: 8px; background: #fff2f2; color: #9c2f2f; }
            .customer-report-panel .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; clip-path: inset(50%); }
            .report-export-modal { position: fixed; inset: 0; z-index: 99999; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .report-export-card { width: min(100%, 480px); overflow: hidden; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .report-export-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 12px; }
            .report-export-header h2 { margin: 0; color: #111827; font-size: 20px; font-weight: 700; }
            .report-export-close { border: 0; background: transparent; color: #9ca3af; cursor: pointer; font-size: 24px; line-height: 1; padding: 4px; }
            .report-export-close:hover { color: #4b5563; }
            .report-export-body { padding: 10px 24px 28px; color: #1f2937; font-size: 15px; line-height: 1.5; }
            .report-export-body p { margin: 0; }
            .report-export-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding: 0 24px 24px; }
            .report-export-footer button, .report-export-footer a { display: inline-flex; min-width: 82px; height: 38px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; box-sizing: border-box; transition: all .15s ease; }
            .report-export-footer .report-export-cancel { border: 1px solid #0084ff; background: #fff; color: #0084ff; }
            .report-export-footer .report-export-cancel:hover { background: #f0f7ff; }
            .report-export-footer .report-export-confirm { border: 1px solid #0084ff; background: #0084ff; color: #fff; }
            .report-export-footer .report-export-confirm:hover { background: #0070d8; border-color: #0070d8; }
            @media (max-width: 650px) { .customer-detail-heading { flex-wrap: wrap; } .customer-detail-actions { width: 100%; justify-content: flex-end; } .customer-filterbar > select, .customer-filterbar > input { width: 100%; } .customer-table-footer { justify-content: flex-start; flex-wrap: wrap; } }
        </style>
    </head>
    <body>
    <div class="admin-layout">
        <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb">Trang chủ <span>/</span> <a href="<?php echo $escape($appBaseUrl . '/admin/reports.php'); ?>">Báo cáo</a> <span>/</span> <strong><?php echo $escape($customerTitle); ?></strong></div>
                <div class="admin-profile">
                    <div class="avatar">QL</div>
                    <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
                </div>
            </header>
            <section class="page-content">
                <div class="customer-detail-heading">
                    <a class="report-back-button" href="<?php echo $escape($appBaseUrl . '/admin/reports.php'); ?>" aria-label="Quay lại danh sách báo cáo">←</a>
                    <h1><?php echo $escape($customerTitle); ?></h1>
                    <div class="customer-detail-actions">
                        <?php if ($customerError === ''): ?><button type="button" onclick="document.getElementById('export-confirmation').style.display='grid'">⇧ Xuất báo cáo</button><?php endif; ?>
                        <button type="button" id="copy-customer-report">▢ Sao chép</button>
                    </div>
                </div>
                <?php if ($customerError !== ''): ?><p class="customer-error" role="alert"><?php echo $escape($customerError); ?></p><?php endif; ?>
                <form method="get" class="customer-filterbar">
                        <input type="hidden" name="view" value="customer">
                        <input type="hidden" name="customer_report" value="<?php echo $escape($customerReport); ?>">
                        <?php if ($savedReportId): ?><input type="hidden" name="report_id" value="<?php echo (int) $savedReportId; ?>"><?php endif; ?>
                        <span class="customer-compare-label">Theo thời gian</span>
                        <label class="sr-only" for="customer-period">Khoảng thời gian</label>
                        <select name="period" id="customer-period">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                            <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                            <option value="last_7" <?php echo $period === 'last_7' ? 'selected' : ''; ?>>7 ngày qua (<?php echo $escape($customerRange); ?>)</option>
                            <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                            <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>30 ngày gần nhất</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn ngày</option>
                        </select>
                        <span class="customer-compare-label">Dữ liệu từ <?php echo $escape($customerRange); ?></span>
                        <label class="sr-only" for="customer-from">Từ ngày</label>
                        <input type="date" name="from" id="customer-from" value="<?php echo $escape($startDate); ?>">
                        <label class="sr-only" for="customer-to">Đến ngày</label>
                        <input type="date" name="to" id="customer-to" value="<?php echo $escape($endDate); ?>">
                        <button type="submit" class="report-button">Áp dụng</button>
                    </form>
                <span class="customer-copy-status" id="customer-copy-status" role="status"></span>
                <section class="customer-report-panel customer-table-panel">
                    <div class="customer-table-toolbar">
                        <strong><?php echo $escape($customerTitle); ?></strong>
                    </div>
                    <?php if ($customerDetailRows === []): ?>
                        <p class="customer-empty">Không có dữ liệu khách hàng trong khoảng thời gian này.</p>
                    <?php else: ?>
                        <div class="customer-table-wrap">
                            <table class="customer-table" id="customer-data-table">
                                <thead><tr><?php foreach ($customerDetailHeaders as $header): ?><th><?php echo $escape($header); ?> <span aria-hidden="true">↕</span></th><?php endforeach; ?></tr></thead>
                                <tbody>
                                    <?php foreach ($customerDetailRows as $row): ?>
                                        <tr><?php foreach ($row as $cell): ?><td><?php echo $escape($cell); ?></td><?php endforeach; ?></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="customer-table-footer">
                            <span id="customer-table-range"></span>
                            <label for="customer-page-size">Hiển thị</label>
                            <select id="customer-page-size"><option>20</option><option selected>50</option><option>100</option></select>
                            <span>Kết quả</span>
                            <button type="button" id="customer-page-prev" aria-label="Trang trước">‹</button>
                            <span id="customer-page-label"></span>
                            <button type="button" id="customer-page-next" aria-label="Trang sau">›</button>
                        </div>
                    <?php endif; ?>
                </section>
            </section>
        </main>
    </div>
    <?php if ($customerError === ''): ?>
        <div class="report-export-modal" id="export-confirmation" style="display:none">
            <section class="report-export-card" role="dialog" aria-modal="true" aria-labelledby="export-confirmation-title" tabindex="-1">
                <header class="report-export-header">
                    <h2 id="export-confirmation-title">Xuất báo cáo</h2>
                    <button class="report-export-close" type="button" aria-label="Đóng" onclick="document.getElementById('export-confirmation').style.display='none'">×</button>
                </header>
                <div class="report-export-body">
                    <p>Bạn có muốn xuất báo cáo? Báo cáo này sẽ được xuất ra file excel</p>
                </div>
                <footer class="report-export-footer">
                    <button type="button" class="report-export-cancel" onclick="document.getElementById('export-confirmation').style.display='none'">Hủy</button>
                    <a class="report-export-confirm" id="customer-export-xlsx" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . $customerExportQueryXlsx); ?>" onclick="setTimeout(() => { document.getElementById('export-confirmation').style.display='none'; }, 200);">Xuất báo cáo</a>
                </footer>
            </section>
        </div>
    <?php endif; ?>
    <script>
        const customerExportModal = document.getElementById('export-confirmation');
        if (customerExportModal) {
            customerExportModal.addEventListener('click', (e) => {
                if (e.target === customerExportModal) customerExportModal.style.display = 'none';
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && customerExportModal.style.display !== 'none') {
                    customerExportModal.style.display = 'none';
                }
            });
        }
        const syncCustomerExportLinks = () => {
            const periodVal = document.getElementById('customer-period')?.value || 'this_month';
            const fromVal = document.getElementById('customer-from')?.value || '';
            const toVal = document.getElementById('customer-to')?.value || '';
            const xlsxBtn = document.getElementById('customer-export-xlsx');
            if (xlsxBtn) {
                const u = new URL(xlsxBtn.href, window.location.origin);
                if (periodVal) u.searchParams.set('period', periodVal);
                if (fromVal) u.searchParams.set('from', fromVal);
                if (toVal) u.searchParams.set('to', toVal);
                xlsxBtn.href = u.pathname + u.search;
            }
        };

        const customerPeriod = document.getElementById('customer-period');
        customerPeriod.addEventListener('change', () => {
            syncCustomerExportLinks();
            if (customerPeriod.value === 'custom') return;
            customerPeriod.form.requestSubmit();
        });
        document.getElementById('customer-from').addEventListener('change', () => {
            customerPeriod.value = 'custom';
            syncCustomerExportLinks();
        });
        document.getElementById('customer-to').addEventListener('change', () => {
            customerPeriod.value = 'custom';
            syncCustomerExportLinks();
        });
        const customerTable = document.getElementById('customer-data-table');
        if (customerTable) {
            const rows = Array.from(customerTable.tBodies[0].rows);
            const pageSize = document.getElementById('customer-page-size');
            const range = document.getElementById('customer-table-range');
            const pageLabel = document.getElementById('customer-page-label');
            const previous = document.getElementById('customer-page-prev');
            const next = document.getElementById('customer-page-next');
            let page = 0;
            const renderCustomerPage = () => {
                const size = Number(pageSize.value);
                const pages = Math.max(1, Math.ceil(rows.length / size));
                page = Math.min(page, pages - 1);
                const start = page * size;
                const end = Math.min(start + size, rows.length);
                const visible = new Set(rows.slice(start, end));
                rows.forEach((row) => { row.hidden = !visible.has(row); });
                range.textContent = `Từ ${start + 1} đến ${end} trên tổng ${rows.length}`;
                pageLabel.textContent = `${page + 1}/${pages}`;
                previous.disabled = page === 0;
                next.disabled = page >= pages - 1;
            };
            pageSize.addEventListener('change', () => { page = 0; renderCustomerPage(); });
            previous.addEventListener('click', () => { page--; renderCustomerPage(); });
            next.addEventListener('click', () => { page++; renderCustomerPage(); });
            renderCustomerPage();
        }
        document.getElementById('copy-customer-report').addEventListener('click', async () => {
            const table = document.getElementById('customer-data-table');
            const status = document.getElementById('customer-copy-status');
            if (!table) {
                status.textContent = 'Không có dữ liệu để sao chép.';
                return;
            }
            const text = Array.from(table.rows).map((row) => Array.from(row.cells).map((cell) => cell.textContent.trim()).join('\t')).join('\n');
            try {
                await navigator.clipboard.writeText(text);
                status.textContent = 'Đã sao chép báo cáo.';
            } catch (error) {
                status.textContent = 'Không thể sao chép. Vui lòng kiểm tra quyền clipboard của trình duyệt.';
            }
        });
    </script>
    </body>
    </html>
    <?php
    exit;
}

$categories = [];
$categoryResult = $conn->query('SELECT id, name FROM categories ORDER BY name');
if ($categoryResult) {
    while ($row = $categoryResult->fetch_assoc()) {
        $categories[(int) $row['id']] = $row['name'];
    }
} else {
    $reportError = 'Không thể tải danh mục sản phẩm.';
    error_log('Admin report categories failed: ' . $conn->error);
}

$today = new DateTimeImmutable('today');
$period = (string) ($_GET['period'] ?? 'this_month');
$validPeriods = ['today', 'yesterday', 'last_7', 'this_month', 'last_month', 'last_30', 'custom'];
if (!in_array($period, $validPeriods, true)) {
    $period = 'this_month';
}

$startInput = (string) ($_GET['from'] ?? '');
$endInput = (string) ($_GET['to'] ?? '');
$dateFrom = $today->modify('first day of this month');
$dateTo = $today;

if ($period === 'today') {
    $dateFrom = $today;
    $dateTo = $today;
} elseif ($period === 'yesterday') {
    $dateFrom = $today->modify('-1 day');
    $dateTo = $dateFrom;
} elseif ($period === 'last_7') {
    $dateFrom = $today->modify('-6 days');
} elseif ($period === 'last_month') {
    $dateFrom = $today->modify('first day of last month');
    $dateTo = $today->modify('last day of last month');
} elseif ($period === 'last_30') {
    $dateFrom = $today->modify('-29 days');
} elseif ($period === 'custom') {
    $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
    $parsedEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
    if (!$parsedStart || !$parsedEnd || $parsedStart->format('Y-m-d') !== $startInput || $parsedEnd->format('Y-m-d') !== $endInput || $parsedStart > $parsedEnd) {
        $reportError = 'Khoảng thời gian không hợp lệ. Vui lòng chọn ngày bắt đầu và kết thúc hợp lệ.';
    } else {
        $dateFrom = $parsedStart;
        $dateTo = $parsedEnd;
    }
}

$rangeDays = (int) $dateFrom->diff($dateTo)->days + 1;
$groupByMonth = $rangeDays > 62;
$bucketExpression = $groupByMonth
    ? "DATE_FORMAT(o.created_at, '%Y-%m')"
    : "DATE_FORMAT(o.created_at, '%Y-%m-%d')";
$timeBucketCount = $groupByMonth
    ? ((int) $dateTo->format('Y') * 12 + (int) $dateTo->format('n')) - ((int) $dateFrom->format('Y') * 12 + (int) $dateFrom->format('n')) + 1
    : $rangeDays;
$periodLabel = $groupByMonth ? 'tháng' : 'ngày';
$selectedPeriodLabel = $dateFrom->format('d/m/Y') . ' - ' . $dateTo->format('d/m/Y');

$categoryId = filter_var($_GET['category_id'] ?? 0, FILTER_VALIDATE_INT);
if ($categoryId === false || $categoryId < 0 || ($categoryId > 0 && !isset($categories[$categoryId]))) {
    $categoryId = 0;
    $reportError = $reportError ?: 'Danh mục được chọn không hợp lệ.';
}

$startDate = $dateFrom->format('Y-m-d');
$endDate = $dateTo->format('Y-m-d');
$endExclusive = $dateTo->modify('+1 day')->format('Y-m-d');
$categoryCondition = $categoryId > 0
    ? ' AND EXISTS (SELECT 1 FROM order_items filter_items INNER JOIN products filter_products ON filter_products.id = filter_items.product_id WHERE filter_items.order_id = o.id AND filter_products.category_id = ' . (int) $categoryId . ')'
    : '';

$runReportQuery = static function (string $sql) use ($conn, $startDate, $endExclusive, &$reportError) {
    $statement = $conn->prepare($sql);
    if (!$statement) {
        error_log('Admin report prepare failed: ' . $conn->error);
        $reportError = $reportError ?: 'Không thể tải dữ liệu báo cáo.';
        return false;
    }

    $statement->bind_param('ss', $startDate, $endExclusive);
    if (!$statement->execute()) {
        error_log('Admin report query failed: ' . $statement->error);
        $reportError = $reportError ?: 'Không thể tải dữ liệu báo cáo.';
        $statement->close();
        return false;
    }

    $result = $statement->get_result();
    $statement->close();
    return $result;
};

$summary = [
    'total_orders' => 0,
    'active_orders' => 0,
    'completed_orders' => 0,
    'cancelled_orders' => 0,
    'revenue' => 0,
    'new_customers' => 0,
    'items_sold' => 0,
];

$summaryResult = $runReportQuery(
    "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(o.status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')), 0) AS active_orders,
            COALESCE(SUM(o.status IN ('Đã giao hàng', 'Đã giao')), 0) AS completed_orders,
            COALESCE(SUM(o.status IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')), 0) AS cancelled_orders
     FROM orders o
     WHERE o.created_at >= ? AND o.created_at < ?{$categoryCondition}"
);
if ($summaryResult) {
    $summary = array_merge($summary, $summaryResult->fetch_assoc() ?: []);
}

if ($categoryId > 0) {
    $revenueSql = "SELECT COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue,
                          COALESCE(SUM(oi.quantity), 0) AS items_sold
                   FROM orders o
                   INNER JOIN order_items oi ON oi.order_id = o.id
                   INNER JOIN products p ON p.id = oi.product_id AND p.category_id = " . (int) $categoryId . "
                   WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.status IN ('Đã giao hàng', 'Đã giao')";
} else {
    $revenueSql = "SELECT COALESCE(SUM(o.total_price), 0) AS revenue,
                          COALESCE(SUM((
                              SELECT SUM(oi.quantity)
                              FROM order_items oi
                              WHERE oi.order_id = o.id
                          )), 0) AS items_sold
                   FROM orders o
                   WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.status IN ('Đã giao hàng', 'Đã giao')";
}
$revenueResult = $runReportQuery($revenueSql);
if ($revenueResult) {
    $summary = array_merge($summary, $revenueResult->fetch_assoc() ?: []);
}

$customersResult = $runReportQuery(
    'SELECT COUNT(*) AS new_customers FROM users WHERE created_at >= ? AND created_at < ?'
);
if ($customersResult) {
    $customerRow = $customersResult->fetch_assoc();
    $summary['new_customers'] = (int) ($customerRow['new_customers'] ?? 0);
}

if ($categoryId > 0) {
    $trendSql = "SELECT {$bucketExpression} AS report_bucket,
                        COUNT(DISTINCT o.id) AS order_count,
                        COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng', 'Đã giao') AND p.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS items_sold,
                        COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng', 'Đã giao') AND p.id IS NOT NULL THEN oi.quantity * oi.price ELSE 0 END), 0) AS revenue
                 FROM orders o
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 LEFT JOIN products p ON p.id = oi.product_id AND p.category_id = " . (int) $categoryId . "
                 WHERE o.created_at >= ? AND o.created_at < ?{$categoryCondition}
                 GROUP BY report_bucket
                 ORDER BY report_bucket";
} else {
    $trendSql = "SELECT {$bucketExpression} AS report_bucket,
                        COUNT(*) AS order_count,
                        COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng', 'Đã giao') THEN (
                            SELECT COALESCE(SUM(oi.quantity), 0)
                            FROM order_items oi
                            WHERE oi.order_id = o.id
                        ) ELSE 0 END), 0) AS items_sold,
                        COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng', 'Đã giao') THEN o.total_price ELSE 0 END), 0) AS revenue
                 FROM orders o
                 WHERE o.created_at >= ? AND o.created_at < ?
                 GROUP BY report_bucket
                 ORDER BY report_bucket";
}
$trendByBucket = [];
$trendResult = $runReportQuery($trendSql);
$loadTrendBuckets = static function ($result, DateTimeImmutable $rangeStart) use ($groupByMonth): array {
    $buckets = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $bucketDate = new DateTimeImmutable((string) $row['report_bucket']);
            if ($groupByMonth) {
                $bucketIndex = ((int) $bucketDate->format('Y') * 12 + (int) $bucketDate->format('n'))
                    - ((int) $rangeStart->format('Y') * 12 + (int) $rangeStart->format('n'));
            } else {
                $bucketIndex = (int) $rangeStart->diff($bucketDate)->days;
            }
            $buckets[$bucketIndex] = [
                'revenue' => (float) $row['revenue'],
                'order_count' => (int) $row['order_count'],
                'items_sold' => (int) $row['items_sold'],
            ];
        }
    }
    return $buckets;
};
$trendByBucket = $loadTrendBuckets($trendResult, $dateFrom);

$trendTableRows = [];
for ($slot = 0; $slot < $timeBucketCount; $slot++) {
    $currentPoint = $trendByBucket[$slot] ?? ['revenue' => 0.0, 'order_count' => 0, 'items_sold' => 0];
    $pointDate = $groupByMonth
        ? $dateFrom->modify('first day of this month')->modify('+' . $slot . ' months')
        : $dateFrom->modify('+' . $slot . ' days');
    $trendTableRows[] = [
        'bucket' => $slot,
        'table_label' => $groupByMonth ? $pointDate->format('m/Y') : $pointDate->format('d/m/Y'),
        'revenue' => $currentPoint['revenue'],
        'order_count' => $currentPoint['order_count'],
        'items_sold' => $currentPoint['items_sold'],
    ];
}

$topProducts = [];
$topProductsSql = "SELECT oi.product_name,
                          SUM(oi.quantity) AS quantity_sold,
                          SUM(oi.quantity * oi.price) AS product_revenue
                   FROM order_items oi
                   INNER JOIN orders o ON o.id = oi.order_id
                   LEFT JOIN products p ON p.id = oi.product_id
                   WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.status IN ('Đã giao hàng', 'Đã giao')" .
    ($categoryId > 0 ? ' AND p.category_id = ' . (int) $categoryId : '') .
    ' GROUP BY oi.product_id, oi.product_name
      ORDER BY quantity_sold DESC, product_revenue DESC';
$topProductsResult = $runReportQuery($topProductsSql);
if ($topProductsResult) {
    while ($row = $topProductsResult->fetch_assoc()) {
        $topProducts[] = $row;
    }
}

$categoryRevenue = [];
$categoryRevenueSql = "SELECT c.name AS category_name,
                              COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue,
                              COALESCE(SUM(oi.quantity), 0) AS items_sold
                       FROM orders o
                       INNER JOIN order_items oi ON oi.order_id = o.id
                       INNER JOIN products p ON p.id = oi.product_id
                       INNER JOIN categories c ON c.id = p.category_id
                       WHERE o.created_at >= ? AND o.created_at < ?
                         AND o.status IN ('Đã giao hàng', 'Đã giao')" .
    ($categoryId > 0 ? ' AND p.category_id = ' . (int) $categoryId : '') . "
                       GROUP BY c.id, c.name
                       ORDER BY revenue DESC, items_sold DESC";
$categoryRevenueResult = $runReportQuery($categoryRevenueSql);
if ($categoryRevenueResult) {
    while ($row = $categoryRevenueResult->fetch_assoc()) {
        $categoryRevenue[] = $row;
    }
}

$statusCounts = [
    'Chờ xác nhận' => 0,
    'Đã xác nhận' => 0,
    'Chuẩn bị đơn hàng' => 0,
    'Đang vận chuyển' => 0,
    'Đang giao' => 0,
    'Đã giao hàng' => 0,
    'Đã hủy' => 0,
];
$statusResult = $runReportQuery(
    "SELECT CASE
                WHEN o.status IN ('Đang xử lý') THEN 'Đã xác nhận'
                WHEN o.status IN ('Đang chuẩn bị') THEN 'Chuẩn bị đơn hàng'
                WHEN o.status IN ('Đã giao') THEN 'Đã giao hàng'
                WHEN o.status IN ('Canceled', 'cancelled', 'huy') THEN 'Đã hủy'
                ELSE o.status
            END AS report_status,
            COUNT(*) AS order_count
     FROM orders o
     WHERE o.created_at >= ? AND o.created_at < ?{$categoryCondition}
     GROUP BY report_status"
);
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        if (isset($statusCounts[$row['report_status']])) {
            $statusCounts[$row['report_status']] += (int) $row['order_count'];
        }
    }
}

$detailHeaders = [];
$detailRows = [];
if ($salesSection === 'revenue') {
    $detailHeaders = ['Ngày', 'Số đơn hàng', 'Sản phẩm đã bán', 'Doanh thu'];
    foreach ($trendTableRows as $point) {
        $detailRows[] = [
            $point['table_label'],
            number_format($point['order_count']),
            number_format($point['items_sold']),
            number_format($point['revenue'], 0, ',', '.') . ' đ',
        ];
    }
} elseif ($salesSection === 'category') {
    $detailHeaders = ['Danh mục', 'Sản phẩm đã bán', 'Doanh thu'];
    foreach ($categoryRevenue as $category) {
        $detailRows[] = [
            $category['category_name'],
            number_format((int) $category['items_sold']),
            number_format((float) $category['revenue'], 0, ',', '.') . ' đ',
        ];
    }
} elseif ($salesSection === 'products') {
    $detailHeaders = ['Sản phẩm', 'Số lượng đã bán', 'Doanh thu sản phẩm'];
    foreach ($topProducts as $product) {
        $detailRows[] = [
            $product['product_name'],
            number_format((int) $product['quantity_sold']),
            number_format((float) $product['product_revenue'], 0, ',', '.') . ' đ',
        ];
    }
} else {
    $detailHeaders = ['Trạng thái đơn hàng', 'Số lượng đơn'];
    foreach ($statusCounts as $status => $count) {
        $detailRows[] = [$status, number_format($count)];
    }
}

$salesTitle = $activeReportTitle ?: ($salesSections[$salesSection] ?? 'Báo cáo bán hàng');

if ($isExport && $reportError === '') {
    $excelRows = [
        ['BÁO CÁO BÁN HÀNG - ÉCRIN JEWELRY'],
        ['Loại báo cáo', $salesTitle],
        ['Khoảng thời gian', $selectedPeriodLabel],
        ['Danh mục', $categoryId > 0 ? (string) ($categories[$categoryId] ?? 'Tất cả') : 'Tất cả'],
        ['Thời gian xuất', date('d/m/Y H:i')],
        [],
        ['TỔNG QUAN CHỈ SỐ', 'GIÁ TRỊ'],
        ['Doanh thu đã giao', (float) $summary['revenue']],
        ['Tổng đơn hàng', (int) $summary['total_orders']],
        ['Đơn hàng đã giao', (int) $summary['completed_orders']],
        ['Đơn hàng đã hủy', (int) $summary['cancelled_orders']],
        ['Khách hàng mới', (int) $summary['new_customers']],
        ['Sản phẩm đã bán', (int) $summary['items_sold']],
        [],
        ['BẢNG CHI TIẾT'],
        $detailHeaders,
    ];
    if ($salesSection === 'revenue') {
        $excelRows[] = [
            'Tổng cộng (' . $selectedPeriodLabel . ')',
            (int) $summary['total_orders'],
            (int) $summary['items_sold'],
            (float) $summary['revenue'],
        ];
        foreach ($trendTableRows as $point) {
            $excelRows[] = [
                $point['table_label'],
                (int) $point['order_count'],
                (int) $point['items_sold'],
                (float) $point['revenue'],
            ];
        }
    } elseif ($salesSection === 'category') {
        foreach ($categoryRevenue as $category) {
            $excelRows[] = [
                (string) $category['category_name'],
                (int) $category['items_sold'],
                (float) $category['revenue'],
            ];
        }
    } elseif ($salesSection === 'products') {
        foreach ($topProducts as $product) {
            $excelRows[] = [
                (string) $product['product_name'],
                (int) $product['quantity_sold'],
                (float) $product['product_revenue'],
            ];
        }
    } else {
        foreach ($statusCounts as $status => $count) {
            $excelRows[] = [$status, (int) $count];
        }
    }

    $filename = 'bao-cao-' . $salesSection . '-' . $startDate . '-den-' . $endDate;
    if ($exportFormat === 'csv') {
        $sendCsvFile($filename . '.csv', $excelRows);
    } else {
        $sendExcelFile($filename . '.xlsx', $excelRows);
    }
}

$exportParamsXlsx = [
    'view' => 'sales',
    'section' => $salesSection,
    'period' => $period,
    'from' => $startDate,
    'to' => $endDate,
    'category_id' => $categoryId,
    'export' => 'xlsx',
];
if ($savedReportId) {
    $exportParamsXlsx['report_id'] = $savedReportId;
}
$exportQueryXlsx = http_build_query($exportParamsXlsx);
$exportQueryCsv = http_build_query([
    'view' => 'sales',
    'section' => $salesSection,
    'period' => $period,
    'from' => $startDate,
    'to' => $endDate,
    'category_id' => $categoryId,
    'export' => 'csv',
]);
$formatCompactRevenue = static function (float $amount): string {
    if ($amount >= 1000000) {
        return number_format($amount / 1000000, 1, ',', '.') . 'tr';
    }
    return $amount >= 1000 ? number_format($amount / 1000, 0, ',', '.') . 'k' : number_format($amount, 0, ',', '.') . 'đ';
};
$svgNumber = static fn($value): string => number_format((float) $value, 2, '.', '');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $escape($salesTitle); ?> - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .report-panel { margin-bottom: 20px; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .report-panel h2 { margin: 0; color: var(--navy); font-size: 17px; }
        .report-panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .report-panel-header p { margin: 6px 0 0; color: var(--muted); font-size: 12px; }
        .report-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
        .report-field { display: grid; gap: 6px; min-width: 150px; }
        .report-field label { color: #64758a; font-size: 12px; font-weight: 600; }
        .report-field select, .report-field input { min-height: 39px; padding: 8px 10px; border: 1px solid #dce2e9; border-radius: 7px; color: var(--text); background: #fff; }
        .report-filter-actions { display: flex; gap: 8px; }
        .report-button { display: inline-flex; min-height: 39px; align-items: center; justify-content: center; padding: 0 14px; border: 1px solid var(--navy); border-radius: 7px; background: var(--navy); color: #fff; font-weight: 600; cursor: pointer; }
        .report-button:hover { background: var(--navy-soft); color: #fff; }
        .report-button-secondary { border-color: #dce2e9; background: #fff; color: var(--navy); }
        .report-button-secondary:hover { background: #f5f7fa; color: var(--navy); }
        .report-error { margin: 0 0 18px; padding: 12px 15px; border: 1px solid #f3c4c4; border-radius: 8px; background: #fff2f2; color: #9c2f2f; }
        .report-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
        .report-stat { min-width: 0; padding: 18px; border: 1px solid #e9edf2; border-radius: 11px; background: #fff; box-shadow: var(--shadow); }
        .report-stat-label { color: #8190a0; font-size: 12px; }
        .report-stat-value { display: block; margin-top: 9px; color: var(--navy); font-size: clamp(18px, 2vw, 25px); font-weight: 700; overflow-wrap: anywhere; }
        .report-stat-note { display: block; margin-top: 7px; color: #98a2af; font-size: 11px; }
        .report-columns { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(270px, 1fr); gap: 18px; }
        .report-empty { margin: 12px 0 0; color: var(--muted); font-size: 13px; }
        .report-status-list { display: grid; }
        .report-status-row { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid #eef1f5; color: #687789; font-size: 12px; }
        .report-status-row:last-child { border-bottom: 0; }
        .report-status-row strong { color: var(--navy); }
        .report-table-wrap { overflow-x: auto; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 12px 10px; border-bottom: 1px solid #eef1f5; text-align: left; }
        .report-table th { color: #7d8a99; font-size: 11px; font-weight: 600; }
        .report-table td { color: #35465a; font-size: 12px; }
        .report-table td:last-child, .report-table th:last-child { text-align: right; }
        .report-product-name { min-width: 180px; font-weight: 600; }
        .report-detail-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
        .report-back-button { display: inline-grid; width: 40px; height: 40px; place-items: center; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #718198; font-size: 20px; }
        .report-detail-heading h1 { font-size: 23px; }
        .report-detail-actions { display: flex; align-items: center; gap: 18px; margin-left: auto; }
        .report-detail-actions a, .report-detail-actions button { padding: 0; border: 0; background: transparent; color: #34465b; cursor: pointer; font-size: 13px; font-weight: 600; }
        .report-detail-actions a:hover, .report-detail-actions button:hover { color: #0784f9; }
        .report-detail-filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; }
        .report-detail-filterbar .report-field { min-width: 0; }
        .report-detail-filterbar .report-field select, .report-detail-filterbar .report-field input { min-height: 39px; }
        .report-detail-filterbar .report-field:first-of-type select { min-width: 140px; }
        .report-compare-label, .report-grain-label { display: inline-flex; min-height: 39px; align-items: center; padding: 0 12px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #526276; font-size: 12px; white-space: nowrap; }
        .report-table-panel { overflow: hidden; padding: 18px 0 0; }
        .report-table-wrap { overflow-x: auto; }
        .report-table-detail { min-width: 720px; }
        .report-table-detail thead { background: #f2f4f6; }
        .report-table-detail th { padding: 12px 14px; color: #46576a; font-size: 12px; white-space: nowrap; }
        .report-table-detail td { padding: 12px 14px; color: #29394c; font-size: 13px; white-space: nowrap; }
        .report-table-detail tbody:not(.report-table-summary) tr:nth-child(even) { background: #f3f5f7; }
        .report-table-detail .report-table-summary td { background: #fff; font-weight: 700; }
        .report-table-detail .report-table-summary td:last-child { color: #17283d; }
        .report-table-detail tbody tr[hidden] { display: none; }
        .report-table-footer { display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 12px 18px; color: #526276; font-size: 12px; }
        .report-table-footer select, .report-table-footer button { min-height: 32px; border: 1px solid #dce2e9; border-radius: 5px; background: #fff; color: #526276; }
        .report-table-footer button { min-width: 32px; cursor: pointer; }
        .report-table-footer button:disabled { opacity: .4; cursor: default; }
        .report-copy-status { color: #16835d; font-size: 12px; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; clip-path: inset(50%); }
        .report-export-modal { position: fixed; inset: 0; z-index: 99999; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
        .report-export-card { width: min(100%, 480px); overflow: hidden; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
        .report-export-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 12px; }
        .report-export-header h2 { margin: 0; color: #111827; font-size: 20px; font-weight: 700; }
        .report-export-close { border: 0; background: transparent; color: #9ca3af; cursor: pointer; font-size: 24px; line-height: 1; padding: 4px; }
        .report-export-close:hover { color: #4b5563; }
        .report-export-body { padding: 10px 24px 28px; color: #1f2937; font-size: 15px; line-height: 1.5; }
        .report-export-body p { margin: 0; }
        .report-export-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding: 0 24px 24px; }
        .report-export-footer button, .report-export-footer a { display: inline-flex; min-width: 82px; height: 38px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; box-sizing: border-box; transition: all .15s ease; }
        .report-export-footer .report-export-cancel { border: 1px solid #0084ff; background: #fff; color: #0084ff; }
        .report-export-footer .report-export-cancel:hover { background: #f0f7ff; }
        .report-export-footer .report-export-confirm { border: 1px solid #0084ff; background: #0084ff; color: #fff; }
        .report-export-footer .report-export-confirm:hover { background: #0070d8; border-color: #0070d8; }
        @media (max-width: 1050px) { .report-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 820px) { .report-columns { grid-template-columns: 1fr; } }
        @media (max-width: 560px) {
            .report-panel { padding: 16px; }
            .report-stats { gap: 9px; }
            .report-stat { padding: 14px; }
            .report-filters, .report-field { width: 100%; }
            .report-filter-actions { width: 100%; }
            .report-filter-actions > * { flex: 1; }
            .report-panel-header { flex-direction: column; }
            .report-detail-heading { flex-wrap: wrap; }
            .report-detail-actions { width: 100%; justify-content: flex-end; }
            .report-table-footer { justify-content: flex-start; flex-wrap: wrap; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <a href="<?php echo $escape($appBaseUrl . '/admin/reports.php'); ?>">Báo cáo</a> <span>/</span> <strong><?php echo $escape($salesTitle); ?></strong></div>
            <div class="admin-profile">
                <div class="avatar">QL</div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="report-detail-heading">
                <a class="report-back-button" href="<?php echo $escape($appBaseUrl . '/admin/reports.php'); ?>" aria-label="Quay lại danh sách báo cáo">←</a>
                <h1><?php echo $escape($salesTitle); ?></h1>
                <div class="report-detail-actions">
                    <?php if ($reportError === ''): ?>
                        <button type="button" onclick="document.getElementById('export-confirmation').style.display='grid'">⇧ Xuất báo cáo</button>
                    <?php endif; ?>
                    <button type="button" id="copy-report">▢ Sao chép</button>
                </div>
            </div>
            <?php if ($reportError !== ''): ?><p class="report-error" role="alert"><?php echo $escape($reportError); ?></p><?php endif; ?>
            <form method="get" class="report-detail-filterbar">
                <input type="hidden" name="view" value="sales">
                <input type="hidden" name="section" value="<?php echo $escape($salesSection); ?>">
                <?php if ($savedReportId): ?><input type="hidden" name="report_id" value="<?php echo (int) $savedReportId; ?>"><?php endif; ?>
                <span class="report-compare-label">Theo ngày tạo đơn</span>
                <div class="report-field">
                    <label class="sr-only" for="report-period">Khoảng thời gian</label>
                    <select name="period" id="report-period">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                        <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                        <option value="last_7" <?php echo $period === 'last_7' ? 'selected' : ''; ?>>7 ngày qua (<?php echo $escape($selectedPeriodLabel); ?>)</option>
                        <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                        <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                        <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>30 ngày gần nhất</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn ngày</option>
                    </select>
                </div>
                <div class="report-field">
                    <label class="sr-only" for="report-from">Từ ngày</label>
                    <input type="date" name="from" id="report-from" value="<?php echo $escape($startDate); ?>">
                </div>
                <div class="report-field">
                    <label class="sr-only" for="report-to">Đến ngày</label>
                    <input type="date" name="to" id="report-to" value="<?php echo $escape($endDate); ?>">
                </div>
                <div class="report-field">
                    <label class="sr-only" for="report-category">Danh mục</label>
                    <select name="category_id" id="report-category">
                        <option value="0">Tất cả danh mục</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo (int) $id; ?>" <?php echo $categoryId === $id ? 'selected' : ''; ?>><?php echo $escape($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="report-button" type="submit">Áp dụng</button>
                <a class="report-button report-button-secondary" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . http_build_query(array_filter(['view' => 'sales', 'section' => $salesSection, 'report_id' => $savedReportId]))); ?>">Đặt lại</a>
            </form>
            <span class="report-copy-status" id="copy-status" role="status"></span>
            <section class="report-panel report-table-panel" id="<?php echo $escape($salesSectionTargets[$salesSection]); ?>">
                <?php if ($detailRows === []): ?>
                    <p class="report-empty">Không có dữ liệu trong khoảng thời gian này.</p>
                <?php else: ?>
                    <div class="report-table-wrap">
                        <table class="report-table report-table-detail" id="report-data-table">
                            <thead><tr><?php foreach ($detailHeaders as $header): ?><th><?php echo $escape($header); ?> <span aria-hidden="true">↕</span></th><?php endforeach; ?></tr></thead>
                            <?php if ($salesSection === 'revenue'): ?>
                                <tbody class="report-table-summary">
                                    <tr>
                                        <td><?php echo $escape($selectedPeriodLabel); ?></td>
                                        <td><?php echo number_format((int) $summary['total_orders']); ?></td>
                                        <td><?php echo number_format((int) $summary['items_sold']); ?></td>
                                        <td><?php echo number_format((float) $summary['revenue'], 0, ',', '.'); ?> đ</td>
                                    </tr>
                                </tbody>
                            <?php endif; ?>
                            <tbody>
                                <?php foreach ($detailRows as $row): ?>
                                    <tr><?php foreach ($row as $cell): ?><td><?php echo $escape($cell); ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="report-table-footer">
                        <span id="table-range"></span>
                        <label for="table-page-size">Hiển thị</label>
                        <select id="table-page-size"><option>20</option><option selected>50</option><option>100</option></select>
                        <span>Kết quả</span>
                        <button type="button" id="table-prev" aria-label="Trang trước">‹</button>
                        <span id="table-page"></span>
                        <button type="button" id="table-next" aria-label="Trang sau">›</button>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>
<?php if ($reportError === ''): ?>
    <div class="report-export-modal" id="export-confirmation" style="display:none">
        <section class="report-export-card" role="dialog" aria-modal="true" aria-labelledby="export-confirmation-title" tabindex="-1">
            <header class="report-export-header">
                <h2 id="export-confirmation-title">Xuất báo cáo</h2>
                <button class="report-export-close" type="button" aria-label="Đóng" onclick="document.getElementById('export-confirmation').style.display='none'">×</button>
            </header>
            <div class="report-export-body">
                <p>Bạn có muốn xuất báo cáo? Báo cáo này sẽ được xuất ra file excel</p>
            </div>
            <footer class="report-export-footer">
                <button type="button" class="report-export-cancel" onclick="document.getElementById('export-confirmation').style.display='none'">Hủy</button>
                <a class="report-export-confirm" id="sales-export-xlsx" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . $exportQueryXlsx); ?>" onclick="setTimeout(() => { document.getElementById('export-confirmation').style.display='none'; }, 200);">Xuất báo cáo</a>
            </footer>
        </section>
    </div>
<?php endif; ?>
<script>
    const salesExportModal = document.getElementById('export-confirmation');
    if (salesExportModal) {
        salesExportModal.addEventListener('click', (e) => {
            if (e.target === salesExportModal) salesExportModal.style.display = 'none';
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && salesExportModal.style.display !== 'none') {
                salesExportModal.style.display = 'none';
            }
        });
    }

    const syncSalesExportLinks = () => {
        const periodVal = document.getElementById('report-period')?.value || 'this_month';
        const fromVal = document.getElementById('report-from')?.value || '';
        const toVal = document.getElementById('report-to')?.value || '';
        const catVal = document.getElementById('report-category')?.value || '0';
        const xlsxBtn = document.getElementById('sales-export-xlsx');
        if (xlsxBtn) {
            const u = new URL(xlsxBtn.href, window.location.origin);
            if (periodVal) u.searchParams.set('period', periodVal);
            if (fromVal) u.searchParams.set('from', fromVal);
            if (toVal) u.searchParams.set('to', toVal);
            if (catVal) u.searchParams.set('category_id', catVal);
            xlsxBtn.href = u.pathname + u.search;
        }
    };

    const reportPeriod = document.getElementById('report-period');
    reportPeriod.addEventListener('change', () => {
        syncSalesExportLinks();
        if (reportPeriod.value === 'custom') return;
        reportPeriod.form.requestSubmit();
    });
    document.getElementById('report-from').addEventListener('change', () => {
        reportPeriod.value = 'custom';
        syncSalesExportLinks();
    });
    document.getElementById('report-to').addEventListener('change', () => {
        reportPeriod.value = 'custom';
        syncSalesExportLinks();
    });
    document.getElementById('report-category').addEventListener('change', (event) => {
        syncSalesExportLinks();
        event.currentTarget.form.requestSubmit();
    });

    const reportTable = document.getElementById('report-data-table');
    if (reportTable) {
        const allRows = Array.from(reportTable.querySelectorAll('tbody:not(.report-table-summary) tr'));
        const pageSize = document.getElementById('table-page-size');
        const pageRange = document.getElementById('table-range');
        const pageLabel = document.getElementById('table-page');
        const previousButton = document.getElementById('table-prev');
        const nextButton = document.getElementById('table-next');
        let currentPage = 0;
        const renderPage = () => {
            const size = Number(pageSize.value);
            const pageCount = Math.max(1, Math.ceil(allRows.length / size));
            currentPage = Math.min(currentPage, pageCount - 1);
            const start = currentPage * size;
            const end = Math.min(start + size, allRows.length);
            const visibleRows = new Set(allRows.slice(start, end));
            allRows.forEach((row) => { row.hidden = !visibleRows.has(row); });
            pageRange.textContent = `Từ ${start + 1} đến ${end} trên tổng ${allRows.length}`;
            pageLabel.textContent = `${currentPage + 1}/${pageCount}`;
            previousButton.disabled = currentPage === 0;
            nextButton.disabled = currentPage >= pageCount - 1;
        };
        pageSize.addEventListener('change', () => { currentPage = 0; renderPage(); });
        previousButton.addEventListener('click', () => { currentPage--; renderPage(); });
        nextButton.addEventListener('click', () => { currentPage++; renderPage(); });
        renderPage();
    }

    document.getElementById('copy-report').addEventListener('click', async () => {
        const table = document.getElementById('report-data-table');
        const status = document.getElementById('copy-status');
        if (!table) {
            status.textContent = 'Không có dữ liệu để sao chép.';
            return;
        }
        const text = Array.from(table.rows).map((row) => Array.from(row.cells).map((cell) => cell.textContent.trim()).join('\t')).join('\n');
        try {
            await navigator.clipboard.writeText(text);
            status.textContent = 'Đã sao chép báo cáo.';
        } catch (error) {
            status.textContent = 'Không thể sao chép. Vui lòng kiểm tra quyền clipboard của trình duyệt.';
        }
    });
</script>
</body>
</html>
