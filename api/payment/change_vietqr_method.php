<?php

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/order_payment_helper.php';

header('Content-Type: application/json; charset=utf-8');

function respond($status, $message, $code = 400)
{
    http_response_code($code);
    echo json_encode(['status' => $status, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    respond('error', 'Vui lòng đăng nhập.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Phương thức không hợp lệ.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$orderId = is_array($data)
    ? filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT)
    : false;
if ($orderId === false || $orderId < 1) {
    respond('error', 'Mã đơn hàng không hợp lệ.');
}

$csrfToken = (string) ($data['csrf_token'] ?? '');
if (empty($_SESSION['payment_csrf']) || !hash_equals($_SESSION['payment_csrf'], $csrfToken)) {
    respond('error', 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', 403);
}

$userId = (int) $_SESSION['user_id'];
$transactionStarted = false;

try {
    ensureOrderPaymentDeadlineSchema($conn);
    expireQrPaymentOrder($conn, (int) $orderId);

    $conn->begin_transaction();
    $transactionStarted = true;
    $stmt = $conn->prepare(
        'SELECT payment_method, payment_status, status, payment_deadline_at,
                payment_deadline_at <= NOW() AS deadline_passed
         FROM orders
         WHERE id = ? AND user_id = ?
         FOR UPDATE'
    );
    if (!$stmt) {
        throw new RuntimeException('Không thể kiểm tra đơn hàng.');
    }
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$order ||
        $order['payment_method'] !== 'BANK_QR' ||
        $order['payment_status'] !== 'Thanh toán thất bại' ||
        !in_array($order['status'], ['Chờ xác nhận', 'Pending'], true) ||
        (int) $order['deadline_passed'] === 1
    ) {
        $conn->rollback();
        $transactionStarted = false;
        respond('error', 'Không thể đổi phương thức: đơn không còn trong thời hạn thanh toán.', 409);
    }

    $update = $conn->prepare(
        "UPDATE orders
         SET payment_method = 'COD',
             payment_status = 'Chưa thanh toán',
             payment_deadline_at = NULL
         WHERE id = ? AND user_id = ?
           AND payment_method = 'BANK_QR'
           AND payment_status = 'Thanh toán thất bại'
           AND status IN ('Chờ xác nhận', 'Pending')
           AND payment_deadline_at > NOW()"
    );
    if (!$update) {
        throw new RuntimeException('Không thể đổi phương thức thanh toán.');
    }
    $update->bind_param('ii', $orderId, $userId);
    $update->execute();
    if ($update->affected_rows !== 1) {
        $update->close();
        throw new RuntimeException('Đơn hàng đã thay đổi, vui lòng tải lại trang.');
    }
    $update->close();

    $conn->commit();
    $transactionStarted = false;
    respond('success', 'Đã đổi đơn hàng sang thanh toán khi nhận hàng.');
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('VietQR payment method change failed: ' . $e->getMessage());
    respond('error', 'Không thể đổi phương thức thanh toán lúc này.', 500);
}

$conn->close();
