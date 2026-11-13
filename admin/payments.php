<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';

$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isManager = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'payments.php';

if (
    !isset($_SESSION['admin_csrf_token'])
    || !is_string($_SESSION['admin_csrf_token'])
    || strlen($_SESSION['admin_csrf_token']) !== 64
) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['admin_csrf_token'];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $notice = ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang và thử lại.'];
    } elseif ($orderId === false || $orderId < 1) {
        $notice = ['type' => 'error', 'message' => 'Mã đơn hàng không hợp lệ.'];
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
                error_log('Preparing COD payment update failed: ' . $conn->error);
                $notice = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán lúc này.'];
            } else {
                $updateStmt->bind_param('i', $orderId);
                if (!$updateStmt->execute()) {
                    error_log('Executing COD payment update failed: ' . $updateStmt->error);
                    $notice = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán lúc này.'];
                } elseif ($updateStmt->affected_rows !== 1) {
                    $notice = [
                        'type' => 'error',
                        'message' => 'Đơn hàng không còn ở trạng thái chờ thu tiền hoặc không thể xác nhận thanh toán.',
                    ];
                } else {
                    $notice = ['type' => 'success', 'message' => 'Đã xác nhận thu tiền COD cho đơn hàng.'];
                }
                $updateStmt->close();
            }
        } catch (mysqli_sql_exception $e) {
            error_log('COD payment update failed: ' . $e->getMessage());
            $notice = ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán lúc này.'];
        }
    }

    $_SESSION['payment_notice'] = $notice;
    header('Location: ' . $appBaseUrl . '/admin/payments.php');
    exit;
}

if (isset($_SESSION['payment_notice']) && is_array($_SESSION['payment_notice'])) {
    $notice = $_SESSION['payment_notice'];
    unset($_SESSION['payment_notice']);
}

