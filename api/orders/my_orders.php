<?php
// api/orders/my_orders.php — Trả về danh sách đơn hàng của user đang đăng nhập
// Hỗ trợ lọc theo trạng thái qua ?status=...
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Vui lòng đăng nhập để xem đơn hàng"]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';

// Xây dựng truy vấn
if ($statusFilter !== '' && $statusFilter !== 'all') {
    // Hỗ trợ nhóm trạng thái
    $statusGroups = [
        'processing' => ['Chờ xác nhận', 'Đã xác nhận', 'Chuẩn bị đơn hàng'],
        'shipping'   => ['Đang vận chuyển', 'Đang giao'],
        'delivered'  => ['Đã giao hàng', 'Đã giao'],
        'cancelled'  => ['Đã hủy'],
    ];

    if (isset($statusGroups[$statusFilter])) {
        $statuses = $statusGroups[$statusFilter];
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT * FROM orders WHERE user_id = ? AND status IN ($placeholders) ORDER BY created_at DESC";
        $types = 'i' . str_repeat('s', count($statuses));
        $params = array_merge([$user_id], $statuses);
    } else {
        // Lọc theo 1 trạng thái cụ thể
        $sql = "SELECT * FROM orders WHERE user_id = ? AND status = ? ORDER BY created_at DESC";
        $types = 'is';
        $params = [$user_id, $statusFilter];
    }
} else {
    $sql = "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC";
    $types = 'i';
    $params = [$user_id];
}

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $order_id = $row['id'];

    // Lấy chi tiết sản phẩm
    $stmt_items = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt_items->bind_param("i", $order_id);
    $stmt_items->execute();
    $result_items = $stmt_items->get_result();

    $items = [];
    while ($item_row = $result_items->fetch_assoc()) {
        $items[] = $item_row;
    }
    $stmt_items->close();

    $row['items'] = $items;
    $orders[] = $row;
}
$stmt->close();

// Đếm số lượng đơn theo từng nhóm trạng thái
$counts = [
    'all' => 0,
    'processing' => 0,
    'shipping' => 0,
    'delivered' => 0,
    'cancelled' => 0,
];

$countStmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM orders WHERE user_id = ? GROUP BY status");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$countResult = $countStmt->get_result();

$statusToGroup = [
    'Chờ xác nhận'      => 'processing',
    'Đã xác nhận'       => 'processing',
    'Chuẩn bị đơn hàng' => 'processing',
    'Đang xử lý'        => 'processing',
    'Đang chuẩn bị'     => 'processing',
    'Đang vận chuyển'    => 'shipping',
    'Đang giao'          => 'shipping',
    'Đã giao hàng'       => 'delivered',
    'Đã giao'            => 'delivered',
    'Đã hủy'             => 'cancelled',
];

while ($countRow = $countResult->fetch_assoc()) {
    $cnt = (int) $countRow['cnt'];
    $counts['all'] += $cnt;
    $group = $statusToGroup[$countRow['status']] ?? 'processing';
    $counts[$group] += $cnt;
}
$countStmt->close();

echo json_encode([
    "status" => "success",
    "orders" => $orders,
    "counts" => $counts,
]);

$conn->close();
?>
