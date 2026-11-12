<?php
session_start();
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

$stmt = $conn->prepare("SELECT id, username, sender, message_type, message, attachment_name, attachment_mime, attachment_size, created_at FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
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