<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../include/order_status_workflow.php';
$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Tác dụng: Xác thực phiên và cấp quyền xử lý đơn hàng cho quản trị viên
$isManager  = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel  = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'orders.php';
$orderStatuses = getOrderWorkflowStatuses();
$cancelledStatuses = ['Đã hủy', 'Canceled', 'cancelled', 'huy'];
$orderNotice = '';
$paymentNotice = null;

/* ── CSRF tokens ── */
if (empty($_SESSION['orders_csrf'])) {
    $_SESSION['orders_csrf'] = bin2hex(random_bytes(32));
}
if (!isset($_SESSION['admin_csrf_token']) || !is_string($_SESSION['admin_csrf_token']) || strlen($_SESSION['admin_csrf_token']) !== 64) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['admin_csrf_token'];

/* ── POST: cập nhật trạng thái đơn hàng ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $token = $_POST['csrf_token'] ?? '';
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $newStatus = trim((string) ($_POST['status'] ?? ''));
    if (!is_string($token) || !hash_equals($_SESSION['orders_csrf'], $token)) {
        $orderNotice = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';
    } elseif ($orderId < 1 || !in_array($newStatus, $orderStatuses, true)) {
        $orderNotice = 'Trạng thái đơn hàng không hợp lệ.';
    } else {
        try {
            $conn->begin_transaction();
            $currentStatusStmt = $conn->prepare('SELECT status FROM orders WHERE id = ? FOR UPDATE');
            $currentStatusStmt->bind_param('i', $orderId);
            $currentStatusStmt->execute();
            $currentOrder = $currentStatusStmt->get_result()->fetch_assoc();
            $currentStatusStmt->close();
            if (!$currentOrder) {
                throw new RuntimeException('Không tìm thấy đơn hàng.');
            }

            $currentStatus = (string) $currentOrder['status'];
            $normalizedCurrentStatus = normalizeOrderWorkflowStatus($currentStatus);
            if ($normalizedCurrentStatus === $newStatus) {
                $conn->commit();
                $orderNotice = 'Đơn hàng đã ở trạng thái này.';
            } else {
                if (!in_array($newStatus, getAllowedOrderStatusTransitions($currentStatus), true)) {
                    throw new DomainException(
                        'Không thể chuyển trạng thái từ "' . $normalizedCurrentStatus . '" sang "' . $newStatus . '".'
                    );
                }

                if ($newStatus === 'Đã hủy') {
                    $updateStatusStmt = $conn->prepare(
                        "UPDATE orders
                         SET status = ?, cancelled_at = COALESCE(cancelled_at, NOW()),
                             cancellation_reason = COALESCE(NULLIF(cancellation_reason, ''), 'Hủy bởi quản trị viên')
                         WHERE id = ?"
                    );
                } else {
                    $updateStatusStmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
                }
                $updateStatusStmt->bind_param('si', $newStatus, $orderId);
                $updateStatusStmt->execute();
                if ($updateStatusStmt->affected_rows !== 1) {
                    $updateStatusStmt->close();
                    throw new RuntimeException('Không thể cập nhật trạng thái đơn hàng.');
                }
                $updateStatusStmt->close();

                $actorName = mb_substr((string) ($adminUsername !== '' ? $adminUsername : 'Quản trị viên'), 0, 100, 'UTF-8');
                $historyStmt = $conn->prepare(
                    'INSERT INTO order_status_history
                        (order_id, status, old_status, new_status, note, actor_name)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $historyNote = $newStatus === 'Đã hủy'
                    ? 'Đơn hàng được hủy bởi quản trị viên.'
                    : 'Cập nhật trạng thái bởi quản trị viên.';
                $historyStmt->bind_param('isssss', $orderId, $newStatus, $currentStatus, $newStatus, $historyNote, $actorName);
                $historyStmt->execute();
                $historyStmt->close();
                $conn->commit();
                $orderNotice = 'Đã cập nhật trạng thái đơn hàng.';
            }
        } catch (DomainException $e) {
            $conn->rollback();
            $orderNotice = $e->getMessage();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('Admin order status update failed: ' . $e->getMessage());
            $orderNotice = 'Không thể cập nhật trạng thái đơn hàng.';
        }
    }
}

/* ── POST: xác nhận thu tiền COD ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_cod') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $paymentNotice = ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.'];
    } elseif ($orderId === false || $orderId < 1) {
        $paymentNotice = ['type' => 'error', 'message' => 'Mã đơn hàng không hợp lệ.'];
    } else {
        try {
            $updateStmt = $conn->prepare(
                "UPDATE orders
                 SET payment_status = 'Đã thanh toán'
                 WHERE id = ?
                   AND payment_method = 'COD'
                   AND payment_status = 'Chưa thanh toán'
                   AND status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')"
            );
            if (!$updateStmt) {
                $paymentNotice = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán.'];
            } else {
                $updateStmt->bind_param('i', $orderId);
                if (!$updateStmt->execute()) {
                    $paymentNotice = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán.'];
                } elseif ($updateStmt->affected_rows !== 1) {
                    $paymentNotice = ['type' => 'error', 'message' => 'Đơn hàng không còn ở trạng thái chờ thu tiền.'];
                } else {
                    $paymentNotice = ['type' => 'success', 'message' => 'Đã xác nhận thu tiền COD.'];
                }
                $updateStmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log('COD payment update failed: ' . $e->getMessage());
            $paymentNotice = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán.'];
        }
    }
}

/* ── Truy vấn đơn hàng ── */
$orders = [];
$ordersError = '';
$emailColumn = $conn->query("SHOW COLUMNS FROM orders LIKE 'email'");
if (!$emailColumn) {
    $ordersError = 'Không thể kiểm tra dữ liệu đơn hàng.';
} else {
    $emailField = $emailColumn->num_rows > 0 ? 'o.email' : "'' AS email";
    $sql = "SELECT o.id, o.full_name, o.phone, {$emailField}, o.address, o.total_price,
                   o.status, o.payment_status, o.payment_method, o.created_at,
                   oi.product_name, oi.quantity, oi.price
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            ORDER BY o.created_at DESC, o.id DESC, oi.id ASC";
    $result = $conn->query($sql);
    if (!$result) {
        $ordersError = 'Không thể tải danh sách đơn hàng.';
        error_log('Admin order list failed: ' . $conn->error);
    } else {
        while ($row = $result->fetch_assoc()) {
            $orderId = (int) $row['id'];
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'id' => $orderId,
                    'customer' => $row['full_name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'total' => (float) $row['total_price'],
                    'status' => $row['status'],
                    'payment_status' => $row['payment_status'],
                    'payment_method' => $row['payment_method'],
                    'date' => $row['created_at'],
                    'items' => [],
                ];
            }
            if ($row['product_name'] !== null) {
                $orders[$orderId]['items'][] = [
                    'name' => $row['product_name'],
                    'quantity' => (int) $row['quantity'],
                    'price' => (float) $row['price'],
                ];
            }
        }
    }
}

