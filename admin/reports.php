<?php
require_once __DIR__ . '/../include/admin_guard.php';
requireManagerRole();
require_once __DIR__ . '/../config/db.php';

$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'reports.php';
$reportError = '';

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
$comparisonTo = $dateFrom->modify('-1 day');
$comparisonFrom = $comparisonTo->modify('-' . ($rangeDays - 1) . ' days');
$chartSlotCount = $groupByMonth
    ? ((int) $dateTo->format('Y') * 12 + (int) $dateTo->format('n')) - ((int) $dateFrom->format('Y') * 12 + (int) $dateFrom->format('n')) + 1
    : $rangeDays;
$periodLabel = $groupByMonth ? 'tháng' : 'ngày';
$selectedPeriodLabel = $dateFrom->format('d/m/Y') . ' - ' . $dateTo->format('d/m/Y');
$previousPeriodLabel = $comparisonFrom->format('d/m/Y') . ' - ' . $comparisonTo->format('d/m/Y');

$categoryId = filter_var($_GET['category_id'] ?? 0, FILTER_VALIDATE_INT);
if ($categoryId === false || $categoryId < 0 || ($categoryId > 0 && !isset($categories[$categoryId]))) {
    $categoryId = 0;
    $reportError = $reportError ?: 'Danh mục được chọn không hợp lệ.';
}

$startDate = $dateFrom->format('Y-m-d');
$endDate = $dateTo->format('Y-m-d');
$endExclusive = $dateTo->modify('+1 day')->format('Y-m-d');
$comparisonStart = $comparisonFrom->format('Y-m-d');
$comparisonEndExclusive = $dateFrom->format('Y-m-d');
$categoryCondition = $categoryId > 0
    ? ' AND EXISTS (SELECT 1 FROM order_items filter_items INNER JOIN products filter_products ON filter_products.id = filter_items.product_id WHERE filter_items.order_id = o.id AND filter_products.category_id = ' . (int) $categoryId . ')'
    : '';

$runReportQuery = static function (string $sql, ?string $rangeStart = null, ?string $rangeEnd = null) use ($conn, $startDate, $endExclusive, &$reportError) {
    $statement = $conn->prepare($sql);
    if (!$statement) {
        error_log('Admin report prepare failed: ' . $conn->error);
        $reportError = $reportError ?: 'Không thể tải dữ liệu báo cáo.';
        return false;
    }

    $queryStart = $rangeStart ?? $startDate;
    $queryEnd = $rangeEnd ?? $endExclusive;
    $statement->bind_param('ss', $queryStart, $queryEnd);
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
                        COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng', 'Đã giao') THEN o.total_price ELSE 0 END), 0) AS revenue
                 FROM orders o
                 WHERE o.created_at >= ? AND o.created_at < ?
                 GROUP BY report_bucket
                 ORDER BY report_bucket";
}
$trendByBucket = [];
$previousTrendByBucket = [];
$trendResult = $runReportQuery($trendSql);
$previousTrendResult = $runReportQuery($trendSql, $comparisonStart, $comparisonEndExclusive);
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
            ];
        }
    }
    return $buckets;
};
$trendByBucket = $loadTrendBuckets($trendResult, $dateFrom);
$previousTrendByBucket = $loadTrendBuckets($previousTrendResult, $comparisonFrom);

$chartData = [];
$maxRevenue = 0.0;
$currentPeriodRevenue = 0.0;
$previousPeriodRevenue = 0.0;
$hasChartRevenue = false;
for ($slot = 0; $slot < $chartSlotCount; $slot++) {
    $currentPoint = $trendByBucket[$slot] ?? ['revenue' => 0.0, 'order_count' => 0];
    $previousPoint = $previousTrendByBucket[$slot] ?? ['revenue' => 0.0, 'order_count' => 0];
    $pointDate = $groupByMonth
        ? $dateFrom->modify('first day of this month')->modify('+' . $slot . ' months')
        : $dateFrom->modify('+' . $slot . ' days');
    $label = $groupByMonth ? $pointDate->format('m/Y') : $pointDate->format('d/m');
    $chartData[] = [
        'bucket' => $slot,
        'label' => $label,
        'revenue' => $currentPoint['revenue'],
        'order_count' => $currentPoint['order_count'],
        'previous_revenue' => $previousPoint['revenue'],
        'previous_order_count' => $previousPoint['order_count'],
    ];
    $currentPeriodRevenue += $currentPoint['revenue'];
    $previousPeriodRevenue += $previousPoint['revenue'];
    $maxRevenue = max($maxRevenue, $currentPoint['revenue'], $previousPoint['revenue']);
    $hasChartRevenue = $hasChartRevenue || $currentPoint['revenue'] > 0 || $previousPoint['revenue'] > 0;
}

