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
    $respond(401, ['status' => 'error', 'message' => 'Vui lòng đăng nhập để xem đơn hàng.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $respond(405, ['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ.']);
}

$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT);
if ($orderId === false || $orderId < 1) {
    $respond(400, ['status' => 'error', 'message' => 'Mã đơn hàng không hợp lệ.']);
}

$userId = (int) $_SESSION['user_id'];
try {
    $orderStmt = $conn->prepare(
        'SELECT id, full_name, phone, email, address, total_price, shipping_fee,
                discount_amount, status, payment_status, payment_method, created_at,
                cancelled_at, cancellation_reason
         FROM orders
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();

    if (!$order) {
        $respond(404, ['status' => 'error', 'message' => 'Không tìm thấy đơn hàng của bạn.']);
    }

    $itemStmt = $conn->prepare(
        'SELECT oi.*,
                EXISTS (
                    SELECT 1 FROM reviews r
                    WHERE r.product_id = oi.product_id AND r.user_id = ?
                ) AS is_reviewed
         FROM order_items oi
         WHERE oi.order_id = ?
         ORDER BY oi.id ASC'
    );
    $itemStmt->bind_param('ii', $userId, $orderId);
    $itemStmt->execute();
    $items = [];
    $itemResult = $itemStmt->get_result();
    while ($item = $itemResult->fetch_assoc()) {
        $items[] = $item;
    }
    $itemStmt->close();

    $historyStmt = $conn->prepare(
        'SELECT status, note, actor_name, created_at
         FROM order_status_history
         WHERE order_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $historyStmt->bind_param('i', $orderId);
    $historyStmt->execute();
    $history = [];
    $historyResult = $historyStmt->get_result();
    while ($event = $historyResult->fetch_assoc()) {
        $history[] = $event;
    }
    $historyStmt->close();

    $order['items'] = $items;
    $order['status_history'] = $history;
    $respond(200, ['status' => 'success', 'order' => $order]);
} catch (Throwable $e) {
    error_log('Customer order detail failed: ' . $e->getMessage());
    $respond(500, ['status' => 'error', 'message' => 'Không thể tải chi tiết đơn hàng lúc này.']);
}
