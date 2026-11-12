<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';
$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Tác dụng: Xác thực phiên và cấp quyền xử lý đơn hàng cho quản trị viên
$isManager  = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel  = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'orders.php';

$orders = [];
$ordersError = '';
$emailColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'email'");
if (!$emailColumn) {
    $ordersError = 'Không thể kiểm tra dữ liệu đơn hàng.';
} else {
    $emailField = $emailColumn->num_rows > 0 ? 'o.email' : "'' AS email";
    $sql = "SELECT o.id, o.full_name, o.phone, {$emailField}, o.address, o.total_price,
                   o.status, o.payment_status, o.payment_method, o.created_at,
                   oi.product_name, oi.quantity, oi.price
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            ORDER BY o.created_at DESC, o.id DESC, oi.id ASC";
    $result = $conn->query($sql);
    if (!$result) {
        $ordersError = 'Không thể tải danh sách đơn hàng.';
        error_log('Admin order list failed: ' . $conn->error);
    } else {
        while ($row = $result->fetch_assoc()) {
            $orderId = (int) $row['id'];
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'id' => $orderId,
                    'customer' => $row['full_name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'total' => (float) $row['total_price'],
                    'status' => $row['status'],
                    'payment_status' => $row['payment_status'],
                    'payment_method' => $row['payment_method'],
                    'date' => $row['created_at'],
                    'items' => [],
                ];
            }
            if ($row['product_name'] !== null) {
                $orders[$orderId]['items'][] = [
                    'name' => $row['product_name'],
                    'quantity' => (int) $row['quantity'],
                    'price' => (float) $row['price'],
                ];
            }
        }
    }
}
$statusClasses = [
    'Chờ xác nhận' => 'status-pending',
    'Đang xử lý' => 'status-pending',
    'Đang giao' => 'status-shipping',
    'Đã giao' => 'status-completed',
    'Đã hủy' => 'status-cancelled',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý đơn hàng - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .proto-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .proto-panel h2 { margin-top: 0; font-size: 1.1rem; }
        .proto-actions { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0; }
        .proto-actions button, .proto-panel button { padding: 9px 14px; border: 0; border-radius: 7px; background: var(--navy); color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        .proto-panel input, .proto-panel select { padding: 9px; border: 1px solid #d5dbe2; border-radius: 7px; font: inherit; }
        .proto-table-wrap { overflow-x: auto; }
        .proto-table { width: 100%; border-collapse: collapse; }
        .proto-table th, .proto-table td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; }
        .proto-table th { color: var(--muted); font-size: .85rem; }

        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending   { background: #fef0c7; color: #b54708; }
        .status-shipping  { background: #e0f2fe; color: #026aa2; }
        .status-completed { background: #ecfdf3; color: #027a48; }
        .status-cancelled { background: #fee4e2; color: #b42318; }
        .order-details { margin: 0; }
        .order-details summary { color: var(--navy); font-weight: 600; cursor: pointer; }
        .order-details-content { min-width: 290px; padding: 12px 0 4px; color: var(--text); white-space: normal; }
        .order-details-content p { margin: 5px 0; }
        .order-items { margin: 10px 0 0; padding-left: 18px; }
        .order-items li { margin: 5px 0; }
        .orders-empty { padding: 24px; color: var(--muted); text-align: center; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý đơn hàng</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý đơn hàng</h1>
                    <p class="page-subtitle">Theo dõi, xác nhận và xử lý đơn hàng của khách.</p>
                </div>
            </div>
            <section class="proto-panel" aria-labelledby="orders-title">
                <div class="proto-actions">
                    <input type="search" id="orders-search" placeholder="Tìm mã đơn, tên, số điện thoại...">
                    <select id="orders-status-filter">
                        <option>Tất cả trạng thái</option>
                        <option>Chờ xác nhận</option>
                        <option>Đang giao</option>
                        <option>Đã giao</option>
                        <option>Đã hủy</option>
                    </select>
                </div>
                <div class="proto-table-wrap">
                    <table class="proto-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Số điện thoại</th>
                                <th>Tổng tiền</th>
                                <th>Thời gian</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($ordersError !== ''): ?>
                            <tr><td class="orders-empty" colspan="7"><?php echo $escape($ordersError); ?></td></tr>
                        <?php elseif ($orders === []): ?>
                            <tr><td class="orders-empty" colspan="7">Chưa có đơn hàng nào.</td></tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <?php
                            $statusClass = $statusClasses[$o['status']] ?? 'status-pending';
                            $date = strtotime($o['date']);
                            $searchText = strtolower($o['customer'] . ' ' . $o['phone'] . ' ' . $o['id']);
                            ?>
                            <tr data-order-id="<?php echo $escape($o['id']); ?>"
                                data-search="<?php echo $escape($searchText); ?>"
                                data-status="<?php echo $escape($o['status']); ?>">
                                <td><strong>#<?php echo $escape($o['id']); ?></strong></td>
                                <td><?php echo $escape($o['customer']); ?></td>
                                <td><?php echo $escape($o['phone']); ?></td>
                                <td><strong><?php echo $escape(number_format($o['total'], 0, ',', '.')); ?>đ</strong></td>
                                <td style="color: var(--muted); font-size: 13px;"><?php echo $date ? $escape(date('d/m/Y H:i', $date)) : '—'; ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo $escape($o['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <details class="order-details">
                                        <summary>Chi tiết</summary>
                                        <div class="order-details-content">
                                            <p><strong>Email:</strong> <?php echo $escape($o['email'] !== '' ? $o['email'] : 'Chưa cung cấp'); ?></p>
                                            <p><strong>Địa chỉ:</strong> <?php echo $escape($o['address']); ?></p>
                                            <p><strong>Thanh toán:</strong> <?php echo $escape($o['payment_method']); ?> — <?php echo $escape($o['payment_status']); ?></p>
                                            <?php if ($o['items'] !== []): ?>
                                                <strong>Sản phẩm:</strong>
                                                <ul class="order-items">
                                                    <?php foreach ($o['items'] as $item): ?>
                                                        <li><?php echo $escape($item['name']); ?> × <?php echo $escape($item['quantity']); ?> — <?php echo $escape(number_format($item['price'], 0, ',', '.')); ?>đ / sản phẩm</li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php else: ?>
                                                <p>Đơn hàng chưa có chi tiết sản phẩm.</p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>
<script>
    const ordersSearch = document.getElementById('orders-search');
    const ordersStatusFilter = document.getElementById('orders-status-filter');
    const orderRows = Array.from(document.querySelectorAll('.proto-table tbody tr[data-order-id]'));

    function filterOrders() {
        const query = ordersSearch.value.trim().toLocaleLowerCase();
        const status = ordersStatusFilter.value;
        orderRows.forEach(row => {
            const matchesQuery = row.dataset.search.toLocaleLowerCase().includes(query);
            const matchesStatus = status === 'Tất cả trạng thái' || row.dataset.status === status;
            row.hidden = !matchesQuery || !matchesStatus;
        });
    }

    ordersSearch.addEventListener('input', filterOrders);
    ordersStatusFilter.addEventListener('change', filterOrders);
</script>
</body>
</html>
