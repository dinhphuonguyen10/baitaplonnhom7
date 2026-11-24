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

$userId = (int) $_SESSION['user_id'];

try {

    // 5. Bắt đầu transaction
    $conn->begin_transaction();

    // Khóa đơn hàng để tránh xác nhận đồng thời
    $stmt = $conn->prepare(
        "SELECT id, user_id, total_price,
                payment_method, payment_status, status,
                (expires_at IS NOT NULL AND expires_at <= NOW()) AS is_expired
         FROM orders
         WHERE id = ? AND user_id = ?
         FOR UPDATE"
    );

    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();

    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new DomainException('Không tìm thấy đơn hàng của bạn.');
    }

    if ($order['payment_method'] !== 'BANK_QR') {
        throw new DomainException('Đơn hàng không sử dụng thanh toán QR.');
    }

    if (in_array($order['status'], ['Đã hủy', 'Canceled'], true)) {
        throw new DomainException('Đơn hàng đã bị hủy.');
    }

    // 6. Không cho xác nhận lại đơn đã thanh toán
    if ($order['payment_status'] === 'Đã thanh toán') {

        $conn->commit();

        response(
            'success',
            'Đơn hàng đã được thanh toán trước đó.',
            'Đã thanh toán'
        );
    }

    if (
        (int) $order['is_expired'] === 1
        && in_array($order['status'], ['Chờ xác nhận', 'Pending'], true)
    ) {
        throw new DomainException('Đơn hàng đã hết thời gian thanh toán.');
    }

    // 7. Cập nhật kết quả giao dịch
    $newPaymentStatus = $result === 'success'
        ? 'Đã thanh toán'
        : 'Thanh toán thất bại';

    // 8. Cập nhật database
    $update = $conn->prepare(
        "UPDATE orders
         SET payment_status = ?
         WHERE id = ?
           AND user_id = ?
           AND payment_method = 'BANK_QR'
           AND payment_status <> 'Đã thanh toán'"
    );

    $update->bind_param(
        'sii',
        $newPaymentStatus,
        $orderId,
        $userId
    );

    $update->execute();

    if ($update->affected_rows !== 1) {
        throw new RuntimeException('Không thể cập nhật trạng thái thanh toán.');
    }

    $update->close();

    // 9. Hoàn tất
    $conn->commit();

    response(
        'success',
        $result === 'success'
            ? 'Thanh toán thành công!'
            : 'Thanh toán thất bại!',
        $newPaymentStatus
    );

} catch (DomainException $e) {

    $conn->rollback();

    response('error', $e->getMessage());

} catch (Throwable $e) {

    $conn->rollback();

    error_log('Demo payment error: ' . $e->getMessage());

    response(
        'error',
        'Hệ thống không thể xử lý thanh toán lúc này.',
        null,
        500
    );
}

$conn->close();
?>
