<?php
require_once __DIR__ . '/../../include/session.php';
require_once __DIR__ . '/../../include/admin_roles.php';

$sender = '';
$userId = 0;
$hasCustomerSession = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer';
$isCustomerRequest = ($_POST['viewer'] ?? '') === 'customer'
    || (!isset($_POST['user_id']) && $hasCustomerSession);
if ($isCustomerRequest && $hasCustomerSession) {
    $sender = 'customer';
    $userId = (int) $_SESSION['user_id'];
} elseif (isset($_SESSION['admin_user_id'])) {
    require_once __DIR__ . '/../../include/admin_guard.php';
    $sender = 'admin';
} elseif ($hasCustomerSession) {
    $sender = 'customer';
    $userId = (int) $_SESSION['user_id'];
} else {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập để gửi tệp.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/chat_helpers.php';

function attachmentJsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

const CHAT_ATTACHMENT_MAX_SIZE = 104857600;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.'], 405);
}
$postMaxSize = trim((string) ini_get('post_max_size'));
$postMaxUnit = strtolower(substr($postMaxSize, -1));
$postMaxBytes = (int) $postMaxSize;
if ($postMaxUnit === 'g') {
    $postMaxBytes *= 1024 * 1024 * 1024;
} elseif ($postMaxUnit === 'm') {
    $postMaxBytes *= 1024 * 1024;
} elseif ($postMaxUnit === 'k') {
    $postMaxBytes *= 1024;
}
if ($postMaxBytes > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $postMaxBytes) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Ảnh vượt quá giới hạn tải lên của máy chủ.'], 413);
}
if (!ensureChatTable($conn)) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Chưa thể chuẩn bị khu vực chat. Vui lòng thử lại sau.'], 500);
}

$upload = $_FILES['file'] ?? null;
if (!is_array($upload)
    || !isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])
    || !is_int($upload['error'])
    || !is_string($upload['tmp_name'])
    || !is_string($upload['name'])
    || !is_numeric($upload['size'])) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Vui lòng chọn một tệp để gửi.'], 400);
}
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $uploadError = (int) $upload['error'];
    $message = $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
        ? 'Ảnh vượt quá giới hạn tải lên của máy chủ.'
        : 'Không thể nhận ảnh tải lên. Vui lòng thử lại.';
    attachmentJsonResponse(['status' => 'error', 'message' => $message], 400);
}

$temporaryPath = (string) ($upload['tmp_name'] ?? '');
if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Tệp tải lên không hợp lệ.'], 400);
}
$fileSize = filesize($temporaryPath);
if ($fileSize === false || $fileSize < 1 || $fileSize > CHAT_ATTACHMENT_MAX_SIZE) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Ảnh phải có dung lượng từ 1 byte đến 100 MB.'], 400);
}

$originalName = (string) ($upload['name'] ?? '');
if (!mb_check_encoding($originalName, 'UTF-8')) {
    $originalName = 'tep-tai-len';
}
$originalName = basename(str_replace('\\', '/', $originalName));
$originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? '';
$originalName = trim(mb_substr($originalName, 0, 180, 'UTF-8'));
if ($originalName === '' || $originalName === '.' || $originalName === '..') {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Tên tệp không hợp lệ.'], 400);
}

$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowedTypes = [
    'jpg' => ['mime' => ['image/jpeg'], 'type' => 'image'],
    'jpeg' => ['mime' => ['image/jpeg'], 'type' => 'image'],
    'png' => ['mime' => ['image/png'], 'type' => 'image'],
    'gif' => ['mime' => ['image/gif'], 'type' => 'image'],
    'webp' => ['mime' => ['image/webp'], 'type' => 'image'],
];
if (!isset($allowedTypes[$extension])) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Chỉ nhận ảnh JPG, PNG, GIF hoặc WEBP.'], 415);
}

$fileInfo = finfo_open(FILEINFO_MIME_TYPE);
if ($fileInfo === false) {
    error_log('Unable to initialize fileinfo for chat attachment validation.');
    attachmentJsonResponse(['status' => 'error', 'message' => 'Máy chủ chưa thể kiểm tra loại tệp.'], 500);
}
$detectedMime = finfo_file($fileInfo, $temporaryPath);
finfo_close($fileInfo);
if (!is_string($detectedMime) || !in_array($detectedMime, $allowedTypes[$extension]['mime'], true)) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Nội dung tệp không khớp với phần mở rộng được phép.'], 415);
}

