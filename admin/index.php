<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';

// Tác dụng: Lấy thông tin tài khoản và vai trò từ phiên quản trị độc lập
$isManager = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$adminCurrentPage = 'index.php';

$today = new DateTimeImmutable('today');
$period = (string) ($_GET['period'] ?? 'this_month');
$validPeriods = ['today', 'yesterday', 'last_7', 'this_month', 'last_month', 'last_30', 'custom'];
if (!in_array($period, $validPeriods, true)) {
    $period = 'this_month';
}

$startInput = (string) ($_GET['from'] ?? '');
$endInput = (string) ($_GET['to'] ?? '');
$periodStart = $today->modify('first day of this month');
$periodEnd = $today;

if ($period === 'today') {
    $periodStart = $today;
    $periodEnd = $today;
} elseif ($period === 'yesterday') {
    $periodStart = $today->modify('-1 day');
    $periodEnd = $periodStart;
} elseif ($period === 'last_7') {
    $periodStart = $today->modify('-6 days');
} elseif ($period === 'last_month') {
    $periodStart = $today->modify('first day of last month');
    $periodEnd = $today->modify('last day of last month');
} elseif ($period === 'last_30') {
    $periodStart = $today->modify('-29 days');
} elseif ($period === 'custom') {
    $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
    $parsedEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
    if ($parsedStart && $parsedEnd
        && $parsedStart->format('Y-m-d') === $startInput
        && $parsedEnd->format('Y-m-d') === $endInput
        && $parsedStart <= $parsedEnd) {
        $periodStart = $parsedStart;
        $periodEnd = $parsedEnd;
    } else {
        $period = 'this_month';
        $periodStart = $today->modify('first day of this month');
        $periodEnd = $today;
    }
}
$periodEndExclusive = $periodEnd->modify('+1 day');
$rangeStart = $periodStart->format('Y-m-d 00:00:00');
$rangeEnd = $periodEndExclusive->format('Y-m-d 00:00:00');

$stats = [
    'revenue' => 0,
    'orders' => 0,
    'completed' => 0,
    'items_sold' => 0,
];
$statStmt = $conn->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN status IN ('Đã giao hàng','Đã giao') THEN total_price ELSE 0 END), 0) AS revenue,
        COUNT(*) AS orders,
        COALESCE(SUM(status IN ('Đã giao hàng','Đã giao')), 0) AS completed
     FROM orders
     WHERE created_at >= ? AND created_at < ?"
);
if ($statStmt) {
    $statStmt->bind_param('ss', $rangeStart, $rangeEnd);
    $statStmt->execute();
    $statResult = $statStmt->get_result();
    if ($statResult) {
        $stats = array_merge($stats, $statResult->fetch_assoc() ?: []);
    }
    $statStmt->close();
}

$itemStmt = $conn->prepare(
    "SELECT COALESCE(SUM(oi.quantity),0) AS items_sold
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at >= ? AND o.created_at < ?
       AND o.status NOT IN ('Đã hủy','Canceled','cancelled','huy')"
);
if ($itemStmt) {
    $itemStmt->bind_param('ss', $rangeStart, $rangeEnd);
    $itemStmt->execute();
    $itemResult = $itemStmt->get_result();
    if ($itemResult) {
        $stats = array_merge($stats, $itemResult->fetch_assoc() ?: []);
    }
    $itemStmt->close();
}
$periodDays = (int) $periodStart->diff($periodEndExclusive)->days;
$previousPeriodEndExclusive = $periodStart;
$previousPeriodStart = $periodStart->modify('-' . $periodDays . ' days');
$previousRangeStart = $previousPeriodStart->format('Y-m-d 00:00:00');
$previousRangeEnd = $previousPeriodEndExclusive->format('Y-m-d 00:00:00');
$comparisonPeriodLabel = $previousPeriodStart->format('d/m/Y') . ' - ' . $previousPeriodEndExclusive->modify('-1 day')->format('d/m/Y');
$previousStats = ['revenue' => 0, 'orders' => 0];
$previousStatStmt = $conn->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN status IN ('Đã giao hàng','Đã giao') THEN total_price ELSE 0 END), 0) AS revenue,
        COUNT(*) AS orders
     FROM orders
     WHERE created_at >= ? AND created_at < ?"
);
if ($previousStatStmt) {
    $previousStatStmt->bind_param('ss', $previousRangeStart, $previousRangeEnd);
    $previousStatStmt->execute();
    $previousStatResult = $previousStatStmt->get_result();
    if ($previousStatResult) {
        $previousStats = array_merge($previousStats, $previousStatResult->fetch_assoc() ?: []);
    }
    $previousStatStmt->close();
}
$averageOrder = (int) $stats['completed'] > 0
    ? (float) $stats['revenue'] / (int) $stats['completed']
    : 0;
