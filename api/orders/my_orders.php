<?php
// api/orders/my_orders.php — Trả về danh sách đơn hàng của user đang đăng nhập
// Hỗ trợ lọc theo trạng thái qua ?status=...
require_once __DIR__ . '/../../include/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/order_payment_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SESSION['role'] ?? '') !== 'customer' || !isset($_SESSION['user_id'])) {
    $respond(401, ['status' => 'error', 'message' => 'Vui lòng đăng nhập để xem đơn hàng.']);
}

$userId = (int) $_SESSION['user_id'];
$statusGroups = [
    'processing' => ['Chờ xác nhận', 'Đã xác nhận', 'Đang chuẩn bị hàng', 'Chuẩn bị đơn hàng', 'Đang xử lý', 'Đang chuẩn bị', 'Pending'],
    'shipping'   => ['Đang giao hàng', 'Đang vận chuyển', 'Đang giao'],
    'delivered'  => ['Đã giao hàng', 'Đã giao', 'Hoàn thành'],
    'cancelled'  => ['Đã hủy', 'Canceled', 'cancelled', 'huy'],
];
$statusFilter = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : 'all';
if ($statusFilter !== 'all' && $statusFilter !== 'payment_pending' && !isset($statusGroups[$statusFilter])) {
    $respond(400, ['status' => 'error', 'message' => 'Bộ lọc trạng thái không hợp lệ.']);
}

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$search = ltrim($search, '#');
if ($search !== '' && (!ctype_digit($search) || strlen($search) > 10)) {
    $respond(400, ['status' => 'error', 'message' => 'Mã đơn hàng chỉ gồm tối đa 10 chữ số.']);
}

$timeFilter = isset($_GET['time']) && is_string($_GET['time']) ? trim($_GET['time']) : '30days';
if (!in_array($timeFilter, ['30days', '6months', 'all'], true)) {
    $timeFilter = 'all';
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageSize = filter_var($_GET['per_page'] ?? 5, FILTER_VALIDATE_INT);
if ($page === false || $page < 1 || $page > 1000000 || $pageSize === false || $pageSize < 1 || $pageSize > 20) {
    $respond(400, ['status' => 'error', 'message' => 'Thông tin phân trang không hợp lệ.']);
}

$baseWhere = ['o.user_id = ?'];
$baseTypes = 'i';
$baseParams = [$userId];

if ($timeFilter === '30days') {
    $baseWhere[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($timeFilter === '6months') {
    $baseWhere[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)';
}

if ($search !== '') {
    $baseWhere[] = 'o.id = ?';
    $baseTypes .= 's';
    $baseParams[] = $search;
}
$baseSql = implode(' AND ', $baseWhere);

$bind = static function (mysqli_stmt $stmt, string $types, array &$params): void {
    if (!$stmt->bind_param($types, ...$params)) {
        throw new RuntimeException('Không thể gắn tham số truy vấn đơn hàng.');
    }
};

try {
    ensureOrderPaymentDeadlineSchema($conn);
    $countStmt = $conn->prepare(
        "SELECT status, COUNT(*) AS total,
                SUM(
                    CASE
                        WHEN payment_method = 'BANK_QR'
                         AND payment_status = 'Thanh toán thất bại'
                         AND payment_deadline_at > NOW()
                         AND status IN ('Chờ xác nhận', 'Pending')
                        THEN 1 ELSE 0
                    END
                ) AS payment_pending
         FROM orders o
         WHERE {$baseSql}
         GROUP BY status"
    );
    $bind($countStmt, $baseTypes, $baseParams);
    $countStmt->execute();
    $counts = [
        'all' => 0,
        'processing' => 0,
        'shipping' => 0,
        'delivered' => 0,
        'cancelled' => 0,
        'payment_pending' => 0,
    ];
    $statusToGroup = [];
    foreach ($statusGroups as $group => $statuses) {
        foreach ($statuses as $status) {
            $statusToGroup[$status] = $group;
        }
    }
    $countResult = $countStmt->get_result();
    while ($countRow = $countResult->fetch_assoc()) {
        $statusCount = (int) $countRow['total'];
        $counts['all'] += $statusCount;
        $counts['payment_pending'] += (int) $countRow['payment_pending'];
        $group = $statusToGroup[$countRow['status']] ?? null;
        if ($group !== null) {
            $counts[$group] += $statusCount;
        }
    }
    $countStmt->close();

    $where = $baseWhere;
    $types = $baseTypes;
    $params = $baseParams;
    if ($statusFilter === 'payment_pending') {
        $where[] = "o.payment_method = 'BANK_QR'
            AND o.payment_status = 'Thanh toán thất bại'
            AND o.payment_deadline_at > NOW()
            AND o.status IN ('Chờ xác nhận', 'Pending')";
    } elseif ($statusFilter !== 'all') {
        $statuses = $statusGroups[$statusFilter];
        $where[] = 'o.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        $types .= str_repeat('s', count($statuses));
        array_push($params, ...$statuses);
    }
    $whereSql = implode(' AND ', $where);

    $totalStmt = $conn->prepare("SELECT COUNT(*) AS total FROM orders o WHERE {$whereSql}");
    $bind($totalStmt, $types, $params);
    $totalStmt->execute();
    $total = (int) $totalStmt->get_result()->fetch_assoc()['total'];
    $totalStmt->close();

    $totalPages = max(1, (int) ceil($total / $pageSize));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $pageSize;
    $orderStmt = $conn->prepare(
        "SELECT o.id, o.full_name, o.phone, o.email, o.address, o.total_price,
                o.shipping_fee, o.discount_amount, o.status, o.payment_status,
                o.payment_method, o.payment_deadline_at,
                UNIX_TIMESTAMP(o.payment_deadline_at) AS payment_deadline,
                UNIX_TIMESTAMP(o.expires_at) AS inventory_expires_at,
                o.created_at, o.cancelled_at, o.cancellation_reason
         FROM orders o
         WHERE {$whereSql}
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT ? OFFSET ?"
    );
    $orderParams = $params;
    $orderParams[] = $pageSize;
    $orderParams[] = $offset;
    $bind($orderStmt, $types . 'ii', $orderParams);
    $orderStmt->execute();
    $orders = [];
    $orderIds = [];
    $orderResult = $orderStmt->get_result();
    while ($row = $orderResult->fetch_assoc()) {
        $row['items'] = [];
        $orders[(int) $row['id']] = $row;
        $orderIds[] = (int) $row['id'];
    }
    $orderStmt->close();

    if ($orderIds !== []) {
        $itemPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemStmt = $conn->prepare(
            "SELECT oi.*,
                    EXISTS (
                        SELECT 1 FROM reviews r
                        WHERE r.product_id = oi.product_id AND r.user_id = ?
                    ) AS is_reviewed
             FROM order_items oi
             WHERE oi.order_id IN ({$itemPlaceholders})
             ORDER BY oi.order_id ASC, oi.id ASC"
        );
        $itemTypes = 'i' . str_repeat('i', count($orderIds));
        $itemParams = array_merge([$userId], $orderIds);
        $bind($itemStmt, $itemTypes, $itemParams);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        while ($item = $itemResult->fetch_assoc()) {
            $orders[(int) $item['order_id']]['items'][] = $item;
        }
        $itemStmt->close();
    }

    $orders = array_values($orders);
    $respond(200, [
        'status' => 'success',
        'orders' => $orders,
        'counts' => $counts,
        'pagination' => [
            'page' => $page,
            'per_page' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Customer order list failed: ' . $e->getMessage());
    $respond(500, ['status' => 'error', 'message' => 'Không thể tải danh sách đơn hàng lúc này.']);
}
