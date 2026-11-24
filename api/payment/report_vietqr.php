<?php

session_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/order_payment_helper.php';

header('Content-Type: application/json; charset=utf-8');

function respond($status, $message, $paymentStatus = null, $code = 400, $paymentDeadline = null)
{
    http_response_code($code);
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'payment_status' => $paymentStatus,
        'payment_deadline' => $paymentDeadline
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    respond('error', 'Vui lòng đăng nhập.', null, 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', 'Phương thức không hợp lệ.', null, 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    respond('error', 'Dữ liệu không hợp lệ.');
}

$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
$result = (string) ($data['result'] ?? '');
if (!$orderId || $orderId < 1 || !in_array($result, ['success', 'failed'], true)) {
    respond('error', 'Thông tin xác nhận thanh toán không hợp lệ.');
}

$csrfToken = (string) ($data['csrf_token'] ?? '');
if (empty($_SESSION['payment_csrf']) || !hash_equals($_SESSION['payment_csrf'], $csrfToken)) {
    respond('error', 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.', null, 403);
}

$qrToken = (string) ($data['qr_token'] ?? '');
$userId = (int) $_SESSION['user_id'];
$transactionStarted = false;

try {
    ensureOrderPaymentDeadlineSchema($conn);
    expireQrPaymentOrder($conn, (int) $orderId);

    $conn->begin_transaction();
    $transactionStarted = true;
    $stmt = $conn->prepare(
        'SELECT id, payment_method, payment_status, status, payment_deadline_at,
                UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
         FROM orders
         WHERE id = ? AND user_id = ?
         FOR UPDATE'
    );
    if (!$stmt) {
        throw new RuntimeException('Không thể kiểm tra đơn hàng thanh toán.');
    }

    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order || $order['payment_method'] !== 'BANK_QR') {
        $conn->rollback();
        $transactionStarted = false;
        respond('error', 'Không tìm thấy đơn hàng thanh toán QR.', null, 404);
    }
    if (in_array($order['status'], ['Đã hủy', 'Canceled', 'cancelled', 'huy'], true)) {
        $conn->rollback();
        $transactionStarted = false;
        respond('error', 'Đơn hàng đã hết thời hạn hoặc đã bị hủy.', null, 409);
    }
    if ($order['payment_status'] === 'Đã thanh toán') {
        $conn->commit();
        $transactionStarted = false;
        if ($result === 'success') {
            respond('success', 'Cửa hàng đã xác nhận khoản thanh toán này.', 'Đã thanh toán', 200);
        }
        respond('error', 'Đơn hàng đã được xác nhận thanh toán.', 'Đã thanh toán', 409);
    }
    if (
        $result === 'success' &&
        $order['payment_status'] === 'Chờ xác nhận thanh toán'
    ) {
        $conn->commit();
        $transactionStarted = false;
        respond(
            'success',
            'Yêu cầu thanh toán của bạn đang chờ cửa hàng xác nhận.',
            'Chờ xác nhận thanh toán',
            200
        );
    }

    $reopeningFailedReport = (
        $result === 'failed' &&
        $order['payment_status'] === 'Chờ xác nhận thanh toán'
    );
    if (
        $result === 'failed' &&
        !in_array($order['status'], ['Chờ xác nhận', 'Pending'], true)
    ) {
        $conn->rollback();
        $transactionStarted = false;
        respond('error', 'Đơn hàng đã được xử lý nên không thể mở lại thời hạn thanh toán.', null, 409);
    }

    if (
        $result === 'success' &&
        $order['payment_status'] === 'Thanh toán thất bại' &&
        $order['payment_deadline'] !== null &&
        (int) $order['payment_deadline'] <= time()
    ) {
        $conn->rollback();
        $transactionStarted = false;
        expireQrPaymentOrder($conn, (int) $orderId);
        respond('error', 'Đã hết thời hạn thanh toán.', null, 409);
    }

    if (!$reopeningFailedReport) {
        $activeAttempt = $_SESSION['payment_qr_attempts'][$orderId] ?? null;
        if (
            !is_array($activeAttempt) ||
            empty($activeAttempt['token']) ||
            (int) ($activeAttempt['expires_at'] ?? 0) <= time() ||
            !hash_equals((string) $activeAttempt['token'], $qrToken)
        ) {
            $conn->rollback();
            $transactionStarted = false;
            respond('error', 'Mã QR đã hết hạn. Hãy dùng mã mới rồi gửi lại xác nhận.', null, 410);
        }
    }

    $newPaymentStatus = $result === 'success'
        ? 'Chờ xác nhận thanh toán'
        : 'Thanh toán thất bại';

    if ($newPaymentStatus === 'Thanh toán thất bại') {
        $update = $conn->prepare(
            "UPDATE orders
             SET payment_status = ?,
                 payment_deadline_at = COALESCE(
                     payment_deadline_at,
                     DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                 )
             WHERE id = ? AND user_id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status <> 'Đã thanh toán'
               AND status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')"
        );
    } else {
        $update = $conn->prepare(
            "UPDATE orders
             SET payment_status = ?,
                 payment_deadline_at = NULL
             WHERE id = ? AND user_id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status <> 'Đã thanh toán'
               AND status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')
               AND (payment_deadline_at IS NULL OR payment_deadline_at > NOW())"
        );
    }
    if (!$update) {
        throw new RuntimeException('Không thể ghi nhận kết quả thanh toán.');
    }
    $update->bind_param('sii', $newPaymentStatus, $orderId, $userId);
    $update->execute();

    if ($update->affected_rows !== 1 && $order['payment_status'] !== $newPaymentStatus) {
        $update->close();
        throw new RuntimeException('Không thể cập nhật trạng thái thanh toán.');
    }
    $update->close();

    $deadlineStmt = $conn->prepare(
        'SELECT UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
         FROM orders
         WHERE id = ?'
    );
    if (!$deadlineStmt) {
        throw new RuntimeException('Không thể kiểm tra hạn thanh toán.');
    }
    $deadlineStmt->bind_param('i', $orderId);
    $deadlineStmt->execute();
    $paymentDeadline = $deadlineStmt->get_result()->fetch_assoc()['payment_deadline'];
    $deadlineStmt->close();

    $conn->commit();
    $transactionStarted = false;

    if ($result === 'success') {
        unset($_SESSION['payment_qr_attempts'][$orderId]);
        respond(
            'success',
            'Đã gửi yêu cầu. Đơn hàng đang chờ cửa hàng xác nhận thanh toán.',
            $newPaymentStatus,
            200
        );
    }

    respond(
        'success',
        $reopeningFailedReport
            ? 'Đã mở lại thanh toán. Bạn có 30 phút để hoàn tất hoặc đổi sang thanh toán khi nhận hàng.'
            : 'Đã ghi nhận thanh toán chưa thành công. Bạn có 30 phút để thanh toán hoặc đổi phương thức.',
        $newPaymentStatus,
        200,
        $paymentDeadline === null ? null : (int) $paymentDeadline
    );
} catch (Throwable $e) {
    if ($transactionStarted) {
        $conn->rollback();
    }
    error_log('VietQR payment report failed: ' . $e->getMessage());
    respond('error', 'Không thể cập nhật trạng thái thanh toán lúc này.', null, 500);
}

$conn->close();