$payments = [];
$paymentsError = '';
$pendingCount = 0;
$paidCount = 0;
$cancelledStatuses = ['Đã hủy', 'Canceled', 'cancelled', 'huy'];
try {
    $result = $conn->query(
        "SELECT id, full_name, phone, total_price, status, payment_status, created_at
         FROM orders
         WHERE payment_method = 'COD'
         ORDER BY created_at DESC, id DESC"
    );
    if (!$result) {
        $paymentsError = 'Không thể tải danh sách thanh toán.';
        error_log('Admin COD payment list failed: ' . $conn->error);
    } else {
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
            if (
                $row['payment_status'] === 'Chưa thanh toán'
                && !in_array($row['status'], $cancelledStatuses, true)
            ) {
                $pendingCount++;
            } elseif ($row['payment_status'] === 'Đã thanh toán') {
                $paidCount++;
            }
        }
        $result->free();
    }
} catch (mysqli_sql_exception $e) {
    $paymentsError = 'Không thể tải danh sách thanh toán.';
    error_log('Admin COD payment list failed: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý thanh toán - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .payments-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .payments-panel h2 { margin-top: 0; }
        .payments-summary { display: flex; flex-wrap: wrap; gap: 12px; margin: 18px 0; }
        .payments-summary span { padding: 9px 13px; border-radius: 8px; background: #f4f6f9; color: var(--text); }
        .payments-toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0; }
        .payments-toolbar input, .payments-toolbar select { min-height: 38px; padding: 8px 10px; border: 1px solid #d5dbe2; border-radius: 7px; font: inherit; }
        .payments-table-wrap { overflow-x: auto; }
        .payments-table { width: 100%; border-collapse: collapse; }
        .payments-table th, .payments-table td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        .payments-table th { color: var(--muted); font-size: .85rem; white-space: nowrap; }
        .payment-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .payment-badge-pending { background: #fef0c7; color: #b54708; }
        .payment-badge-paid { background: #ecfdf3; color: #027a48; }
        .payment-action { padding: 8px 11px; border: 0; border-radius: 7px; background: var(--navy); color: #fff; font: inherit; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .payment-action:hover { background: var(--navy-soft); }
        .payment-muted, .payments-empty { color: var(--muted); }
        .payments-empty { padding: 24px; text-align: center; }
        .payment-notice { margin: 16px 0; padding: 12px 15px; border-radius: 8px; }
        .payment-notice-success { background: #ecfdf3; color: #027a48; }
        .payment-notice-error { background: #fee4e2; color: #b42318; }
        @media (max-width: 700px) {
            .payments-panel { padding: 16px; }
            .payments-table th, .payments-table td { padding: 10px 8px; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý thanh toán</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý thanh toán COD</h1>
                    <p class="page-subtitle">Theo dõi tiền thu khi giao hàng và xác nhận các đơn đã nhận tiền.</p>
                </div>
            </div>

            <?php if (is_array($notice) && isset($notice['message'])): ?>
                <?php $noticeType = ($notice['type'] ?? '') === 'success' ? 'success' : 'error'; ?>
                <p class="payment-notice payment-notice-<?php echo $noticeType; ?>" role="status">
                    <?php echo $escape($notice['message']); ?>
                </p>
            <?php endif; ?>

            <section class="payments-panel" aria-labelledby="payments-title">
                <h2 id="payments-title">Danh sách đơn hàng COD</h2>
                <div class="payments-summary" aria-label="Tổng quan thanh toán">
                    <span>Chờ thu tiền: <strong><?php echo $pendingCount; ?></strong></span>
                    <span>Đã thu tiền: <strong><?php echo $paidCount; ?></strong></span>
                </div>
                <div class="payments-toolbar">
                    <input type="search" id="payments-search" placeholder="Tìm mã đơn, tên, số điện thoại..." aria-label="Tìm đơn hàng">
                    <select id="payments-status-filter" aria-label="Lọc trạng thái thanh toán">
                        <option value="">Tất cả trạng thái thanh toán</option>
                        <option value="Chưa thanh toán">Chưa thanh toán</option>
                        <option value="Đã thanh toán">Đã thanh toán</option>
                    </select>
                </div>
                <div class="payments-table-wrap">
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Số điện thoại</th>
                                <th>Số tiền COD</th>
                                <th>Trạng thái đơn</th>
                                <th>Thanh toán</th>
                                <th>Ngày đặt</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($paymentsError !== ''): ?>
                            <tr><td class="payments-empty" colspan="8"><?php echo $escape($paymentsError); ?></td></tr>
                        <?php elseif ($payments === []): ?>
                            <tr><td class="payments-empty" colspan="8">Chưa có đơn hàng COD.</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                $orderId = (int) $payment['id'];
                                $paymentStatus = (string) $payment['payment_status'];
                                $orderStatus = (string) $payment['status'];
                                $isCancelled = in_array($orderStatus, $cancelledStatuses, true);
                                $date = strtotime((string) $payment['created_at']);
                                $searchText = strtolower($orderId . ' ' . $payment['full_name'] . ' ' . $payment['phone']);
                                $paymentBadgeClass = $paymentStatus === 'Đã thanh toán' ? 'payment-badge-paid' : 'payment-badge-pending';
                                ?>
                                <tr data-search="<?php echo $escape($searchText); ?>" data-payment-status="<?php echo $escape($paymentStatus); ?>">
                                    <td><strong>#<?php echo $orderId; ?></strong></td>
                                    <td><?php echo $escape($payment['full_name']); ?></td>
                                    <td><?php echo $escape($payment['phone']); ?></td>
                                    <td><strong><?php echo $escape(number_format((float) $payment['total_price'], 0, ',', '.')); ?>đ</strong></td>
                                    <td><?php echo $escape($orderStatus); ?></td>
                                    <td><span class="payment-badge <?php echo $paymentBadgeClass; ?>"><?php echo $escape($paymentStatus); ?></span></td>
                                    <td><?php echo $date ? $escape(date('d/m/Y H:i', $date)) : '—'; ?></td>
                                    <td>
                                        <?php if ($paymentStatus === 'Đã thanh toán'): ?>
                                            <span class="payment-muted">Đã xác nhận</span>
                                        <?php elseif ($isCancelled): ?>
                                            <span class="payment-muted">Đơn đã hủy</span>
                                        <?php elseif ($paymentStatus === 'Chưa thanh toán'): ?>
                                            <form method="post" action="<?php echo $escape($appBaseUrl . '/admin/payments.php'); ?>" onsubmit="return confirm('Xác nhận đã nhận đủ tiền COD cho đơn hàng này?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                                <button class="payment-action" type="submit">Xác nhận đã thu tiền</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="payment-muted">Không thể xác nhận</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>
<script>
    const paymentsSearch = document.getElementById('payments-search');
    const paymentsStatusFilter = document.getElementById('payments-status-filter');
    const paymentRows = Array.from(document.querySelectorAll('.payments-table tbody tr[data-search]'));

    function filterPayments() {
        const query = paymentsSearch.value.trim().toLocaleLowerCase();
        const status = paymentsStatusFilter.value;
        paymentRows.forEach(row => {
            const matchesQuery = row.dataset.search.toLocaleLowerCase().includes(query);
            const matchesStatus = status === '' || row.dataset.paymentStatus === status;
            row.hidden = !matchesQuery || !matchesStatus;
        });
    }

    paymentsSearch.addEventListener('input', filterPayments);
    paymentsStatusFilter.addEventListener('change', filterPayments);
</script>
</body>
</html>
