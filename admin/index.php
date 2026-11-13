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
$orderStats = ['total' => 0, 'pending' => 0];
$orderStatsResult = $conn->query(
    "SELECT COUNT(*) AS total,
            COALESCE(SUM(status = 'Chờ xác nhận'), 0) AS pending
     FROM orders"
);
if ($orderStatsResult) {
    $orderStats = $orderStatsResult->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ÉCRIN Jewelry | Giao diện quản trị</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .dash-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 16px; margin-bottom: 28px; }
        .dash-card { padding: 20px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .dash-card span { display: block; color: var(--muted); font-size: 13px; }
        .dash-card strong { display: block; margin-top: 12px; color: var(--navy); font-size: 1.5rem; }
        .dash-card small { display: block; margin-top: 6px; color: var(--muted); font-size: 11px; }
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
                    <p class="page-subtitle">Bảng điều khiển quản trị ÉCRIN Jewelry.</p>
                </div>
            </div>
            <section class="dash-grid" aria-label="Thẻ thống kê">
                <article class="dash-card"><span>Doanh thu hôm nay</span><strong>—</strong></article>
                <article class="dash-card">
                    <span>Đơn hàng</span>
                    <strong><?php echo $escape(number_format((int) $orderStats['total'])); ?></strong>
                    <small><?php echo $escape(number_format((int) $orderStats['pending'])); ?> đơn chờ xác nhận</small>
                </article>
                <article class="dash-card"><span>Sản phẩm</span><strong>—</strong></article>
                <article class="dash-card"><span>Khách hàng</span><strong>—</strong></article>
            </section>
        </section>
    </main>
</div>
</body>
</html>
