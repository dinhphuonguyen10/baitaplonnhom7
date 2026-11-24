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
    'pending' => ['Chờ xác nhận', 'Pending'],
    'confirmed' => ['Đã xác nhận', 'Đang xử lý'],
    'preparing' => ['Đang chuẩn bị hàng', 'Chuẩn bị đơn hàng', 'Đang chuẩn bị'],
    'shipping' => ['Đang giao hàng', 'Đang vận chuyển', 'Đang giao'],
    'delivered' => ['Đã giao hàng', 'Đã giao'],
    'completed' => ['Hoàn thành'],
    'cancelled' => ['Đã hủy', 'Canceled', 'cancelled', 'huy'],
];
$statusFilter = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : 'all';
if ($statusFilter !== 'all' && !isset($statusGroups[$statusFilter])) {
    $respond(400, ['status' => 'error', 'message' => 'Bộ lọc trạng thái không hợp lệ.']);
}

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$search = ltrim($search, '#');
if ($search !== '' && (!ctype_digit($search) || strlen($search) > 10)) {
    $respond(400, ['status' => 'error', 'message' => 'Mã đơn hàng chỉ gồm tối đa 10 chữ số.']);
}

$validateDate = static function (string $value): bool {
    if ($value === '') {
        return true;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    return $date !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $date->format('Y-m-d') === $value;
};
$fromDate = isset($_GET['from']) && is_string($_GET['from']) ? trim($_GET['from']) : '';
$toDate = isset($_GET['to']) && is_string($_GET['to']) ? trim($_GET['to']) : '';
if (!$validateDate($fromDate) || !$validateDate($toDate) || ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate)) {
    $respond(400, ['status' => 'error', 'message' => 'Khoảng thời gian lọc không hợp lệ.']);
}

$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$pageSize = filter_var($_GET['per_page'] ?? 5, FILTER_VALIDATE_INT);
if ($page === false || $page < 1 || $page > 1000000 || $pageSize === false || $pageSize < 1 || $pageSize > 20) {
    $respond(400, ['status' => 'error', 'message' => 'Thông tin phân trang không hợp lệ.']);
}

$baseWhere = ['o.user_id = ?'];
$baseTypes = 'i';
$baseParams = [$userId];
if ($fromDate !== '') {
    $baseWhere[] = 'o.created_at >= ?';
    $baseTypes .= 's';
    $baseParams[] = $fromDate . ' 00:00:00';
}
if ($toDate !== '') {
    $baseWhere[] = 'o.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $baseTypes .= 's';
    $baseParams[] = $toDate;
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
    $countStmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM orders o WHERE {$baseSql} GROUP BY status");
    $bind($countStmt, $baseTypes, $baseParams);
    $countStmt->execute();
    $counts = [
        'all' => 0,
        'pending' => 0,
        'confirmed' => 0,
        'preparing' => 0,
        'shipping' => 0,
        'delivered' => 0,
        'completed' => 0,
        'cancelled' => 0,
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
        $group = $statusToGroup[$countRow['status']] ?? null;
        if ($group !== null) {
            $counts[$group] += $statusCount;
        }
    }
    $countStmt->close();

    $where = $baseWhere;
    $types = $baseTypes;
    $params = $baseParams;
    if ($statusFilter !== 'all') {
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
                o.payment_method, o.created_at, o.cancelled_at, o.cancellation_reason
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
