<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../../include/admin_roles.php';

$isAdmin = false;
$hasCustomerSession = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
$isCustomerRequest = ($_GET['viewer'] ?? '') === 'customer';
$isCustomer = false;
if ($isCustomerRequest) {
    $isCustomer = $hasCustomerSession;
} elseif (isset($_SESSION['admin_user_id'])) {
    require_once __DIR__ . '/../../include/admin_guard.php';
    $isAdmin = true;
} elseif ($hasCustomerSession) {
    $isCustomer = true;
}
if (!$isCustomer && !$isAdmin) {
    http_response_code(401);
    exit('Vui lòng đăng nhập để xem tệp.');
}

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_helpers.php';

if (!ensureChatTable($conn)) {
    http_response_code(500);
    exit('Không thể tải tệp lúc này.');
}
$messageId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$messageId || $messageId < 1) {
    http_response_code(400);
    exit('Tệp không hợp lệ.');
}

if ($isCustomer) {
    $userId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT message_type, attachment_path, attachment_name, attachment_mime
                            FROM chat_messages
                            WHERE id = ? AND user_id = ? AND message_type IN ('image', 'file') LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        exit('Không thể tải tệp lúc này.');
    }
    $stmt->bind_param('ii', $messageId, $userId);
} else {
    $stmt = $conn->prepare("SELECT message_type, attachment_path, attachment_name, attachment_mime
                            FROM chat_messages
                            WHERE id = ? AND message_type IN ('image', 'file') LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        exit('Không thể tải tệp lúc này.');
    }
    $stmt->bind_param('i', $messageId);
}
if (!$stmt->execute()) {
    $stmt->close();
    http_response_code(500);
    exit('Không thể tải tệp lúc này.');
}
$attachment = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$attachment) {
    http_response_code(404);
    exit('Không tìm thấy tệp.');
}

$storedName = (string) $attachment['attachment_path'];
$storageDirectory = realpath(chatAttachmentDirectory());
if ($storageDirectory === false || $storedName === '' || basename($storedName) !== $storedName) {
    http_response_code(404);
    exit('Không tìm thấy tệp.');
}
$resolvedPath = realpath($storageDirectory . DIRECTORY_SEPARATOR . $storedName);
if ($resolvedPath === false || dirname($resolvedPath) !== $storageDirectory || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
    http_response_code(404);
    exit('Không tìm thấy tệp.');
}

$isImage = $attachment['message_type'] === 'image'
    && in_array($attachment['attachment_mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true);
$contentType = $isImage ? $attachment['attachment_mime'] : 'application/octet-stream';
$originalName = (string) $attachment['attachment_name'];
$disposition = $isImage ? 'inline' : 'attachment';
header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header("Content-Disposition: " . $disposition . "; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode($originalName));
header('Content-Length: ' . (string) filesize($resolvedPath));
readfile($resolvedPath);
