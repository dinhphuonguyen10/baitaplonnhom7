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

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$message = isset($data['message']) ? trim($data['message']) : '';
$messageType = 'text';

if ($message === '' || mb_strlen($message, 'UTF-8') > 2000) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Tin nhắn cần có nội dung và không quá 2.000 ký tự."]);
    exit;
}

$userId = intval($_SESSION['user_id']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Khách';

if (!ensureChatTable($conn)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Chưa thể chuẩn bị khu vực chat. Vui lòng thử lại sau."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, sender, message_type, message) VALUES (?, ?, 'customer', ?, ?)");
$stmt->bind_param("isss", $userId, $username, $messageType, $message);

if ($stmt->execute()) {
    $insertedId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, username, sender, message_type, message, attachment_name, attachment_mime, attachment_size, created_at FROM chat_messages WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $insertedId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messageData = $result->fetch_assoc();
    $stmt->close();

    echo json_encode(["status" => "success", "message" => $messageData]);
} else {
    echo json_encode(["status" => "error", "message" => "Không thể gửi tin nhắn. Vui lòng thử lại sau."]);
}

$conn->close();
?>