$formatComparison = static function (float $current, float $previous): array {
    if ($previous == 0.0) {
        return ['class' => 'is-neutral', 'text' => $current == 0.0 ? '0%' : '—'];
    }
    $change = (($current - $previous) / abs($previous)) * 100;
    if (abs($change) < 0.05) {
        return ['class' => 'is-neutral', 'text' => '0%'];
    }
    return [
        'class' => $change > 0 ? 'is-up' : 'is-down',
        'text' => ($change > 0 ? '↑ ' : '↓ ') . number_format(abs($change), 1, ',', '.') . '%',
    ];
};
$comparisonNotes = [
    'revenue' => $formatComparison((float) $stats['revenue'], (float) $previousStats['revenue']),
    'orders' => $formatComparison((float) $stats['orders'], (float) $previousStats['orders']),
];

$statusCounts = [
    'Chờ xác nhận' => 0,
    'Đã xác nhận' => 0,
    'Chuẩn bị đơn hàng' => 0,
    'Đang vận chuyển' => 0,
    'Đang giao' => 0,
    'Đã giao hàng' => 0,
    'Đã hủy' => 0,
];
$legacyStatusMap = [
    'Đang xử lý' => 'Đã xác nhận',
    'Đang chuẩn bị' => 'Chuẩn bị đơn hàng',
    'Đã giao' => 'Đã giao hàng',
    'Canceled' => 'Đã hủy',
    'cancelled' => 'Đã hủy',
    'huy' => 'Đã hủy',
];
$statusStmt = $conn->prepare(
    "SELECT status, COUNT(*) AS order_count
     FROM orders
     WHERE created_at >= ? AND created_at < ?
     GROUP BY status"
);
if ($statusStmt) {
    $statusStmt->bind_param('ss', $rangeStart, $rangeEnd);
    $statusStmt->execute();
    $statusResult = $statusStmt->get_result();
    if ($statusResult) {
        while ($row = $statusResult->fetch_assoc()) {
            $count = (int) $row['order_count'];
            $status = (string) $row['status'];
            $normalizedStatus = $legacyStatusMap[$status] ?? $status;
            if (isset($statusCounts[$normalizedStatus])) {
                $statusCounts[$normalizedStatus] += $count;
            }
        }
    }
    $statusStmt->close();
}

$recentOrders = [];
$recentOrdersResult = $conn->query(
    "SELECT id, full_name, total_price, status, created_at
     FROM orders
     ORDER BY created_at DESC, id DESC
     LIMIT 5"
);
if ($recentOrdersResult) {
    while ($row = $recentOrdersResult->fetch_assoc()) {
        $recentOrders[] = $row;
    }
}

$topProducts = [];
$topStmt = $conn->prepare(
    "SELECT oi.product_name,
            COALESCE(MAX(p.image), '') AS image,
            COALESCE(SUM(oi.quantity),0) AS quantity_sold,
            COALESCE(SUM(oi.quantity * oi.price),0) AS product_revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE o.created_at >= ? AND o.created_at < ?
       AND o.status NOT IN ('Đã hủy','Canceled','cancelled','huy')
     GROUP BY oi.product_name
     ORDER BY quantity_sold DESC
     LIMIT 3"
);
if ($topStmt) {
    $topStmt->bind_param('ss', $rangeStart, $rangeEnd);
    $topStmt->execute();
    $topResult = $topStmt->get_result();
    if ($topResult) {
        while ($row = $topResult->fetch_assoc()) {
            $topProducts[] = $row;
        }
    }
    $topStmt->close();
}