$chartMax = $maxRevenue > 0 ? $maxRevenue : 1;
$chartWidth = 780;
$chartPlotLeft = 52;
$chartPlotRight = 770;
$chartPlotTop = 18;
$chartPlotBottom = 252;
$currentLinePoints = [];
$previousLinePoints = [];
$axisLabelInterval = max(1, (int) ceil($chartSlotCount / 10));
foreach ($chartData as $index => $point) {
    $x = $chartPlotLeft + ($chartSlotCount > 1 ? ($index / ($chartSlotCount - 1)) * ($chartPlotRight - $chartPlotLeft) : ($chartPlotRight - $chartPlotLeft) / 2);
    $currentY = $chartPlotBottom - ($point['revenue'] / $chartMax) * ($chartPlotBottom - $chartPlotTop);
    $previousY = $chartPlotBottom - ($point['previous_revenue'] / $chartMax) * ($chartPlotBottom - $chartPlotTop);
    $currentLinePoints[] = ['x' => $x, 'y' => $currentY];
    $previousLinePoints[] = ['x' => $x, 'y' => $previousY];
}
$buildSmoothPath = static function (array $points): string {
    if ($points === []) {
        return '';
    }

    $formatPoint = static fn(float $x, float $y): string => number_format($x, 2, '.', '') . ' ' . number_format($y, 2, '.', '');
    $path = 'M ' . $formatPoint($points[0]['x'], $points[0]['y']);
    $pointCount = count($points);

    for ($index = 0; $index < $pointCount - 1; $index++) {
        $previous = $points[max(0, $index - 1)];
        $start = $points[$index];
        $end = $points[$index + 1];
        $next = $points[min($pointCount - 1, $index + 2)];
        $minimumY = min($start['y'], $end['y']);
        $maximumY = max($start['y'], $end['y']);
        $control1X = $start['x'] + ($end['x'] - $previous['x']) / 6;
        $control1Y = max($minimumY, min($maximumY, $start['y'] + ($end['y'] - $previous['y']) / 6));
        $control2X = $end['x'] - ($next['x'] - $start['x']) / 6;
        $control2Y = max($minimumY, min($maximumY, $end['y'] - ($next['y'] - $start['y']) / 6));
        $path .= ' C ' . $formatPoint($control1X, $control1Y)
            . ', ' . $formatPoint($control2X, $control2Y)
            . ', ' . $formatPoint($end['x'], $end['y']);
    }

    return $path;
};
$currentLinePath = $buildSmoothPath($currentLinePoints);
$previousLinePath = $buildSmoothPath($previousLinePoints);
$revenueChangePercent = $previousPeriodRevenue > 0
    ? (($currentPeriodRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100
    : null;

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
      ORDER BY quantity_sold DESC, product_revenue DESC
      LIMIT 8';
$topProductsResult = $runReportQuery($topProductsSql);
if ($topProductsResult) {
    while ($row = $topProductsResult->fetch_assoc()) {
        $topProducts[] = $row;
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

if (($_GET['export'] ?? '') === 'csv' && $reportError === '') {
    $filename = 'bao-cao-' . $startDate . '-den-' . $endDate . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        error_log('Admin report CSV output could not be opened.');
        exit;
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['BÁO CÁO THỐNG KÊ ÉCRIN JEWELRY']);
    fputcsv($output, ['Loại báo cáo', $periodLabel, $selectedPeriodLabel, 'So sánh với', $previousPeriodLabel]);
    fputcsv($output, ['Từ ngày', $startDate, 'Đến ngày', $endDate]);
    $csvCategory = $categoryId > 0 ? (string) $categories[$categoryId] : 'Tất cả';
    if (preg_match('/^[\s]*[=+\-@]/u', $csvCategory)) {
        $csvCategory = "'" . $csvCategory;
    }
    fputcsv($output, ['Danh mục', $csvCategory]);
    fputcsv($output, ['Chỉ số', 'Giá trị']);
    fputcsv($output, ['Doanh thu đã giao', (float) $summary['revenue']]);
    fputcsv($output, ['Tổng đơn hàng', (int) $summary['total_orders']]);
    fputcsv($output, ['Đơn hàng đã giao', (int) $summary['completed_orders']]);
    fputcsv($output, ['Đơn hàng đã hủy', (int) $summary['cancelled_orders']]);
    fputcsv($output, ['Khách hàng mới', (int) $summary['new_customers']]);
    fputcsv($output, ['Sản phẩm đã bán', (int) $summary['items_sold']]);
    fputcsv($output, []);
    fputcsv($output, ['Thời điểm', 'Số đơn', 'Doanh thu kỳ này', 'Doanh thu kỳ trước']);
    foreach ($chartData as $point) {
        fputcsv($output, [$point['label'], $point['order_count'], $point['revenue'], $point['previous_revenue']]);
    }
    fputcsv($output, []);
    fputcsv($output, ['Sản phẩm bán chạy', 'Số lượng', 'Doanh thu']);
    foreach ($topProducts as $product) {
        $productName = (string) $product['product_name'];
        if (preg_match('/^[\s]*[=+\-@]/u', $productName)) {
            $productName = "'" . $productName;
        }
        fputcsv($output, [$productName, (int) $product['quantity_sold'], (float) $product['product_revenue']]);
    }
    fclose($output);
    exit;
}

$exportQuery = http_build_query([
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
    <title>Báo cáo và thống kê - ÉCRIN Jewelry</title>
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
        .report-chart-summary { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin: 0 0 12px; }
        .report-chart-summary strong { color: #17283d; font-size: 25px; }
        .report-change { font-size: 12px; }
        .report-change.positive { color: #16835d; }
        .report-change.negative { color: #d14747; }
        .report-chart-scroll { overflow-x: auto; padding: 4px 0; }
        .report-line-chart { display: block; width: 100%; min-width: 760px; height: auto; overflow: visible; font-family: inherit; }
        .report-chart-grid { stroke: #e2e7ed; stroke-width: 1; }
        .report-chart-axis-label { fill: #718198; font-size: 12px; }
        .report-chart-line { fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
        .report-chart-current { stroke: #0784f9; }
        .report-chart-previous { stroke: #91c8ff; stroke-dasharray: 7 6; }
        .report-chart-dot-current { fill: #0784f9; }
        .report-chart-dot-previous { fill: #fff; stroke: #91c8ff; stroke-width: 2; }
        .report-chart-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 22px; margin: 12px 0 0; color: #718198; font-size: 12px; }
        .report-chart-legend span { display: inline-flex; align-items: center; gap: 7px; }
        .report-chart-legend i { width: 20px; border-top: 2px solid #0784f9; }
        .report-chart-legend .previous-line { border-top: 2px dashed #91c8ff; }
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
        }
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
            <div class="welcome-row">
                <div>
                    <h1>Báo cáo và thống kê</h1>
                    <p class="page-subtitle">Theo dõi doanh thu, đơn hàng và hiệu quả sản phẩm.</p>
                </div>
            </div>

            <?php if ($reportError !== ''): ?>
                <p class="report-error" role="alert"><?php echo $escape($reportError); ?></p>
            <?php endif; ?>

            <section class="report-panel">
                <div class="report-panel-header">
                    <div><h2>Bộ lọc báo cáo</h2><p>Dữ liệu từ <?php echo $escape($startDate); ?> đến <?php echo $escape($endDate); ?></p></div>
                    <?php if ($reportError === ''): ?>
                        <a class="report-button report-button-secondary" href="<?php echo $escape($appBaseUrl . '/admin/reports.php?' . $exportQuery); ?>">Xuất CSV</a>
                    <?php endif; ?>
                </div>
                <form method="get" class="report-filters">
                    <div class="report-field">
                        <label for="report-period">Thời gian</label>
                        <select name="period" id="report-period">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                            <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                            <option value="last_7" <?php echo $period === 'last_7' ? 'selected' : ''; ?>>7 ngày qua</option>
                            <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                            <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>30 ngày gần nhất</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn ngày</option>
                        </select>
                    </div>
                    <div class="report-field">
                        <label for="report-from">Từ ngày</label>
                        <input type="date" name="from" id="report-from" value="<?php echo $escape($startDate); ?>">
                    </div>
                    <div class="report-field">
                        <label for="report-to">Đến ngày</label>
                        <input type="date" name="to" id="report-to" value="<?php echo $escape($endDate); ?>">
                    </div>
                    <div class="report-field">
                        <label for="report-category">Danh mục</label>
                        <select name="category_id" id="report-category">
                            <option value="0">Tất cả danh mục</option>
                            <?php foreach ($categories as $id => $name): ?>
                                <option value="<?php echo (int) $id; ?>" <?php echo $categoryId === $id ? 'selected' : ''; ?>><?php echo $escape($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="report-filter-actions">
                        <button class="report-button" type="submit">Áp dụng</button>
                        <a class="report-button report-button-secondary" href="<?php echo $escape($appBaseUrl . '/admin/reports.php'); ?>">Đặt lại</a>
                    </div>
                </form>
            </section>

            <section class="report-stats" aria-label="Thống kê tổng quan">
                <article class="report-stat">
                    <span class="report-stat-label">Doanh thu đã giao</span>
                    <strong class="report-stat-value"><?php echo number_format((float) $summary['revenue'], 0, ',', '.'); ?> đ</strong>
                    <span class="report-stat-note">Chỉ tính đơn hàng đã giao thành công</span>
                </article>
                <article class="report-stat">
                    <span class="report-stat-label">Tổng đơn hàng</span>
                    <strong class="report-stat-value"><?php echo number_format((int) $summary['total_orders']); ?></strong>
                    <span class="report-stat-note"><?php echo number_format((int) $summary['completed_orders']); ?> đơn đã giao</span>
                </article>
                <article class="report-stat">
                    <span class="report-stat-label">Khách hàng mới</span>
                    <strong class="report-stat-value"><?php echo number_format((int) $summary['new_customers']); ?></strong>
                    <span class="report-stat-note">Đăng ký trong khoảng thời gian đã chọn</span>
                </article>
                <article class="report-stat">
                    <span class="report-stat-label">Sản phẩm đã bán</span>
                    <strong class="report-stat-value"><?php echo number_format((int) $summary['items_sold']); ?></strong>
                    <span class="report-stat-note"><?php echo number_format((int) $summary['cancelled_orders']); ?> đơn đã hủy</span>
                </article>
            </section>

            <div class="report-columns">
                <section class="report-panel">
                    <div class="report-panel-header">
                        <div>
                            <h2>Doanh thu theo thời gian</h2>
                            <p><?php echo $escape($selectedPeriodLabel); ?> · Doanh thu từ các đơn hàng đã giao</p>
                        </div>
                    </div>
                    <?php if (!$hasChartRevenue): ?>
                        <p class="report-empty">Chưa có doanh thu trong kỳ này và kỳ so sánh.</p>
                    <?php else: ?>
                        <div class="report-chart-summary">
                            <strong><?php echo number_format($currentPeriodRevenue, 0, ',', '.'); ?> đ</strong>
                            <?php if ($revenueChangePercent !== null): ?>
                                <span class="report-change <?php echo $revenueChangePercent >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo $revenueChangePercent >= 0 ? '↑' : '↓'; ?>
                                    <?php echo number_format(abs($revenueChangePercent), 2, ',', '.'); ?>% so với kỳ trước
                                </span>
                            <?php else: ?>
                                <span class="report-change">Chưa có doanh thu kỳ trước để so sánh</span>
                            <?php endif; ?>
                        </div>
                        <div class="report-chart-scroll" role="region" aria-label="Biểu đồ doanh thu so sánh với kỳ trước" tabindex="0">
                            <svg class="report-line-chart" viewBox="0 0 <?php echo $chartWidth; ?> 300" role="img" aria-label="Doanh thu <?php echo $escape($periodLabel); ?> hiện tại so với <?php echo $escape($periodLabel); ?> trước">
                                <?php for ($tick = 0; $tick <= 4; $tick++): ?>
                                    <?php
                                    $tickRatio = $tick / 4;
                                    $tickY = $chartPlotBottom - $tickRatio * ($chartPlotBottom - $chartPlotTop);
                                    $tickRevenue = $chartMax * $tickRatio;
                                    ?>
                                    <line class="report-chart-grid" x1="<?php echo $chartPlotLeft; ?>" y1="<?php echo $svgNumber($tickY); ?>" x2="<?php echo $chartPlotRight; ?>" y2="<?php echo $svgNumber($tickY); ?>"></line>
                                    <text class="report-chart-axis-label" x="<?php echo $chartPlotLeft - 8; ?>" y="<?php echo $svgNumber($tickY + 4); ?>" text-anchor="end"><?php echo $escape($formatCompactRevenue($tickRevenue)); ?></text>
                                <?php endfor; ?>
                                <path class="report-chart-line report-chart-previous" d="<?php echo $escape($previousLinePath); ?>"></path>
                                <path class="report-chart-line report-chart-current" d="<?php echo $escape($currentLinePath); ?>"></path>
                                <?php foreach ($chartData as $index => $point): ?>
                                    <?php
                                    $pointX = $chartPlotLeft + ($chartSlotCount > 1 ? ($index / ($chartSlotCount - 1)) * ($chartPlotRight - $chartPlotLeft) : ($chartPlotRight - $chartPlotLeft) / 2);
                                    $currentY = $chartPlotBottom - ($point['revenue'] / $chartMax) * ($chartPlotBottom - $chartPlotTop);
                                    $previousY = $chartPlotBottom - ($point['previous_revenue'] / $chartMax) * ($chartPlotBottom - $chartPlotTop);
                                    $showAxisLabel = $index % $axisLabelInterval === 0 || $index === $chartSlotCount - 1;
                                    ?>
                                    <?php if ($chartSlotCount === 1): ?>
                                        <circle class="report-chart-dot-previous" cx="<?php echo $svgNumber($pointX); ?>" cy="<?php echo $svgNumber($previousY); ?>" r="4"></circle>
                                        <circle class="report-chart-dot-current" cx="<?php echo $svgNumber($pointX); ?>" cy="<?php echo $svgNumber($currentY); ?>" r="4"></circle>
                                    <?php endif; ?>
                                    <?php if ($showAxisLabel): ?>
                                        <text class="report-chart-axis-label" x="<?php echo $svgNumber($pointX); ?>" y="278" text-anchor="middle"><?php echo $escape($point['label']); ?></text>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </svg>
                            <div class="report-chart-legend">
                                <span><i></i><?php echo $escape($selectedPeriodLabel); ?></span>
                                <span><i class="previous-line"></i>So với: <?php echo $escape($previousPeriodLabel); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="report-panel">
                    <div class="report-panel-header"><div><h2>Trạng thái đơn hàng</h2><p>Phân bố đơn trong khoảng thời gian đã chọn</p></div></div>
                    <div class="report-status-list">
                        <?php foreach ($statusCounts as $status => $count): ?>
                            <div class="report-status-row"><span><?php echo $escape($status); ?></span><strong><?php echo number_format($count); ?></strong></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <section class="report-panel">
                <div class="report-panel-header"><div><h2>Sản phẩm bán chạy</h2><p>Xếp hạng theo số lượng trong các đơn hàng đã giao</p></div></div>
                <?php if ($topProducts === []): ?>
                    <p class="report-empty">Chưa có sản phẩm bán ra trong khoảng thời gian này.</p>
                <?php else: ?>
                    <div class="report-table-wrap">
                        <table class="report-table">
                            <thead><tr><th>Sản phẩm</th><th>Số lượng đã bán</th><th>Doanh thu sản phẩm</th></tr></thead>
                            <tbody>
                                <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td class="report-product-name"><?php echo $escape($product['product_name']); ?></td>
                                        <td><?php echo number_format((int) $product['quantity_sold']); ?></td>
                                        <td><?php echo number_format((float) $product['product_revenue'], 0, ',', '.'); ?> đ</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>
<script>
    const reportPeriod = document.getElementById('report-period');
    reportPeriod.addEventListener('change', () => {
        if (reportPeriod.value === 'custom') return;
        reportPeriod.form.requestSubmit();
    });
    document.getElementById('report-from').addEventListener('change', () => { reportPeriod.value = 'custom'; });
    document.getElementById('report-to').addEventListener('change', () => { reportPeriod.value = 'custom'; });
</script>
</body>
</html>
