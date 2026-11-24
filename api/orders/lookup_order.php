<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SESSION['role'] ?? '') !== 'customer' || !isset($_SESSION['user_id'])) {
    $respond(401, ['status' => 'error', 'message' => 'Vui lòng đăng nhập để tra cứu đơn hàng.']);
}

$query = isset($_GET['query']) && is_string($_GET['query']) ? trim($_GET['query']) : '';
$query = ltrim($query, '#');
$orderId = filter_var($query, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId < 1) {
    $respond(400, ['status' => 'error', 'message' => 'Vui lòng nhập mã đơn hàng hợp lệ.']);
}

try {
    $stmt = $conn->prepare(
        'SELECT id, full_name, phone, address, total_price, status, payment_status,
                payment_method, created_at
         FROM orders
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $userId = (int) $_SESSION['user_id'];
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        $respond(404, ['status' => 'error', 'message' => 'Không tìm thấy đơn hàng thuộc tài khoản của bạn.']);
    }

    $itemStmt = $conn->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $items = [];
    $itemResult = $itemStmt->get_result();
    while ($item = $itemResult->fetch_assoc()) {
        $items[] = $item;
    }
    $itemStmt->close();
    $order['items'] = $items;

    $respond(200, ['status' => 'success', 'orders' => [$order]]);
} catch (Throwable $e) {
    error_log('Order lookup failed: ' . $e->getMessage());
    $respond(500, ['status' => 'error', 'message' => 'Không thể tra cứu đơn hàng lúc này.']);
}