$loadDailyRevenue = static function (mysqli $connection, string $start, string $end): array {
    $dailyRevenue = [];
    $stmt = $connection->prepare(
        "SELECT DATE(created_at) AS sale_date, COALESCE(SUM(total_price),0) AS revenue
         FROM orders
         WHERE status IN ('Đã giao hàng','Đã giao')
           AND created_at >= ? AND created_at < ?
         GROUP BY DATE(created_at)"
    );
    if ($stmt) {
        $stmt->bind_param('ss', $start, $end);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $dailyRevenue[(string) $row['sale_date']] = (float) $row['revenue'];
            }
        }
        $stmt->close();
    }
    return $dailyRevenue;
};
$dailyCurrentRevenue = $loadDailyRevenue($conn, $rangeStart, $rangeEnd);
$dailyPreviousRevenue = $loadDailyRevenue($conn, $previousRangeStart, $previousRangeEnd);
$chartDays = [];
for ($dayIndex = 0; $dayIndex < $periodDays; $dayIndex++) {
    $currentDate = $periodStart->modify('+' . $dayIndex . ' days');
    $previousDate = $previousPeriodStart->modify('+' . $dayIndex . ' days');
    $currentKey = $currentDate->format('Y-m-d');
    $previousKey = $previousDate->format('Y-m-d');
    $chartDays[] = [
        'label' => $currentDate->format('d/m'),
        'current' => $dailyCurrentRevenue[$currentKey] ?? 0.0,
        'previous' => $dailyPreviousRevenue[$previousKey] ?? 0.0,
    ];
}
$chartValues = [];
foreach ($chartDays as $chartDay) {
    $chartValues[] = $chartDay['current'];
    $chartValues[] = $chartDay['previous'];
}
$chartMax = max($chartValues);
$chartMax = $chartMax > 0 ? ceil($chartMax / 5000000) * 5000000 : 5000000;
$formatCompact = static function (float $value): string {
    if ($value >= 1000000000) return number_format($value / 1000000000, 1, ',', '.') . 't';
    if ($value >= 1000000) return number_format($value / 1000000, 0, ',', '.') . 'tr';
    if ($value >= 1000) return number_format($value / 1000, 0, ',', '.') . 'k';
    return number_format($value, 0, ',', '.') . 'đ';
};
$periodLabels = [
    'today' => 'Hôm nay',
    'yesterday' => 'Hôm qua',
    'last_7' => '7 ngày qua',
    'this_month' => 'Tháng này',
    'last_month' => 'Tháng trước',
    'last_30' => '30 ngày gần nhất',
    'custom' => 'Tùy chọn ngày',
];
$periodLabel = $periodLabels[$period];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ÉCRIN Jewelry | Giao diện quản trị</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .welcome-row { display: flex; flex-direction: column; align-items: flex-start; margin-bottom: 24px; }
        .period-filter { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 10px; margin-top: 16px; }
        .period-field { display: grid; gap: 5px; }
        .period-field label { color: #64758a; font-size: 13px; font-weight: 600; }
        .period-filter select, .period-filter input { min-height: 39px; padding: 8px 10px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #35465a; }
        .period-filter select { min-width: 150px; }
        .period-filter input { width: 145px; }
        .period-filter button { min-height: 39px; padding: 0 14px; border: 1px solid var(--navy); border-radius: 7px; background: var(--navy); color: #fff; font-weight: 600; cursor: pointer; }
        .period-filter .reset-period { display: inline-flex; min-height: 39px; align-items: center; padding: 0 14px; border: 1px solid #dce2e9; border-radius: 7px; color: var(--navy); background: #fff; font-weight: 600; }
        .period-comparison { margin: 8px 0 0; color: #718198; font-size: 12px; }
        .business-panel, .orders-panel, .best-panel, .revenue-panel { border: 1px solid #ebedf1; border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .business-panel { margin-bottom: 18px; padding: 20px 20px 18px; }
        .business-panel h2, .orders-panel h2, .best-panel h2, .revenue-panel h2 { margin: 0; color: var(--navy); font-size: 18px; }
        .business-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
        .business-card { min-height: 104px; padding: 20px 20px 16px; border-radius: 9px; background: #f1f2f4; }
        .business-label { display: flex; align-items: center; gap: 7px; color: #6f7d8d; font-size: 15px; }
        .business-value { margin-top: 9px; color: #17283d; font-size: 28px; line-height: 1; }
        .business-comparison { display: inline-flex; align-items: center; margin-left: auto; white-space: nowrap; font-size: 11px; font-weight: 600; }
        .business-comparison.is-up { color: #13845d; }
        .business-comparison.is-down { color: #c14d46; }
        .business-comparison.is-neutral { color: #718198; }
        .dashboard-middle { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; margin-bottom: 18px; }
        .orders-panel, .best-panel { min-height: 242px; padding: 20px; }
        .panel-title-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .text-link { color: #718198; font-size: 13px; white-space: nowrap; }
        .recent-order-list { display: grid; margin-top: 10px; }
        .recent-order { display: grid; grid-template-columns: minmax(0,1fr) auto; align-items: center; gap: 12px; min-height: 43px; border-bottom: 1px dashed #e5e9ee; }
        .recent-order:last-child { border-bottom: 0; }
        .recent-order-main { min-width: 0; }
        .recent-order-main strong { display: block; overflow: hidden; color: #26384d; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
        .recent-order-main small { display: block; margin-top: 3px; color: #98a3af; font-size: 11px; }
        .recent-order-meta { display: flex; align-items: flex-end; flex-direction: column; gap: 4px; }
        .recent-order-total { color: #17283d; font-size: 14px; font-weight: 700; white-space: nowrap; }
        .recent-order-status { padding: 3px 7px; border-radius: 20px; background: #f1f4f7; color: #718198; font-size: 10px; white-space: nowrap; }
        .recent-order-status.is-pending { background: #fff4df; color: #ad7100; }
        .recent-order-status.is-done { background: #e7f7f0; color: #13845d; }
        .recent-order-status.is-cancelled { background: #fff0ef; color: #c14d46; }
        .best-list { display: grid; gap: 3px; margin-top: 12px; }
        .best-item { display: grid; grid-template-columns: 38px minmax(0,1fr) auto; align-items: center; gap: 10px; min-height: 58px; }
        .product-thumb { width: 38px; height: 38px; display: grid; place-items: center; overflow: hidden; border-radius: 8px; background: #f2f4f6; }
        .product-thumb img { width: 100%; height: 100%; object-fit: cover; }
        .product-info { min-width: 0; }
        .product-info strong { display: block; overflow: hidden; color: #17283d; font-size: 14px; text-overflow: ellipsis; white-space: nowrap; }
        .product-info small { display: block; margin-top: 4px; color: #9aa4b0; font-size: 10px; }
        .mini-progress { height: 3px; margin-top: 6px; overflow: hidden; border-radius: 3px; background: #eef1f4; }
        .mini-progress i { display: block; height: 100%; border-radius: inherit; background: #89afd2; }
        .product-sales { text-align: right; }
        .product-sales strong { display: block; color: #17283d; font-size: 15px; }
        .product-sales small { color: #9aa4b0; font-size: 10px; }
        .revenue-panel { padding: 20px 20px 16px; }
        .revenue-chart { margin-top: 24px; overflow-x: auto; }
        .chart-area { display: grid; grid-template-columns: 58px minmax(0,1fr); height: 280px; }
        .chart-y { display: flex; flex-direction: column; justify-content: space-between; padding: 0 10px 24px 0; color: #718198; font-size: 13px; text-align: right; }
        .chart-plot { position: relative; height: 256px; border-bottom: 1px solid #dfe4ea; }
        .chart-grid { position: absolute; inset: 0 0 24px; display: flex; flex-direction: column; justify-content: space-between; }
        .chart-grid i { border-top: 1px solid #edf0f3; }
        .chart-bars { position: absolute; inset: 0 0 24px; display: grid; grid-template-columns: repeat(var(--chart-days), minmax(24px,1fr)); align-items: end; gap: 3px; }
        .chart-day { height: 100%; display: flex; align-items: end; justify-content: center; gap: 2px; }
        .chart-bar { width: 40%; min-width: 4px; max-width: 12px; min-height: 2px; border-radius: 2px 2px 0 0; }
        .bar-current { background: #0784f9; }
        .bar-previous { background: #91c8ff; }
        .chart-x { position: absolute; right: 0; bottom: -20px; left: 0; display: grid; grid-template-columns: repeat(var(--chart-days), minmax(24px,1fr)); color: #718198; font-size: 10px; text-align: center; }
        .chart-legend { display: flex; justify-content: center; gap: 24px; margin-top: 22px; color: #718198; font-size: 13px; }
        .chart-legend span { display: inline-flex; align-items: center; gap: 7px; }
        .chart-legend i { width: 12px; height: 12px; border-radius: 2px; }
        .legend-current { background: #0784f9; }
        .legend-previous { background: #91c8ff; }
        @media (max-width: 1050px) { .business-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } .dashboard-middle { grid-template-columns: 1fr; } }
        @media (max-width: 560px) { .business-grid { grid-template-columns: 1fr; } .welcome-row { align-items: flex-start; gap: 14px; } .period-filter, .period-field, .period-filter select, .period-filter input { width: 100%; } .period-filter button, .period-filter .reset-period { flex: 1; justify-content: center; } .chart-area { grid-template-columns: 46px minmax(0,1fr); } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Tổng quan</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Tổng quan</h1>
                    <p class="page-subtitle">Chào mừng trở lại! Đây là tình hình kinh doanh của ÉCRIN hôm nay.</p>
                </div>
                <form method="get" class="period-filter">
                    <div class="period-field">
                        <label for="dashboard-period">Thời gian</label>
                        <select name="period" id="dashboard-period">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                            <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                            <option value="last_7" <?php echo $period === 'last_7' ? 'selected' : ''; ?>>7 ngày qua</option>
                            <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                            <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>30 ngày gần nhất</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn ngày</option>
                        </select>
                    </div>
                    <div class="period-field">
                        <label for="dashboard-from">Từ ngày</label>
                        <input type="date" name="from" id="dashboard-from" value="<?php echo $escape($periodStart->format('Y-m-d')); ?>">
                    </div>
                    <div class="period-field">
                        <label for="dashboard-to">Đến ngày</label>
                        <input type="date" name="to" id="dashboard-to" value="<?php echo $escape($periodEnd->format('Y-m-d')); ?>">
                    </div>
                    <button type="submit">Áp dụng</button>
                    <a class="reset-period" href="<?php echo $escape($appBaseUrl . '/admin/index.php'); ?>">Đặt lại</a>
                </form>
                <p class="period-comparison">So với kỳ trước: <?php echo $escape($comparisonPeriodLabel); ?></p>
            </div>

            <section class="business-panel" aria-label="Kết quả kinh doanh">
                <h2>Kết quả kinh doanh</h2>
                <div class="business-grid">
                    <article class="business-card">
                        <span class="business-label">Doanh thu<?php if ($comparisonNotes['revenue']['text'] !== ''): ?><span class="business-comparison <?php echo $escape($comparisonNotes['revenue']['class']); ?>"><?php echo $escape($comparisonNotes['revenue']['text']); ?></span><?php endif; ?></span>
                        <strong class="business-value"><?php echo $escape($formatCompact((float) $stats['revenue'])); ?></strong>
                    </article>
                    <article class="business-card">
                        <span class="business-label">Tổng đơn<?php if ($comparisonNotes['orders']['text'] !== ''): ?><span class="business-comparison <?php echo $escape($comparisonNotes['orders']['class']); ?>"><?php echo $escape($comparisonNotes['orders']['text']); ?></span><?php endif; ?></span>
                        <strong class="business-value"><?php echo number_format((int) $stats['orders']); ?></strong>
                    </article>
                    <article class="business-card">
                        <span class="business-label">Giá trị trung bình đơn</span>
                        <strong class="business-value"><?php echo $escape(number_format($averageOrder / 1000000, 2, '.', '')); ?>tr</strong>
                    </article>
                    <article class="business-card">
                        <span class="business-label">Số lượng hàng bán <span title="Tổng số sản phẩm đã bán">ⓘ</span></span>
                        <strong class="business-value"><?php echo number_format((int) $stats['items_sold']); ?></strong>
                    </article>
                </div>
            </section>

            <div class="dashboard-middle">
                <section class="orders-panel">
                    <div class="panel-title-row">
                        <h2>Đơn hàng mới nhất</h2>
                        <a class="text-link" href="<?php echo $escape($appBaseUrl . '/admin/orders.php'); ?>">Quản lý đơn →</a>
                    </div>
                    <div class="recent-order-list">
                        <?php if ($recentOrders === []): ?>
                            <p class="page-subtitle">Chưa có đơn hàng nào.</p>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                                <?php
                                $orderStatus = (string) $order['status'];
                                $statusClass = in_array($orderStatus, ['Đã giao hàng', 'Đã giao'], true) ? 'is-done' : (in_array($orderStatus, ['Đã hủy', 'Canceled', 'cancelled', 'huy'], true) ? 'is-cancelled' : (in_array($orderStatus, ['Chờ xác nhận', 'Pending'], true) ? 'is-pending' : ''));
                                ?>
                                <article class="recent-order">
                                    <div class="recent-order-main">
                                        <strong>#<?php echo (int) $order['id']; ?> · <?php echo $escape($order['full_name']); ?></strong>
                                        <small><?php echo $escape(date('d/m/Y · H:i', strtotime((string) $order['created_at']))); ?></small>
                                    </div>
                                    <div class="recent-order-meta">
                                        <strong class="recent-order-total"><?php echo number_format((float) $order['total_price'], 0, ',', '.'); ?>₫</strong>
                                        <span class="recent-order-status <?php echo $escape($statusClass); ?>"><?php echo $escape($orderStatus); ?></span>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="best-panel">
                    <div class="panel-title-row">
                        <h2>Sản phẩm bán chạy</h2>
                        <a class="text-link" href="<?php echo $escape($appBaseUrl . '/admin/products.php'); ?>">Xem tất cả →</a>
                    </div>
                    <div class="best-list">
                        <?php if ($topProducts === []): ?>
                            <p class="page-subtitle">Chưa có sản phẩm bán ra trong khoảng thời gian này.</p>
                        <?php else: ?>
                            <?php foreach ($topProducts as $index => $product): ?>
                                <?php $quantity = (int) $product['quantity_sold']; ?>
                                <?php $progress = $topProducts ? min(100, $quantity / max(1, (int) $topProducts[0]['quantity_sold']) * 100) : 0; ?>
                                <article class="best-item">
                                    <div class="product-thumb">
                                        <?php if (!empty($product['image'])): ?>
                                            <img src="<?php echo $escape($appBaseUrl . '/' . ltrim($product['image'], '/')); ?>" alt="">
                                        <?php else: ?>
                                            <?php echo $index === 0 ? '◇' : ($index === 1 ? '◈' : '✦'); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <strong><?php echo $escape($product['product_name']); ?></strong>
                                        <small>Đã bán <?php echo number_format((int) $product['quantity_sold']); ?> sản phẩm</small>
                                        <div class="mini-progress"><i style="width: <?php echo $escape(number_format($progress, 1, '.', '')); ?>%"></i></div>
                                    </div>
                                    <div class="product-sales">
                                        <strong><?php echo number_format($quantity); ?></strong>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <section class="revenue-panel">
                <div class="panel-title-row"><h2>Biểu đồ doanh thu</h2></div>
                <div class="revenue-chart">
                    <div class="chart-area" style="--chart-days:<?php echo (int) $periodDays; ?>; width:max(100%, <?php echo 58 + ($periodDays * 34); ?>px)">
                        <div class="chart-y">
                            <?php for ($tick = 4; $tick >= 0; $tick--): ?>
                                <span><?php echo $escape($formatCompact($chartMax * $tick / 4)); ?></span>
                            <?php endfor; ?>
                        </div>
                        <div class="chart-plot" style="min-width:<?php echo $periodDays * 34; ?>px">
                            <div class="chart-grid">
                                <?php for ($tick = 0; $tick < 5; $tick++): ?><i></i><?php endfor; ?>
                            </div>
                            <div class="chart-bars">
                                <?php foreach ($chartDays as $chartDay): ?>
                                    <?php
                                    $currentHeight = $chartMax > 0 ? ($chartDay['current'] / $chartMax) * 100 : 0;
                                    $previousHeight = $chartMax > 0 ? ($chartDay['previous'] / $chartMax) * 100 : 0;
                                    ?>
                                    <div class="chart-day">
                                        <i class="chart-bar bar-previous" style="height:<?php echo $escape(number_format($previousHeight, 2, '.', '')); ?>%"></i>
                                        <i class="chart-bar bar-current" style="height:<?php echo $escape(number_format($currentHeight, 2, '.', '')); ?>%"></i>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="chart-x">
                                <?php foreach ($chartDays as $chartDay): ?>
                                    <span><?php echo $escape($chartDay['label']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="chart-legend">
                        <span><i class="legend-current"></i><?php echo $escape($periodLabel); ?> (<?php echo $escape($periodStart->format('d/m/Y')); ?> - <?php echo $escape($periodEnd->format('d/m/Y')); ?>)</span>
                        <span><i class="legend-previous"></i>Kỳ trước (<?php echo $escape($comparisonPeriodLabel); ?>)</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</div>
<script>
    const dashboardPeriod = document.getElementById('dashboard-period');
    dashboardPeriod.addEventListener('change', () => {
        if (dashboardPeriod.value === 'custom') return;
        dashboardPeriod.form.requestSubmit();
    });
    document.getElementById('dashboard-from').addEventListener('change', () => {
        dashboardPeriod.value = 'custom';
    });
    document.getElementById('dashboard-to').addEventListener('change', () => {
        dashboardPeriod.value = 'custom';
    });
</script>
</body>
</html>