if ($allowedTypes[$extension]['type'] === 'image') {
    $imageInfo = getimagesize($temporaryPath);
    if ($imageInfo === false
        || ($imageInfo['mime'] ?? '') !== $detectedMime
        || (int) $imageInfo[0] > 16000
        || (int) $imageInfo[1] > 16000
        || (int) $imageInfo[0] * (int) $imageInfo[1] > 50000000) {
        attachmentJsonResponse(['status' => 'error', 'message' => 'Ảnh không hợp lệ hoặc vượt quá 50 MP.'], 415);
    }
}

$customerUsername = '';
if ($sender === 'admin') {
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || $userId < 1) {
        attachmentJsonResponse(['status' => 'error', 'message' => 'Cuộc trò chuyện không hợp lệ.'], 400);
    }
    // Tác dụng: Xác minh khách hàng tồn tại trong bảng users (chỉ chứa tài khoản khách hàng)
    $customer = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1");
    if (!$customer) {
        attachmentJsonResponse(['status' => 'error', 'message' => 'Không thể kiểm tra khách hàng.'], 500);
    }
    $customer->bind_param('i', $userId);
    if (!$customer->execute()) {
        $customer->close();
        attachmentJsonResponse(['status' => 'error', 'message' => 'Không thể kiểm tra khách hàng.'], 500);
    }
    $customerUsername = (string) ($customer->get_result()->fetch_assoc()['username'] ?? '');
    $customer->close();
    if ($customerUsername === '') {
        attachmentJsonResponse(['status' => 'error', 'message' => 'Không tìm thấy khách hàng này.'], 404);
    }
} else {
    $customerUsername = (string) ($_SESSION['username'] ?? 'Khách');
}

$storageDirectory = chatAttachmentDirectory();
if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0750, true) && !is_dir($storageDirectory)) {
    error_log('Unable to create private chat attachment directory.');
    attachmentJsonResponse(['status' => 'error', 'message' => 'Không thể lưu tệp lúc này.'], 500);
}

$storedName = bin2hex(random_bytes(24)) . '.' . $extension;
$storedPath = $storageDirectory . DIRECTORY_SEPARATOR . $storedName;
if (!move_uploaded_file($temporaryPath, $storedPath)) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Không thể lưu tệp lúc này.'], 500);
}

$messageType = $allowedTypes[$extension]['type'];
$message = 'Ảnh: ' . $originalName;
$senderUsername = $sender === 'admin'
    ? (string) ($_SESSION['admin_username'] ?? 'Nhân viên ÉCRIN')
    : $customerUsername;
$insert = $conn->prepare(
    "INSERT INTO chat_messages (user_id, username, sender, message_type, message, attachment_path, attachment_name, attachment_mime, attachment_size)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$insert) {
    unlink($storedPath);
    attachmentJsonResponse(['status' => 'error', 'message' => 'Không thể lưu tin nhắn tệp.'], 500);
}
$insert->bind_param('isssssssi', $userId, $senderUsername, $sender, $messageType, $message, $storedName, $originalName, $detectedMime, $fileSize);
if (!$insert->execute()) {
    $insert->close();
    unlink($storedPath);
    attachmentJsonResponse(['status' => 'error', 'message' => 'Không thể lưu tin nhắn tệp.'], 500);
}
$messageId = $insert->insert_id;
$insert->close();

$read = $conn->prepare('SELECT id, username, sender, message_type, message, attachment_name, attachment_mime, attachment_size, created_at FROM chat_messages WHERE id = ? LIMIT 1');
if (!$read) {
    attachmentJsonResponse(['status' => 'error', 'message' => 'Tệp đã gửi nhưng không thể tải lại tin nhắn.'], 500);
}
$read->bind_param('i', $messageId);
if (!$read->execute()) {
    $read->close();
    attachmentJsonResponse(['status' => 'error', 'message' => 'Tệp đã gửi nhưng không thể tải lại tin nhắn.'], 500);
}
$messageRow = $read->get_result()->fetch_assoc();
$read->close();
attachmentJsonResponse(['status' => 'success', 'message' => $messageRow]);
