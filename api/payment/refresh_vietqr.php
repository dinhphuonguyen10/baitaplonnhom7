<?php

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/payment_qr_helper.php';
require_once __DIR__ . '/../../include/order_payment_helper.php';

header('Content-Type: application/json; charset=utf-8');

function respond($status, $message, $code = 400, $attempt = null)
{
    http_response_code($code);
    $response = [
        'status' => $status,
        'message' => $message
    ];
    if ($attempt !== null) {
        $response['qr_token'] = $attempt['token'];
        $response['transfer_reference'] = $attempt['reference'];
        $response['qr_payload'] = $attempt['payload'];
        $response['expires_in'] = 60;
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    respond('error', 'Vui lòng đăng nhập.', 401);
}

$paymentConfig = getVietQrPaymentConfig();
if ($paymentConfig === null) {
    respond('error', 'Cửa hàng chưa cấu hình tài khoản VietQR.', 503);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Phương thức không hợp lệ.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    respond('error', 'Dữ liệu không hợp lệ.');
}

$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    respond('error', 'Mã đơn hàng không hợp lệ.');
}

$csrfToken = (string) ($data['csrf_token'] ?? '');
if (empty($_SESSION['payment_csrf']) || !hash_equals($_SESSION['payment_csrf'], $csrfToken)) {
    respond('error', 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', 403);
}

$userId = (int) $_SESSION['user_id'];
try {
    ensureOrderPaymentDeadlineSchema($conn);
    expireQrPaymentOrder($conn, (int) $orderId);
} catch (Throwable $e) {
    error_log('VietQR expiration check failed: ' . $e->getMessage());
    respond('error', 'Không thể kiểm tra thời hạn thanh toán lúc này.', 500);
}

$stmt = $conn->prepare(
    'SELECT payment_method, payment_status, status, total_price,
            UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
     FROM orders
     WHERE id = ? AND user_id = ?
     LIMIT 1'
);
if (!$stmt) {
    error_log('VietQR refresh prepare failed: ' . $conn->error);
    respond('error', 'Hệ thống không thể tạo mã QR lúc này.', 500);
}
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$order || $order['payment_method'] !== 'BANK_QR') {
    respond('error', 'Không tìm thấy đơn hàng thanh toán QR.', 404);
}
if (in_array($order['status'], ['Đã hủy', 'Canceled'], true)) {
    respond('error', 'Đơn hàng đã bị hủy.', 409);
}
if (in_array($order['payment_status'], ['Đã thanh toán', 'Chờ xác nhận thanh toán'], true)) {
    respond('error', 'Đơn hàng đang chờ hoặc đã được cửa hàng xác nhận.', 409);
}
if (
    $order['payment_status'] === 'Thanh toán thất bại' &&
    $order['payment_deadline'] !== null &&
    (int) $order['payment_deadline'] <= time()
) {
    respond('error', 'Đã hết thời hạn thanh toán.', 409);
}

$attempt = createVietQrPaymentAttempt(
    (int) $orderId,
    (float) $order['total_price'],
    $paymentConfig
);
$_SESSION['payment_qr_attempts'][$orderId] = $attempt;

respond('success', 'Đã tạo mã QR mới.', 200, $attempt);
