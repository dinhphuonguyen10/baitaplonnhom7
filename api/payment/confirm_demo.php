<?php

require_once __DIR__ . '/../../include/session.php';

header('Content-Type: application/json; charset=utf-8');

function respond($status, $message, $code = 400)
{
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    respond('error', 'Vui lòng đăng nhập.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Phương thức không hợp lệ.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$csrfToken = is_array($data) ? (string) ($data['csrf_token'] ?? '') : '';
if (
    empty($_SESSION['payment_csrf']) ||
    !hash_equals($_SESSION['payment_csrf'], $csrfToken)
) {
    respond('error', 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', 403);
}

respond(
    'error',
    'Thanh toán VietQR được xác nhận bởi cửa hàng sau khi đối soát giao dịch.',
    409
);
