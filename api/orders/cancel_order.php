<?php
// Xử lý hủy đơn hàng
require_once __DIR__ . '/../../include/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/order_status_workflow.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$respond = static function (int $code, array $body): void {
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
};

if (($_SESSION['role'] ?? '') !== 'customer' || !isset($_SESSION['user_id'])) {
    $respond(401, ['status' => 'error', 'message' => 'Vui lòng đăng nhập để thực hiện thao tác này.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $respond(405, ['status' => 'error', 'message' => 'Phương thức yêu cầu không hợp lệ.']);
}

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) {
    $respond(400, ['status' => 'error', 'message' => 'Dữ liệu yêu cầu không hợp lệ.']);
}
$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
$reason = trim((string) ($data['reason'] ?? ''));
$csrfToken = (string) ($data['csrf_token'] ?? '');
if ($orderId === false || $orderId < 1) {
    $respond(400, ['status' => 'error', 'message' => 'Mã đơn hàng không hợp lệ.']);
}
if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
    $respond(400, ['status' => 'error', 'message' => 'Vui lòng nhập lý do hủy, tối đa 500 ký tự.']);
}
if (
    empty($_SESSION['order_cancel_csrf'])
    || !hash_equals((string) $_SESSION['order_cancel_csrf'], $csrfToken)
) {
    $respond(403, ['status' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.']);
}

$userId = (int) $_SESSION['user_id'];
$actorName = mb_substr((string) ($_SESSION['username'] ?? 'Khách hàng'), 0, 100, 'UTF-8');

try {
    $conn->begin_transaction();

    $orderStmt = $conn->prepare(
        'SELECT id, status, inventory_status
         FROM orders
         WHERE id = ? AND user_id = ?
         FOR UPDATE'
    );
    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();
    if (!$order) {
        throw new DomainException('Không tìm thấy đơn hàng của bạn.');
    }

    if (
        normalizeOrderWorkflowStatus((string) $order['status']) !== 'Chờ xác nhận'
        || !in_array('Đã hủy', getAllowedOrderStatusTransitions((string) $order['status']), true)
    ) {
        throw new DomainException('Chỉ có thể hủy đơn hàng khi đơn đang chờ xác nhận.');
    }
    $previousStatus = (string) $order['status'];

    if ($order['inventory_status'] === 'reserved') {
        if (!releaseReservedOrderInventory($conn, $orderId)) {
            throw new RuntimeException('Không thể hoàn tồn kho cho đơn hàng.');
        }
    } elseif ($order['inventory_status'] !== 'legacy') {
        throw new DomainException('Tồn kho của đơn hàng đã được xử lý; vui lòng tải lại trang.');
    }

    $updateStmt = $conn->prepare(
        "UPDATE orders
         SET status = 'Đã hủy', cancelled_at = NOW(), cancellation_reason = ?
         WHERE id = ? AND user_id = ? AND status IN ('Chờ xác nhận', 'Pending')"
    );
    $updateStmt->bind_param('sii', $reason, $orderId, $userId);
    $updateStmt->execute();
    if ($updateStmt->affected_rows !== 1) {
        $updateStmt->close();
        throw new DomainException('Trạng thái đơn hàng đã thay đổi. Vui lòng tải lại trang.');
    }
    $updateStmt->close();

    $historyStmt = $conn->prepare(
        "INSERT INTO order_status_history
            (order_id, status, old_status, new_status, note, actor_name)
         VALUES (?, 'Đã hủy', ?, 'Đã hủy', ?, ?)"
    );
    $historyStmt->bind_param('isss', $orderId, $previousStatus, $reason, $actorName);
    $historyStmt->execute();
    $historyStmt->close();

    $conn->commit();
    $respond(200, ['status' => 'success', 'message' => 'Đã hủy đơn hàng thành công.']);
} catch (DomainException $e) {
    $conn->rollback();
    $respond(409, ['status' => 'error', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Order cancellation failed: ' . $e->getMessage());
    $respond(500, ['status' => 'error', 'message' => 'Không thể hủy đơn hàng lúc này. Vui lòng thử lại sau.']);
}
