<?php
require_once __DIR__ . '/../../include/admin_guard.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_helpers.php';

function chatJsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!ensureChatTable($conn)) {
    chatJsonResponse(['status' => 'error', 'message' => 'Chưa thể chuẩn bị khu vực chat. Vui lòng thử lại sau.'], 500);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'conversations') {
        $sql = "SELECT cm.user_id,
                       COALESCE(NULLIF(u.username, ''), MAX(cm.username), 'Khách hàng') AS username,
                       (SELECT latest.message FROM chat_messages latest WHERE latest.user_id = cm.user_id ORDER BY latest.id DESC LIMIT 1) AS last_message,
                       (SELECT latest.created_at FROM chat_messages latest WHERE latest.user_id = cm.user_id ORDER BY latest.id DESC LIMIT 1) AS last_at,
                       (SELECT COUNT(*) FROM chat_messages incoming
                        WHERE incoming.user_id = cm.user_id AND incoming.sender = 'customer'
                          AND incoming.is_read = 0) AS unread
                FROM chat_messages cm
                LEFT JOIN users u ON u.id = cm.user_id
                GROUP BY cm.user_id, u.username
                ORDER BY MAX(cm.id) DESC";
        $result = $conn->query($sql);
        if (!$result) {
            chatJsonResponse(['status' => 'error', 'message' => 'Không thể tải danh sách hội thoại.'], 500);
        }
        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
        chatJsonResponse(['status' => 'success', 'conversations' => $conversations]);
    }

    if ($action === 'messages') {
        $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId || $userId < 1) {
            chatJsonResponse(['status' => 'error', 'message' => 'Cuộc trò chuyện không hợp lệ.'], 400);
        }
        $stmt = $conn->prepare('SELECT cm.id, cm.username, cm.sender, cm.message_type, cm.message, cm.product_id, p.name AS product_name, p.image AS product_image, p.price_sale AS product_price, cm.attachment_name, cm.attachment_mime, cm.attachment_size, cm.created_at FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id WHERE cm.user_id = ? ORDER BY cm.id DESC LIMIT 200');
        if (!$stmt) {
            chatJsonResponse(['status' => 'error', 'message' => 'Không thể tải tin nhắn.'], 500);
        }
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            chatJsonResponse(['status' => 'error', 'message' => 'Không thể tải tin nhắn.'], 500);
        }
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
        chatJsonResponse(['status' => 'success', 'messages' => array_reverse($messages)]);
    }

    chatJsonResponse(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.'], 400);
}

if ($method === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        chatJsonResponse(['status' => 'error', 'message' => 'Yêu cầu không hợp lệ.'], 400);
    }

    if (($payload['action'] ?? '') === 'mark_read') {
        $userId = filter_var($payload['user_id'] ?? null, FILTER_VALIDATE_INT);
        $lastMessageId = filter_var($payload['last_message_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$userId || $userId < 1 || !$lastMessageId || $lastMessageId < 1) {
            chatJsonResponse(['status' => 'error', 'message' => 'Cuộc trò chuyện không hợp lệ.'], 400);
        }
        $stmt = $conn->prepare("UPDATE chat_messages
                                SET is_read = 1
                                WHERE user_id = ? AND sender = 'customer'
                                  AND is_read = 0 AND id <= ?");
        if (!$stmt) {
            chatJsonResponse(['status' => 'error', 'message' => 'Không thể cập nhật trạng thái đã đọc.'], 500);
        }
        $stmt->bind_param('ii', $userId, $lastMessageId);
        if (!$stmt->execute()) {
            $stmt->close();
            chatJsonResponse(['status' => 'error', 'message' => 'Không thể cập nhật trạng thái đã đọc.'], 500);
        }
        $stmt->close();
        chatJsonResponse(['status' => 'success']);
    }

    if (($payload['action'] ?? '') !== 'send') {
        chatJsonResponse(['status' => 'error', 'message' => 'Yêu cầu gửi tin nhắn không hợp lệ.'], 400);
    }
    $userId = filter_var($payload['user_id'] ?? null, FILTER_VALIDATE_INT);
    $message = trim((string) ($payload['message'] ?? ''));
    if (!$userId || $userId < 1) {
        chatJsonResponse(['status' => 'error', 'message' => 'Cuộc trò chuyện không hợp lệ.'], 400);
    }
    if ($message === '' || mb_strlen($message, 'UTF-8') > 2000) {
        chatJsonResponse(['status' => 'error', 'message' => 'Tin nhắn cần có nội dung và không quá 2.000 ký tự.'], 400);
    }

    // Tác dụng: Xác minh khách hàng tồn tại trong users.
    // users không có cột role, nên không được kiểm tra role = 'customer' ở đây.
    $customer = $conn->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    if (!$customer) {
        chatJsonResponse(['status' => 'error', 'message' => 'Không thể kiểm tra khách hàng.'], 500);
    }
    $customer->bind_param('i', $userId);
    if (!$customer->execute()) {
        $customer->close();
        chatJsonResponse(['status' => 'error', 'message' => 'Không thể kiểm tra khách hàng.'], 500);
    }
    $customerResult = $customer->get_result();
    $customerRow = $customerResult->fetch_assoc();
    $customer->close();
    if (!$customerRow) {
        chatJsonResponse(['status' => 'error', 'message' => 'Không tìm thấy khách hàng này.'], 404);
    }

    $adminUsername = (string) ($_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Nhân viên ÉCRIN');
    $stmt = $conn->prepare("INSERT INTO chat_messages (user_id, username, sender, message_type, message) VALUES (?, ?, 'admin', 'text', ?)");
    if (!$stmt) {
        chatJsonResponse(['status' => 'error', 'message' => 'Không thể gửi tin nhắn lúc này.'], 500);
    }
    $stmt->bind_param('iss', $userId, $adminUsername, $message);
    if (!$stmt->execute()) {
        $stmt->close();
        chatJsonResponse(['status' => 'error', 'message' => 'Không thể gửi tin nhắn lúc này.'], 500);
    }
    $messageId = $stmt->insert_id;
    $stmt->close();

    $read = $conn->prepare('SELECT cm.id, cm.username, cm.sender, cm.message_type, cm.message, cm.product_id, p.name AS product_name, p.image AS product_image, p.price_sale AS product_price, cm.attachment_name, cm.attachment_mime, cm.attachment_size, cm.created_at FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id WHERE cm.id = ? LIMIT 1');
    if (!$read) {
        chatJsonResponse(['status' => 'error', 'message' => 'Tin nhắn đã gửi nhưng không thể tải lại nội dung.'], 500);
    }
    $read->bind_param('i', $messageId);
    if (!$read->execute()) {
        $read->close();
        chatJsonResponse(['status' => 'error', 'message' => 'Tin nhắn đã gửi nhưng không thể tải lại nội dung.'], 500);
    }
    $messageRow = $read->get_result()->fetch_assoc();
    $read->close();
    chatJsonResponse(['status' => 'success', 'message' => $messageRow]);
}

chatJsonResponse(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.'], 405);
