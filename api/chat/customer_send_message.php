<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_helpers.php';
require_once __DIR__ . '/../../include/auto_reply_helper.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Vui lòng đăng nhập tài khoản khách hàng để sử dụng chat."]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);
$message = isset($data['message']) ? trim($data['message']) : '';
$messageType = 'text';
$productId = null;

if ($message === '' || mb_strlen($message, 'UTF-8') > 2000) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Tin nhắn cần có nội dung và không quá 2.000 ký tự."]);
    exit;
}

if (isset($data['product_id'])) {
    $productId = filter_var($data['product_id'], FILTER_VALIDATE_INT);
    if ($productId === false || $productId < 1) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Sản phẩm được chia sẻ không hợp lệ."]);
        exit;
    }

    $productCheck = $conn->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    if (!$productCheck) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Không thể kiểm tra sản phẩm được chia sẻ."]);
        exit;
    }
    $productCheck->bind_param('i', $productId);
    if (!$productCheck->execute()) {
        $productCheck->close();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Không thể kiểm tra sản phẩm được chia sẻ."]);
        exit;
    }
    $productExists = $productCheck->get_result()->num_rows > 0;
    $productCheck->close();
    if (!$productExists) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Không tìm thấy sản phẩm được chia sẻ."]);
        exit;
    }
}

$userId = intval($_SESSION['user_id']);
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Khách';

if (!ensureChatTable($conn)) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Chưa thể chuẩn bị khu vực chat. Vui lòng thử lại sau."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, sender, message_type, message, product_id) VALUES (?, ?, 'customer', ?, ?, ?)");
$stmt->bind_param("isssi", $userId, $username, $messageType, $message, $productId);

if ($stmt->execute()) {
    $insertedId = $stmt->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("SELECT cm.id, cm.username, cm.sender, cm.message_type, cm.message, cm.product_id, p.name AS product_name, p.image AS product_image, p.price_sale AS product_price, cm.attachment_name, cm.attachment_mime, cm.attachment_size, cm.created_at FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id WHERE cm.id = ? LIMIT 1");
    $stmt->bind_param("i", $insertedId);
    $stmt->execute();
    $result = $stmt->get_result();
    $messageData = $result->fetch_assoc();
    $stmt->close();

    echo json_encode(["status" => "success", "message" => $messageData]);

    // Gửi tin chăm sóc một lần mỗi ngày, ở tin nhắn đầu tiên trong ngày.
    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS cnt FROM chat_messages WHERE user_id = ? AND sender = 'customer' AND DATE(created_at) = CURDATE()"
    );
    if ($countStmt) {
        $countStmt->bind_param('i', $userId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        if ((int)($countRow['cnt'] ?? 0) === 1) {
            // Tin vừa lưu là tin đầu tiên của khách trong ngày.
            sendAutoReply($conn, $userId, 'new_message');
        }
    }
} else {
    echo json_encode(["status" => "error", "message" => "Không thể gửi tin nhắn. Vui lòng thử lại sau."]);
}

$conn->close();
?>