/* ── Tính thống kê ── */
$totalOrders = count($orders);
$statCounts = [
    'pending' => 0, 'confirmed' => 0, 'preparing' => 0,
    'shipping' => 0, 'delivering' => 0, 'completed' => 0, 'cancelled' => 0,
    'cod_pending' => 0, 'cod_paid' => 0,
];
foreach ($orders as $o) {
    $st = normalizeOrderWorkflowStatus((string) $o['status']);
    match ($st) {
        'Chờ xác nhận' => $statCounts['pending']++,
        'Đã xác nhận' => $statCounts['confirmed']++,
        'Đang chuẩn bị hàng' => $statCounts['preparing']++,
        'Đang giao hàng' => $statCounts['shipping']++,
        'Đã giao hàng', 'Hoàn thành' => $statCounts['completed']++,
        'Đã hủy' => $statCounts['cancelled']++,
        default => null,
    };
    if ($o['payment_method'] === 'COD') {
        if ($o['payment_status'] === 'Chưa thanh toán' && !in_array($st, $cancelledStatuses, true)) {
            $statCounts['cod_pending']++;
        } elseif ($o['payment_status'] === 'Đã thanh toán') {
            $statCounts['cod_paid']++;
        }
    }
}

$statusClasses = [
    'Chờ xác nhận' => 'st-pending',
    'Đã xác nhận' => 'st-confirmed',
    'Đang chuẩn bị hàng' => 'st-preparing',
    'Đang giao hàng' => 'st-shipping',
    'Đã giao hàng' => 'st-completed',
    'Hoàn thành' => 'st-completed',
    'Đã hủy' => 'st-cancelled',
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý đơn hàng & thanh toán - ÉCRIN Jewelry</title>
    <meta name="description" content="Quản lý đơn hàng và thanh toán COD cho cửa hàng ÉCRIN Jewelry">
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ══════════════════════════════════════════════
           UNIFIED ORDER & PAYMENT MANAGEMENT — PREMIUM
           ══════════════════════════════════════════════ */

        .om-page { font-family: 'Inter', 'Roboto', Arial, sans-serif; }

        /* ── Notice Banners ── */
        .om-notice {
            display: flex; align-items: center; gap: 10px;
            margin: 0 0 20px; padding: 14px 18px;
            border-radius: 12px; font-size: 13px; font-weight: 600;
            animation: omSlideDown .35s cubic-bezier(.4,0,.2,1);
        }
        .om-notice-success { background: linear-gradient(135deg, #ecfdf5, #d1fae5); color: #065f46; border: 1px solid #a7f3d0; }
        .om-notice-error   { background: linear-gradient(135deg, #fef2f2, #fee2e2); color: #991b1b; border: 1px solid #fca5a5; }
        .om-notice::before { font-size: 18px; }
        .om-notice-success::before { content: '✓'; }
        .om-notice-error::before   { content: '✕'; }

        /* ── Summary Cards ── */
        .om-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }
        .om-stat {
            position: relative; overflow: hidden;
            padding: 18px 16px 16px; border-radius: 14px;
            background: #fff; border: 1px solid #f0f1f4;
            box-shadow: 0 2px 12px rgba(15,39,71,.04);
            transition: transform .22s cubic-bezier(.4,0,.2,1), box-shadow .22s;
        }
        .om-stat:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(15,39,71,.09);
        }
        .om-stat-icon {
            width: 38px; height: 38px; border-radius: 10px;
            display: grid; place-items: center; font-size: 18px;
            margin-bottom: 12px;
        }
        .om-stat-value {
            font-size: 26px; font-weight: 800;
            color: var(--navy); letter-spacing: -.5px;
            line-height: 1;
        }
        .om-stat-label {
            font-size: 11.5px; font-weight: 600;
            color: #8793a2; margin-top: 6px;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .om-stat-accent {
            position: absolute; top: 0; right: 0;
            width: 60px; height: 60px; border-radius: 0 14px 0 60px;
            opacity: .08;
        }

        /* Card color variants */
        .om-stat--total .om-stat-icon    { background: #eef2ff; color: #4f46e5; }
        .om-stat--total .om-stat-accent  { background: #4f46e5; }
        .om-stat--pending .om-stat-icon  { background: #fef3c7; color: #d97706; }
        .om-stat--pending .om-stat-accent{ background: #d97706; }
        .om-stat--active .om-stat-icon   { background: #dbeafe; color: #2563eb; }
        .om-stat--active .om-stat-accent { background: #2563eb; }
        .om-stat--done .om-stat-icon     { background: #d1fae5; color: #059669; }
        .om-stat--done .om-stat-accent   { background: #059669; }
        .om-stat--cancel .om-stat-icon   { background: #fee2e2; color: #dc2626; }
        .om-stat--cancel .om-stat-accent { background: #dc2626; }
        .om-stat--cod-wait .om-stat-icon { background: #fef0c7; color: #b54708; }
        .om-stat--cod-wait .om-stat-accent{background: #b54708; }
        .om-stat--cod-done .om-stat-icon { background: #ecfdf3; color: #027a48; }
        .om-stat--cod-done .om-stat-accent{background: #027a48; }

        /* ── Main Panel ── */
        .om-panel {
            background: #fff; border: 1px solid #ebeef2;
            border-radius: 16px; padding: 0;
            box-shadow: 0 4px 20px rgba(15,39,71,.04);
            overflow: hidden;
        }
        .om-panel-header {
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
            padding: 22px 24px 18px;
            border-bottom: 1px solid #f3f4f6;
            background: linear-gradient(180deg, #fafbfd 0%, #fff 100%);
        }
        .om-panel-title {
            font-size: 16px; font-weight: 700;
            color: var(--navy); margin: 0;
            display: flex; align-items: center; gap: 8px;
        }
        .om-panel-title-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: linear-gradient(135deg, #0f2747, #1d4169);
            color: #fff; display: grid; place-items: center;
            font-size: 15px;
        }
        .om-count-badge {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 24px; height: 22px; padding: 0 7px;
            border-radius: 20px; font-size: 11px; font-weight: 700;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: #fff;
        }

        /* ── Filters Toolbar ── */
        .om-filters {
            display: flex; flex-wrap: wrap; gap: 10px;
            padding: 16px 24px;
            background: #fafbfc; border-bottom: 1px solid #f3f4f6;
        }
        .om-search {
            flex: 1 1 240px; min-width: 200px;
            position: relative;
        }
        .om-search-icon {
            position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; font-size: 15px; pointer-events: none;
        }
        .om-search input {
            width: 100%; height: 40px;
            padding: 0 14px 0 36px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font: 500 13px/1 'Inter', sans-serif;
            color: var(--text); background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }
        .om-search input:focus {
            outline: none; border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129,140,248,.15);
        }
        .om-search input::placeholder { color: #94a3b8; }
        .om-filter-select {
            height: 40px; min-width: 170px;
            padding: 0 32px 0 12px;
            border: 1.5px solid #e2e8f0; border-radius: 10px;
            font: 500 13px/1 'Inter', sans-serif;
            color: var(--text); background: #fff;
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath d='M3 5l3 3 3-3' stroke='%2394a3b8' fill='none' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            transition: border-color .2s, box-shadow .2s;
        }
        .om-filter-select:focus {
            outline: none; border-color: #818cf8;
            box-shadow: 0 0 0 3px rgba(129,140,248,.15);
        }

        /* ── Table ── */
        .om-table-wrap { overflow-x: auto; }
        .om-table {
            width: 100%; min-width: 1050px;
            border-collapse: collapse;
        }
        .om-table thead { background: #f8f9fb; }
        .om-table th {
            padding: 13px 16px; text-align: left;
            font-size: 11px; font-weight: 700;
            color: #64748b; text-transform: uppercase;
            letter-spacing: .6px; white-space: nowrap;
            border-bottom: 1px solid #ebeef2;
        }
        .om-table td {
            padding: 14px 16px; vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13.5px; font-weight: 500;
            color: var(--text);
        }
        .om-table tbody tr {
            transition: background .15s;
        }
        .om-table tbody tr:hover {
            background: #f8faff;
        }
        .om-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Order ID */
        .om-order-id {
            font-weight: 700; color: var(--navy);
            font-variant-numeric: tabular-nums;
        }

        /* Customer name */
        .om-customer {
            font-weight: 600; color: var(--navy);
            max-width: 150px; overflow: hidden;
            text-overflow: ellipsis; white-space: nowrap;
        }

        /* Money */
        .om-money {
            font-weight: 700; color: #0f172a;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        /* Date */
        .om-date { color: #64748b; font-size: 12.5px; white-space: nowrap; }

        /* ── Status Badges ── */
        .om-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 11px; border-radius: 20px;
            font-size: 11.5px; font-weight: 600;
            white-space: nowrap;
        }
        .om-badge::before {
            content: ''; display: block;
            width: 6px; height: 6px; border-radius: 50%;
            background: currentColor; opacity: .6;
        }
        .st-pending   { background: #fef3c7; color: #92400e; }
        .st-confirmed { background: #e0e7ff; color: #3730a3; }
        .st-preparing { background: #f3e8ff; color: #7e22ce; }
        .st-shipping  { background: #e0f2fe; color: #0369a1; }
        .st-completed { background: #d1fae5; color: #065f46; }
        .st-cancelled { background: #fee2e2; color: #991b1b; }

        /* Payment badges */
        .pay-paid    { background: #d1fae5; color: #065f46; }
        .pay-pending { background: #fef3c7; color: #92400e; }

        /* ── Status Update Form (inline) ── */
        .om-status-form {
            display: inline-flex; align-items: center; gap: 6px;
        }
        .om-status-select {
            height: 32px; min-width: 148px;
            padding: 0 26px 0 9px;
            border: 1.5px solid #e2e8f0; border-radius: 8px;
            font: 500 12px/1 'Inter', sans-serif;
            color: var(--text); background: #fff;
            appearance: none; cursor: pointer;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath d='M2.5 4l2.5 2.5L7.5 4' stroke='%2394a3b8' fill='none' stroke-width='1.3' stroke-linecap='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            transition: border-color .2s;
        }
        .om-status-select:focus { outline: none; border-color: #818cf8; }
        .om-btn-save {
            height: 32px; padding: 0 12px;
            border: none; border-radius: 8px;
            background: linear-gradient(135deg, #0f2747, #1d4169);
            color: #fff; font: 600 11.5px/1 'Inter', sans-serif;
            cursor: pointer; transition: opacity .2s, transform .15s;
            white-space: nowrap;
        }
        .om-btn-save:hover { opacity: .88; transform: scale(1.03); }
        .om-btn-save:active { transform: scale(.97); }

        /* ── COD Confirm Button ── */
        .om-btn-cod {
            display: inline-flex; align-items: center; gap: 5px;
            height: 32px; padding: 0 13px;
            border: none; border-radius: 8px;
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff; font: 600 11.5px/1 'Inter', sans-serif;
            cursor: pointer; transition: opacity .2s, transform .15s;
            white-space: nowrap;
        }
        .om-btn-cod:hover { opacity: .88; transform: scale(1.03); }
        .om-btn-cod:active { transform: scale(.97); }

        .om-muted { color: #94a3b8; font-size: 12px; font-weight: 500; }

        /* ── Order Detail Modal ── */
        .om-detail-button {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 13px; border: 0; border-radius: 8px;
            font-size: 12px; font-weight: 700;
            color: #4f46e5; background: #eef2ff;
            cursor: pointer; transition: .2s ease;
        }
        .om-detail-button:hover { background: #4f46e5; color: #fff; transform: translateY(-1px); }
        .om-detail-button::before { content: '▾'; font-size: 11px; }
        .om-detail-modal {
            width: min(520px, calc(100vw - 32px));
            max-height: calc(100vh - 32px);
            padding: 0; border: 0; border-radius: 16px;
            color: #1e293b;
            box-shadow: 0 24px 80px rgba(15, 23, 42, .28);
        }
        .om-detail-modal::backdrop {
            background: rgba(15, 23, 42, .55);
            backdrop-filter: blur(3px);
        }
        .om-detail-modal-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 20px; border-bottom: 1px solid #e8edf3;
            background: #fff;
        }
        .om-detail-modal-header h3 { margin: 0; color: #102a43; font-size: 17px; }
        .om-detail-close {
            width: 34px; height: 34px; border: 0; border-radius: 50%;
            background: #f1f5f9; color: #475569;
            font-size: 20px; line-height: 1; cursor: pointer;
        }
        .om-detail-modal-body {
            max-height: calc(100vh - 110px); overflow-y: auto;
            padding: 18px 20px 20px;
            background: #fff;
        }
        .om-detail-modal-body p {
            margin: 8px 0; font-size: 13px; color: #475569;
            line-height: 1.55;
        }
        .om-detail-modal-body strong { color: #102a43; }
        .om-detail-items {
            margin: 10px 0 0; padding-left: 18px;
        }
        .om-detail-items li {
            margin: 5px 0; font-size: 13px; color: #475569;
        }

        /* ── Payment Method Tag ── */
        .om-pay-method {
            display: inline-block;
            padding: 3px 8px; border-radius: 6px;
            font-size: 10.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .4px;
        }
        .om-pay-cod  { background: #fef3c7; color: #92400e; }
        .om-pay-other { background: #e0e7ff; color: #3730a3; }

        /* ── Empty State ── */
        .om-empty {
            padding: 48px 24px; text-align: center;
            color: #94a3b8;
        }
        .om-empty-icon { font-size: 48px; margin-bottom: 12px; opacity: .4; }
        .om-empty p { font-size: 14px; margin: 0; }

        /* ── Action Cell ── */
        .om-actions-cell {
            display: flex; flex-direction: column; gap: 8px;
            min-width: 220px;
        }
        .om-action-row {
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        }

        /* ── Animations ── */
        @keyframes omSlideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Responsive ── */
        @media (max-width: 1100px) {
            .om-stats { grid-template-columns: repeat(auto-fit, minmax(135px, 1fr)); gap: 10px; }
        }
        @media (max-width: 768px) {
            .om-filters { padding: 12px 16px; }
            .om-panel-header { padding: 16px; }
            .om-stats { grid-template-columns: repeat(2, 1fr); }
            .om-stat { padding: 14px 12px; }
            .om-stat-value { font-size: 22px; }
        }
        @media (max-width: 480px) {
            .om-stats { grid-template-columns: 1fr 1fr; gap: 8px; }
            .om-stat { padding: 12px 10px; }
            .om-stat-value { font-size: 20px; }
            .om-stat-label { font-size: 10px; }
        }

        /* Cân bằng dashboard trên màn hình lớn */
        @media (min-width: 1101px) {
            .om-page {
                max-width: none;
                padding: 42px 42px 64px;
            }
            .om-stats {
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: 16px;
            }
            .om-stat {
                min-width: 0;
                min-height: 154px;
                padding: 20px 18px;
            }
            .om-stat-label {
                font-size: 11px;
                white-space: nowrap;
            }
        }
        .om-page .welcome-row {
            margin-bottom: 30px;
        }
        .om-page .welcome-row h1 {
            letter-spacing: -0.045em;
        }
        .om-panel {
            box-shadow: 0 8px 28px rgba(15, 39, 71, .055);
        }

        /* Chủ đề navy cho dashboard */
        .om-stat {
            background: linear-gradient(145deg, #0b2033 0%, #173b5d 100%);
            border-color: rgba(255, 255, 255, .08);
            box-shadow: 0 8px 22px rgba(11, 32, 51, .16);
        }
        .om-stat:hover {
            box-shadow: 0 14px 30px rgba(11, 32, 51, .24);
        }
        .om-stat-value { color: #fff; }
        .om-stat-label { color: #c7d3df; }
        .om-stat-icon,
        .om-stat--total .om-stat-icon,
        .om-stat--pending .om-stat-icon,
        .om-stat--active .om-stat-icon,
        .om-stat--done .om-stat-icon,
        .om-stat--cancel .om-stat-icon,
        .om-stat--cod-wait .om-stat-icon,
        .om-stat--cod-done .om-stat-icon {
            background: rgba(255, 255, 255, .13);
            color: #f2d28b;
        }
        .om-stat-icon svg {
            width: 21px;
            height: 21px;
            display: block;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }
        .om-stat-accent,
        .om-stat--total .om-stat-accent,
        .om-stat--pending .om-stat-accent,
        .om-stat--active .om-stat-accent,
        .om-stat--done .om-stat-accent,
        .om-stat--cancel .om-stat-accent,
        .om-stat--cod-wait .om-stat-accent,
        .om-stat--cod-done .om-stat-accent {
            background: #fff;
            opacity: .1;
        }
        .om-panel-header {
            background: linear-gradient(135deg, #0b2033 0%, #173b5d 100%);
            border-bottom-color: rgba(255, 255, 255, .12);
        }
        .om-panel-title { color: #fff; }
        .om-panel-title-icon {
            background: rgba(242, 210, 139, .18);
            color: #f2d28b;
        }
        .om-count-badge {
            background: #f2d28b;
            color: #0b2033;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Đơn hàng & Thanh toán</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content om-page">
            <div class="welcome-row">
                <div>
                    <h1>Đơn hàng & Thanh toán</h1>
                    <p class="page-subtitle">Theo dõi, xác nhận và xử lý đơn hàng & thanh toán của khách hàng.</p>
                </div>
            </div>

            <?php if ($orderNotice !== ''): ?>
                <div class="om-notice om-notice-success" role="status"><?php echo $escape($orderNotice); ?></div>
            <?php endif; ?>
            <?php if (is_array($paymentNotice) && isset($paymentNotice['message'])): ?>
                <div class="om-notice om-notice-<?php echo $paymentNotice['type'] === 'success' ? 'success' : 'error'; ?>" role="status">
                    <?php echo $escape($paymentNotice['message']); ?>
                </div>
            <?php endif; ?>

            <!-- ═══ Summary Cards ═══ -->
            <div class="om-stats">
                <div class="om-stat om-stat--total">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 4h8"/><path d="M9 3h6a1 1 0 0 1 1 1v2H8V4a1 1 0 0 1 1-1Z"/><rect x="5" y="5" width="14" height="16" rx="2"/><path d="M9 11h6M9 15h6M9 18h3"/></svg></div>
                    <div class="om-stat-value"><?php echo $totalOrders; ?></div>
                    <div class="om-stat-label">Tổng đơn hàng</div>
                </div>
                <div class="om-stat om-stat--pending">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h10M7 21h10"/><path d="M8 3c0 5 4 5 4 9s-4 4-4 9M16 3c0 5-4 5-4 9s4 4 4 9"/></svg></div>
                    <div class="om-stat-value"><?php echo $statCounts['pending']; ?></div>
                    <div class="om-stat-label">Chờ xác nhận</div>
                </div>
                <div class="om-stat om-stat--active">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 6h11v10H3z"/><path d="M14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/><path d="M14 14h7"/></svg></div>
                    <div class="om-stat-value"><?php echo $statCounts['shipping'] + $statCounts['delivering']; ?></div>
                    <div class="om-stat-label">Đang giao hàng</div>
                </div>
                <div class="om-stat om-stat--done">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8 12 3 3 5-6"/></svg></div>
                    <div class="om-stat-value"><?php echo $statCounts['completed']; ?></div>
                    <div class="om-stat-label">Đã giao / hoàn thành</div>
                </div>
                <div class="om-stat om-stat--cancel">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="m9 9 6 6M15 9l-6 6"/></svg></div>
                    <div class="om-stat-value"><?php echo $statCounts['cancelled']; ?></div>
                    <div class="om-stat-label">Đã hủy</div>
                </div>
                <div class="om-stat om-stat--cod-wait">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 7v10M15 9.5c-.6-.7-1.5-1-3-1-1.7 0-2.5.8-2.5 1.8 0 2.7 5.5 1.1 5.5 3.8 0 1.1-.9 1.9-2.7 1.9-1.3 0-2.3-.4-2.9-1.1"/></svg></div>
                    <div class="om-stat-value"><?php echo $statCounts['cod_pending']; ?></div>
                    <div class="om-stat-label">Chờ thu COD</div>
                </div>
                <div class="om-stat om-stat--cod-done">
                    <div class="om-stat-accent"></div>
                    <div class="om-stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 9 4-5h8l4 5-8 10L4 9Z"/><path d="m4 9h16M8 4l4 5 4-5M8 9l4 10 4-10"/></svg></div>
                    <div class="om-stat-value"><?php echo $statCounts['cod_paid']; ?></div>
                    <div class="om-stat-label">Đã thu COD</div>
                </div>
            </div>

            <!-- ═══ Main Table Panel ═══ -->
            <div class="om-panel">
                <div class="om-panel-header">
                    <h2 class="om-panel-title">
                        <span class="om-panel-title-icon">▤</span>
                        Danh sách đơn hàng
                        <span class="om-count-badge"><?php echo $totalOrders; ?></span>
                    </h2>
                </div>

                <div class="om-filters">
                    <div class="om-search">
                        <span class="om-search-icon">🔍</span>
                        <input type="search" id="om-search" placeholder="Tìm mã đơn, tên khách, số điện thoại..." aria-label="Tìm kiếm đơn hàng">
                    </div>
                    <select id="om-filter-status" class="om-filter-select" aria-label="Lọc trạng thái đơn">
                        <option value="">Tất cả trạng thái</option>
                        <?php foreach ($orderStatuses as $st): ?>
                            <option value="<?php echo $escape($st); ?>"><?php echo $escape($st); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="om-filter-payment" class="om-filter-select" aria-label="Lọc trạng thái thanh toán">
                        <option value="">Tất cả thanh toán</option>
                        <option value="Chưa thanh toán">Chưa thanh toán</option>
                        <option value="Đã thanh toán">Đã thanh toán</option>
                    </select>
                </div>

                <div class="om-table-wrap">
                    <table class="om-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Số điện thoại</th>
                                <th>Tổng tiền</th>
                                <th>Thời gian</th>
                                <th>Trạng thái đơn</th>
                                <th>Thanh toán</th>
                                <th style="min-width:260px">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="om-tbody">
                        <?php if ($ordersError !== ''): ?>
                            <tr><td class="om-empty" colspan="8"><?php echo $escape($ordersError); ?></td></tr>
                        <?php elseif ($orders === []): ?>
                            <tr><td colspan="8">
                                <div class="om-empty">
                                    <div class="om-empty-icon">📦</div>
                                    <p>Chưa có đơn hàng nào.</p>
                                </div>
                            </td></tr>
                        <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <?php
                            $date = strtotime($o['date']);
                            $normalizedStatus = normalizeOrderWorkflowStatus((string) $o['status']);
                            $allowedStatusTransitions = getAllowedOrderStatusTransitions((string) $o['status']);
                            $searchText = strtolower($o['customer'] . ' ' . $o['phone'] . ' ' . $o['id']);
                            $stClass = $statusClasses[$normalizedStatus] ?? 'st-pending';
                            $isCOD = $o['payment_method'] === 'COD';
                            $isPaid = $o['payment_status'] === 'Đã thanh toán';
                            $isCancelled = in_array($normalizedStatus, $cancelledStatuses, true);
                            $payClass = $isPaid ? 'pay-paid' : 'pay-pending';
                            ?>
                            <tr data-order-id="<?php echo $escape($o['id']); ?>"
                                data-search="<?php echo $escape($searchText); ?>"
                                data-status="<?php echo $escape($normalizedStatus); ?>"
                                data-payment="<?php echo $escape($o['payment_status']); ?>">
                                <td><span class="om-order-id">#<?php echo $escape($o['id']); ?></span></td>
                                <td><span class="om-customer"><?php echo $escape($o['customer']); ?></span></td>
                                <td><?php echo $escape($o['phone']); ?></td>
                                <td><span class="om-money"><?php echo $escape(number_format($o['total'], 0, ',', '.')); ?>đ</span></td>
                                <td><span class="om-date"><?php echo $date ? $escape(date('d/m/Y H:i', $date)) : '—'; ?></span></td>
                                <td><span class="om-badge <?php echo $stClass; ?>"><?php echo $escape($normalizedStatus); ?></span></td>
                                <td>
                                    <span class="om-badge <?php echo $payClass; ?>"><?php echo $escape($o['payment_status']); ?></span>
                                    <?php if ($isCOD): ?>
                                        <span class="om-pay-method om-pay-cod">COD</span>
                                    <?php else: ?>
                                        <span class="om-pay-method om-pay-other"><?php echo $escape($o['payment_method']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="om-actions-cell">
                                        <!-- Cập nhật trạng thái đơn -->
                                        <form method="post" class="om-action-row">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($_SESSION['orders_csrf']); ?>">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $o['id']; ?>">
                                            <select name="status" class="om-status-select" aria-label="Trạng thái tiếp theo đơn #<?php echo (int) $o['id']; ?>" <?php echo $allowedStatusTransitions === [] ? 'disabled' : ''; ?>>
                                                <?php if ($allowedStatusTransitions === []): ?>
                                                    <option value="" selected>Đơn đã ở trạng thái cuối</option>
                                                <?php else: ?>
                                                    <option value="" selected disabled>Chọn bước tiếp theo</option>
                                                <?php foreach ($allowedStatusTransitions as $statusOption): ?>
                                                    <option value="<?php echo $escape($statusOption); ?>"><?php echo $escape($statusOption); ?></option>
                                                <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <button type="submit" class="om-btn-save" <?php echo $allowedStatusTransitions === [] ? 'disabled' : ''; ?>>Lưu</button>
                                        </form>
                                        <!-- Xác nhận COD -->
                                        <div class="om-action-row">
                                            <?php if ($isCOD && !$isPaid && !$isCancelled && $o['payment_status'] === 'Chưa thanh toán'): ?>
                                                <form method="post" onsubmit="return confirm('Xác nhận đã nhận đủ tiền COD cho đơn #<?php echo (int)$o['id']; ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                                    <input type="hidden" name="action" value="confirm_cod">
                                                    <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                                                    <button type="submit" class="om-btn-cod">💰 Xác nhận thu COD</button>
                                                </form>
                                            <?php elseif ($isCOD && $isPaid): ?>
                                                <span class="om-muted">✓ Đã thu tiền</span>
                                            <?php elseif ($isCOD && $isCancelled): ?>
                                                <span class="om-muted">Đơn đã hủy</span>
                                            <?php endif; ?>
                                        </div>
                                        <!-- Chi tiết -->
                                        <button type="button"
                                                class="om-detail-button"
                                                data-order-detail="<?php echo (int) $o['id']; ?>">
                                            Chi tiết
                                        </button>
                                        <div class="om-detail-source"
                                             id="om-detail-source-<?php echo (int) $o['id']; ?>"
                                             hidden>
                                                <p><strong>Email:</strong> <?php echo $escape($o['email'] !== '' ? $o['email'] : 'Chưa cung cấp'); ?></p>
                                                <p><strong>Địa chỉ:</strong> <?php echo $escape($o['address']); ?></p>
                                                <p><strong>Thanh toán:</strong> <?php echo $escape($o['payment_method']); ?> — <?php echo $escape($o['payment_status']); ?></p>
                                                <?php if ($o['items'] !== []): ?>
                                                    <strong>Sản phẩm:</strong>
                                                    <ul class="om-detail-items">
                                                        <?php foreach ($o['items'] as $item): ?>
                                                            <li><?php echo $escape($item['name']); ?> × <?php echo $escape($item['quantity']); ?> — <?php echo $escape(number_format($item['price'], 0, ',', '.')); ?>đ / sp</li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php else: ?>
                                                    <p>Đơn hàng chưa có chi tiết sản phẩm.</p>
                                                <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<dialog class="om-detail-modal" id="om-detail-modal">
    <div class="om-detail-modal-header">
        <h3 id="om-detail-modal-title">Chi tiết đơn hàng</h3>
        <button type="button" class="om-detail-close" id="om-detail-close" aria-label="Đóng">×</button>
    </div>
    <div class="om-detail-modal-body" id="om-detail-modal-body"></div>
</dialog>
<script>
    /* ═══ Filtering ═══ */
    const omSearch = document.getElementById('om-search');
    const omFilterStatus = document.getElementById('om-filter-status');
    const omFilterPayment = document.getElementById('om-filter-payment');
    const omRows = Array.from(document.querySelectorAll('#om-tbody tr[data-order-id]'));

    function filterOrders() {
        const q = omSearch.value.trim().toLocaleLowerCase();
        const st = omFilterStatus.value;
        const pay = omFilterPayment.value;
        let visible = 0;

        omRows.forEach(row => {
            const matchQ = row.dataset.search.toLocaleLowerCase().includes(q);
            const matchSt = st === '' || row.dataset.status === st;
            const matchPay = pay === '' || row.dataset.payment === pay;
            const show = matchQ && matchSt && matchPay;
            row.hidden = !show;
            if (show) visible++;
        });
    }

    omSearch.addEventListener('input', filterOrders);
    omFilterStatus.addEventListener('change', filterOrders);
    omFilterPayment.addEventListener('change', filterOrders);

    /* ═══ Card click → filter shortcut ═══ */
    document.querySelectorAll('.om-stat').forEach(card => {
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => {
            const label = card.querySelector('.om-stat-label')?.textContent?.trim() || '';
            const map = {
                'Tổng đơn hàng': ['', ''],
                'Chờ xác nhận': ['Chờ xác nhận', ''],
                'Đang giao hàng': ['Đang giao hàng', ''],
                'Đã giao / hoàn thành': ['', ''],
                'Đã hủy': ['Đã hủy', ''],
                'Chờ thu COD': ['', 'Chưa thanh toán'],
                'Đã thu COD': ['', 'Đã thanh toán'],
            };
            if (map[label]) {
                omFilterStatus.value = map[label][0];
                omFilterPayment.value = map[label][1];
                filterOrders();
                document.querySelector('.om-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    /* ═══ Order Detail Modal ═══ */
    const detailModal = document.getElementById('om-detail-modal');
    const detailModalTitle = document.getElementById('om-detail-modal-title');
    const detailModalBody = document.getElementById('om-detail-modal-body');
    const detailClose = document.getElementById('om-detail-close');

    document.querySelectorAll('[data-order-detail]').forEach(button => {
        button.addEventListener('click', () => {
            const orderId = button.dataset.orderDetail;
            const source = document.getElementById('om-detail-source-' + orderId);
            detailModalTitle.textContent = 'Đơn hàng ' + orderId;
            detailModalBody.innerHTML = source ? source.innerHTML : '<p>Không có dữ liệu chi tiết.</p>';
            detailModal.showModal();
        });
    });

    detailClose.addEventListener('click', () => detailModal.close());
    detailModal.addEventListener('click', event => {
        if (event.target === detailModal) detailModal.close();
    });
</script>
</body>
</html>
