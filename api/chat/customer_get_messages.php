<?php
require_once __DIR__ . '/../../include/session.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_helpers.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Vui lòng đăng nhập tài khoản khách hàng để sử dụng chat."]);
    exit;
}

$userId = intval($_SESSION['user_id']);

if (!ensureChatTable($conn)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Chưa thể chuẩn bị khu vực chat. Vui lòng thử lại sau."]);
    exit;
}

$stmt = $conn->prepare("SELECT cm.id, cm.username, cm.sender, cm.message_type, cm.message, cm.product_id, p.name AS product_name, p.image AS product_image, CASE WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW() THEN p.price_original ELSE p.price_sale END AS product_price, cm.attachment_name, cm.attachment_mime, cm.attachment_size, cm.created_at FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id AND p.is_deleted = 0 AND p.status = 'active' AND EXISTS (SELECT 1 FROM categories c WHERE c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0) WHERE cm.user_id = ? ORDER BY cm.created_at ASC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}

echo json_encode(["status" => "success", "messages" => $messages]);

$stmt->close();
$conn->close();
?>