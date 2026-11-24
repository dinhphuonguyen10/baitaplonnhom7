
<?php
require_once __DIR__ . '/../include/session.php';

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../include/payment_qr_helper.php';
require_once __DIR__ . '/../include/order_payment_helper.php';

// Chỉ cho phép khách hàng đăng nhập thanh toán
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../index.php');
    exit;
}

$paymentConfig = getVietQrPaymentConfig();
if ($paymentConfig === null) {
    http_response_code(503);
    exit('Chưa cấu hình tài khoản VietQR. Vui lòng liên hệ cửa hàng.');
}

$userId = (int) $_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    http_response_code(400);
    exit('Mã đơn hàng không hợp lệ.');
}

try {
    ensureOrderPaymentDeadlineSchema($conn);
    expireQrPaymentOrder($conn, (int) $orderId);
} catch (Throwable $e) {
    error_log('VietQR expiration check failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Không thể kiểm tra thời hạn thanh toán lúc này.');
}

// Lấy đơn hàng thuộc tài khoản hiện tại
$stmt = $conn->prepare(
    "SELECT id, total_price, payment_method,
            payment_status, status, created_at, payment_deadline_at,
            UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
     FROM orders
     WHERE id = ? AND user_id = ?
     LIMIT 1"
);

$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$order || $order['payment_method'] !== 'BANK_QR') {
    http_response_code(404);
    exit('Không tìm thấy đơn hàng thanh toán QR.');
}

// Tạo token bảo vệ yêu cầu xác nhận
if (empty($_SESSION['payment_csrf'])) {
    $_SESSION['payment_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['payment_csrf'];

$baseDir = str_replace(
    '\\',
    '/',
    dirname($_SERVER['SCRIPT_NAME'] ?? '/client/payment.php')
);

$appBaseUrl = preg_replace(
    '~/(?:client|admin|api)(?:/.*)?$~',
    '',
    $baseDir
);

$appBaseUrl = rtrim($appBaseUrl, '/');

$isPaid = $order['payment_status'] === 'Đã thanh toán';
$isAwaitingConfirmation = $order['payment_status'] === 'Chờ xác nhận thanh toán';
$isCanceled = in_array($order['status'], ['Đã hủy', 'Canceled', 'cancelled', 'huy'], true);
$isFailed = $order['payment_status'] === 'Thanh toán thất bại';
$isPaymentDeadlineExpired = $isFailed
    && $order['payment_deadline'] !== null
    && (int) $order['payment_deadline'] <= time();
$showPaymentQr = !$isPaid && !$isAwaitingConfirmation && !$isCanceled && !$isPaymentDeadlineExpired;
$qrAttempt = null;
if ($showPaymentQr) {
    $qrAttempts = $_SESSION['payment_qr_attempts'] ?? [];
    $qrAttempt = $qrAttempts[$orderId] ?? null;
    if (
        !is_array($qrAttempt) ||
        empty($qrAttempt['token']) ||
        empty($qrAttempt['payload']) ||
        empty($qrAttempt['reference']) ||
        (int) ($qrAttempt['expires_at'] ?? 0) <= time()
    ) {
        $qrAttempt = createVietQrPaymentAttempt(
            (int) $order['id'],
            (float) $order['total_price'],
            $paymentConfig
        );
        $_SESSION['payment_qr_attempts'][$orderId] = $qrAttempt;
    }
}

$amountValue = (int) round((float) $order['total_price']);
$amount = number_format(
    $amountValue,
    0,
    ',',
    '.'
);

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Thanh toán QR - ÉCRIN JEWELRY</title>

    <link rel="stylesheet"
          href="<?= e($appBaseUrl) ?>/assets/style.css">

    <style>
        body {
            background:
                radial-gradient(circle at top left, rgba(36, 82, 122, .18), transparent 28rem),
                #edf2f7;
            min-height: 100vh;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
        }

        .demo-payment {
            max-width: 1120px;
            margin: 40px auto;
            background: #fff;
            border: 1px solid #e3eaf0;
            border-radius: 22px;
            color: #102a43;
            box-shadow: 0 28px 80px rgba(16, 42, 67, .14);
            overflow: hidden;
        }

        .demo-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 96px;
            padding: 22px 36px;
            background: #102a43;
            color: #fff;
        }

        .demo-brand {
            margin: 0 0 5px;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1.2px;
        }

        .demo-title {
            margin: 0;
            color: #fff;
            font-size: 13px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .demo-secure {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #dbe7f2;
            font-size: 13px;
            white-space: nowrap;
        }

        .demo-secure::before {
            content: "✓";
            display: grid;
            place-items: center;
            width: 22px;
            height: 22px;
            border: 1px solid #7f9bb4;
            border-radius: 50%;
            color: #fff;
            font-size: 12px;
        }

        .demo-layout {
            display: grid;
            grid-template-columns: 370px minmax(0, 1fr);
            min-height: 600px;
        }

        .demo-order {
            padding: 38px 32px;
            background: #f6f8fb;
            border-right: 1px solid #e4ebf2;
        }

        .demo-order-label {
            margin: 0 0 22px;
            color: #7890a5;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .demo-row {
            padding: 16px 0;
            border-bottom: 1px solid #dde6ef;
        }

        .demo-row:last-child {
            border-bottom: 0;
        }

        .demo-row span {
            display: block;
            margin-bottom: 7px;
            color: #7890a5;
            font-size: 13px;
        }

        .demo-row strong {
            color: #102a43;
            font-size: 16px;
        }

        .demo-amount {
            color: #b78a31 !important;
            font-size: 29px !important;
            letter-spacing: -.3px;
        }

        .demo-transfer {
            margin-top: 24px;
            padding: 19px;
            background: #102a43;
            border-radius: 14px;
        }

        .demo-transfer span {
            display: block;
            color: #c8d5e2;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .demo-transfer strong {
            color: #fff;
            display: block;
            font-size: 17px;
            letter-spacing: 1.1px;
            overflow-wrap: anywhere;
        }

        .demo-payment-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            text-align: center;
        }

        .demo-payment-intro {
            max-width: 500px;
            margin: 0 auto 24px;
        }

        .demo-payment-intro h2 {
            margin: 0 0 8px;
            color: #102a43;
            font-size: 23px;
        }

        .demo-payment-intro p {
            margin: 0;
            color: #667d91;
            font-size: 14px;
            line-height: 1.65;
        }

        .demo-qr h3 {
            margin: 0 0 16px;
            color: #526b80;
            font-size: 13px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .demo-qr-frame {
            display: inline-flex;
            padding: 14px;
            background: #fff;
            border: 1px solid #e1e8ee;
            border-radius: 20px;
            box-shadow: 0 16px 38px rgba(16, 42, 67, .1);
        }

        .demo-qr img {
            display: block;
            width: 260px;
            height: 260px;
            max-width: 100%;
            object-fit: contain;
        }

        .demo-qr-caption {
            margin: 14px 0 0;
            color: #7890a5;
            font-size: 12px;
        }

        .demo-qr-expiry {
            margin: 14px 0 0;
            color: #526b80;
            font-size: 14px;
        }

        .demo-qr-expiry strong {
            color: #b04a3f;
            font-variant-numeric: tabular-nums;
        }

        .demo-qr-code {
            display: block;
            margin-top: 8px;
            color: #526b80;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .demo-payment-status {
            max-width: 510px;
            margin: 26px auto 0;
            padding: 15px 18px;
            border: 1px solid #d8e8dc;
            border-radius: 12px;
            background: #f2faf4;
            color: #246746;
            font-size: 14px;
            line-height: 1.55;
        }

        .demo-payment-status strong {
            display: block;
            margin-bottom: 4px;
        }

        .demo-payment-status.is-error {
            border-color: #efcfca;
            background: #fff6f4;
            color: #983d35;
        }

        .demo-awaiting-confirmation {
            max-width: 520px;
            padding: 34px;
            border: 1px solid #d6e8dc;
            border-radius: 18px;
            background: linear-gradient(145deg, #f3fbf5, #fff);
            color: #526b80;
            box-shadow: 0 16px 42px rgba(16, 42, 67, .08);
        }

        .demo-awaiting-icon {
            display: grid;
            place-items: center;
            width: 58px;
            height: 58px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #e2f2e7;
            color: #26714c;
            font-size: 28px;
            font-weight: 800;
        }

        .demo-awaiting-confirmation h2 {
            margin: 0 0 12px;
            color: #102a43;
            font-size: 24px;
        }

        .demo-awaiting-confirmation p {
            margin: 0 0 22px;
            line-height: 1.7;
        }

        .demo-payment-failed-state {
            max-width: 520px;
            padding: 34px;
            border: 1px solid #efcfca;
            border-radius: 18px;
            background: linear-gradient(145deg, #fff6f4, #fff);
            color: #983d35;
            box-shadow: 0 16px 42px rgba(16, 42, 67, .08);
            text-align: center;
        }

        .demo-payment-failed-state h2 {
            margin: 0 0 12px;
            color: #102a43;
            font-size: 24px;
        }

        .demo-payment-failed-state p {
            margin: 0 0 22px;
            line-height: 1.7;
        }

        .demo-payment-failed-state .demo-awaiting-icon {
            background: #f9e4e0;
            color: #983d35;
        }

        .demo-payment-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 20px;
        }

        .demo-payment-actions button {
            min-width: 205px;
            border: 1px solid transparent;
            border-radius: 10px;
            padding: 13px 18px;
            cursor: pointer;
            font: inherit;
            font-weight: 750;
            transition: .18s ease;
        }

        .demo-payment-actions button:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 8px 18px rgba(16, 42, 67, .12);
        }

        .demo-payment-success {
            background: #102a43;
            color: #fff;
        }

        .demo-payment-failure {
            border-color: #e8c7c2 !important;
            background: #fff8f6;
            color: #983d35;
            border-radius: 9px;
            padding: 11px 15px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .demo-payment-actions button:disabled {
            opacity: .6;
            cursor: wait;
        }

        .demo-awaiting-confirmation .demo-payment-failure {
            margin-top: 18px;
            border: 1px solid #e8c7c2;
            border-radius: 9px;
            padding: 11px 15px;
            background: #fff8f6;
            color: #983d35;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }

        .demo-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 24px;
        }

        .demo-actions[hidden] {
            display: none;
        }

        .demo-back {
            min-width: 190px;
            border: 1px solid #cad6e2;
            border-radius: 9px;
            padding: 13px 16px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 700;
            background: #fff;
            color: #102a43;
            transition: .18s ease;
        }

        .demo-back {
            display: inline-block;
            background: #fff;
            color: #102a43;
        }

        @media (max-width: 700px) {
            .demo-payment {
                margin: 18px 12px 40px;
            }

            .demo-header {
                padding: 18px 20px;
            }

            .demo-secure {
                display: none;
            }

            .demo-layout {
                grid-template-columns: 1fr;
            }

            .demo-order {
                border-right: 0;
                border-bottom: 1px solid #e4ebf2;
                padding: 24px 20px;
            }

            .demo-payment-panel {
                padding: 28px 20px;
            }

            .demo-back {
                width: 100%;
            }

            .demo-payment-actions button {
                width: 100%;
            }

            .demo-qr img {
                width: 220px;
                height: 220px;
            }

        }
    </style>
</head>

<body>

<main class="demo-payment">
    <header class="demo-header">
        <div>
            <p class="demo-brand">ÉCRIN JEWELRY</p>
            <h1 class="demo-title">Chuyển khoản VietQR</h1>
        </div>
        <span class="demo-secure">Thanh toán chuyển khoản</span>
    </header>

    <div class="demo-layout">
        <aside class="demo-order">
            <p class="demo-order-label">Thông tin thanh toán</p>

            <div class="demo-row">
                <span>Mã đơn hàng</span>
                <strong><?= e((string) $order['id']) ?></strong>
            </div>

            <div class="demo-row">
                <span>Phương thức</span>
                <strong id="payment-method-label"><?= e($paymentConfig['bank_name']) ?></strong>
            </div>

            <div class="demo-row" id="payment-account-holder-row">
                <span>Chủ tài khoản</span>
                <strong><?= e($paymentConfig['account_holder']) ?></strong>
            </div>

            <div class="demo-row" id="payment-account-number-row">
                <span>Số tài khoản</span>
                <strong><?= e($paymentConfig['account_number']) ?></strong>
            </div>

            <div class="demo-row">
                <span>Tổng thanh toán</span>
                <strong class="demo-amount"><?= e($amount) ?>đ</strong>
            </div>

            <?php if ($showPaymentQr): ?>
                <section class="demo-transfer">
                    <span>NỘI DUNG CHUYỂN KHOẢN</span>
                    <strong id="payment-reference"><?= e($qrAttempt['reference']) ?></strong>
                </section>
            <?php endif; ?>
        </aside>

        <section class="demo-payment-panel">
            <?php if ($isCanceled): ?>
                <div class="demo-payment-status is-error">
                    <strong>Đơn hàng đã bị hủy</strong>
                    Đơn hàng này đã hết thời hạn thanh toán hoặc đã được hủy trước đó.
                    <a class="demo-back" href="<?= e($appBaseUrl) ?>/client/tracuudh.php">Tra cứu đơn hàng</a>
                </div>
            <?php elseif ($isPaymentDeadlineExpired): ?>
                <div class="demo-payment-status is-error">
                    <strong>Đã hết thời hạn thanh toán</strong>
                    Đơn hàng này đã được chuyển sang xử lý. Vui lòng liên hệ cửa hàng để được hỗ trợ.
                    <a class="demo-back" href="<?= e($appBaseUrl) ?>/client/tracuudh.php">Tra cứu đơn hàng</a>
                </div>
            <?php elseif ($isPaid): ?>
                <div class="demo-payment-status">
                    <strong>Đã xác nhận thanh toán</strong>
                    Cửa hàng đã đối soát khoản chuyển cho đơn hàng này.
                </div>
            <?php elseif ($isAwaitingConfirmation): ?>
                <section class="demo-awaiting-confirmation" id="payment-awaiting-confirmation" role="status" aria-live="polite">
                    <span class="demo-awaiting-icon" aria-hidden="true">✓</span>
                    <h2>Đặt hàng thành công</h2>
                    <p>Đơn hàng <strong>#<?= e((string) $order['id']) ?></strong> đã đặt thành công và đang chờ cửa hàng xác nhận thanh toán sau khi đối soát giao dịch.</p>
                    <p><strong>Tổng thanh toán:</strong> <?= e($amount) ?>đ</p>
                </section>
            <?php else: ?>
                <?php if ($isFailed): ?>
                <section class="demo-payment-failed-state" id="payment-failed-state" role="status" aria-live="polite">
                    <span class="demo-awaiting-icon" aria-hidden="true">!</span>
                    <h2>Thanh toán chưa thành công</h2>
                    <p>Đơn hàng <strong>#<?= e((string) $order['id']) ?></strong> vẫn được giữ. Bạn có thể quay lại thanh toán trong vòng 30 phút hoặc đổi sang thanh toán khi nhận hàng.</p>
                    <div class="demo-payment-actions">
                        <button class="demo-payment-success" id="retry-vietqr-payment" type="button">
                            Quay lại thanh toán
                        </button>
                    </div>
                </section>
                <?php endif; ?>

                <div id="payment-qr-view" <?php echo $isFailed ? 'hidden' : ''; ?>>
                <div class="demo-payment-intro">
                    <h2>Quét mã bằng ứng dụng ngân hàng</h2>
                    <p>Kiểm tra đúng người nhận, số tiền và nội dung chuyển khoản trước khi xác nhận giao dịch.</p>
                </div>

                <div class="demo-qr">
                    <h3>Mã VietQR cho đơn hàng #<?= e((string) $order['id']) ?></h3>
                    <div class="demo-qr-frame">
                        <img id="payment-qr-image" alt="Mã VietQR chuyển khoản">
                    </div>
                    <p class="demo-qr-expiry" id="payment-qr-expiry">
                        Nội dung mã đổi sau <strong id="payment-qr-countdown">01:00</strong>
                    </p>
                    <span class="demo-qr-code" id="payment-qr-code"><?= e($qrAttempt['reference']) ?></span>
                </div>

                <div class="demo-payment-actions" id="payment-actions">
                    <button class="demo-payment-failure" type="button" onclick="reportVietQrPayment('failed')">
                        Thanh toán thất bại
                    </button>
                    <button class="demo-payment-success" type="button" onclick="reportVietQrPayment('success')">
                        Thanh toán thành công
                    </button>
                </div>

                <div id="payment-result" class="demo-payment-status" role="status" aria-live="polite" hidden></div>
                </div>

                <?php if ($isFailed): ?>
                <div id="payment-failure-window" class="demo-payment-status is-error">
                    <strong>Thời hạn thanh toán</strong>
                    Còn <span id="payment-deadline-countdown">30:00</span> để thanh toán. Hết thời hạn, đơn hàng sẽ tự động bị hủy.
                    <button class="demo-payment-failure" id="change-payment-method" type="button">
                        Đổi sang thanh toán khi nhận hàng
                    </button>
                </div>
                <?php endif; ?>

                <section class="demo-awaiting-confirmation" id="payment-cod-confirmed" role="status" aria-live="polite" hidden>
                    <span class="demo-awaiting-icon" aria-hidden="true">✓</span>
                    <h2>Đã đổi sang thanh toán khi nhận hàng</h2>
                    <p>Đơn hàng <strong>#<?= e((string) $order['id']) ?></strong> vẫn được giữ nguyên. Bạn sẽ thanh toán khi nhận hàng.</p>
                </section>
            <?php endif; ?>

            <div class="demo-actions" id="payment-navigation-actions" <?php echo $showPaymentQr ? 'hidden' : ''; ?>>
                <a class="demo-back"
                   href="<?= e($appBaseUrl) ?>/client/tracuudh.php">
                    Tra cứu đơn hàng
                </a>
                <a class="demo-back"
                   href="<?= e($appBaseUrl) ?>/index.php">
                    Về trang chủ
                </a>
            </div>
        </section>
    </div>
</main>

<?php if ($showPaymentQr): ?>
<script src="<?= e($appBaseUrl) ?>/assets/qrcode-generator.js"></script>
<script>
const PAYMENT_ORDER_ID = <?= (int) $order['id'] ?>;
const PAYMENT_CSRF = <?= json_encode($csrfToken) ?>;
const PAYMENT_REFRESH_API =
    <?= json_encode($appBaseUrl . '/api/payment/refresh_vietqr.php') ?>;
const PAYMENT_REPORT_API =
    <?= json_encode($appBaseUrl . '/api/payment/report_vietqr.php') ?>;
const PAYMENT_CHANGE_API =
    <?= json_encode($appBaseUrl . '/api/payment/change_vietqr_method.php') ?>;
let paymentQrToken = <?= json_encode($qrAttempt['token'] ?? '') ?>;
let paymentQrPayload = <?= json_encode($qrAttempt['payload'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
let paymentQrExpiresAt = Date.now() + <?= max(0, ((int) ($qrAttempt['expires_at'] ?? time()) - time())) ?> * 1000;
let paymentDeadlineExpiresAt = <?= (int) ($order['payment_deadline'] ?? 0) ?> * 1000;
let paymentQrRefreshing = false;
let paymentQrRefreshRetryAt = 0;
let paymentDeadlineExpiryRequested = false;

function setPaymentMessage(title, detail, isError = false) {
    const message = document.getElementById('payment-result');
    const heading = document.createElement('strong');
    heading.textContent = title;
    message.hidden = !isError;
    message.classList.toggle('is-error', isError);
    message.textContent = '';
    message.appendChild(heading);
    message.appendChild(document.createTextNode(detail));
}

function renderPaymentQr(payload) {
    if (typeof qrcode !== 'function') {
        throw new Error('Không tải được thư viện tạo QR. Hãy tải lại trang hoặc nhập thông tin chuyển khoản bên cạnh.');
    }
    const qr = qrcode(0, 'M');
    qr.addData(payload, 'Byte');
    qr.make();
    document.getElementById('payment-qr-image').src = qr.createDataURL(6, 4);
}

async function refreshVietQr() {
    if (paymentQrRefreshing) return false;
    paymentQrRefreshing = true;
    setPaymentMessage('Đang làm mới mã chuyển khoản', 'Vui lòng chờ trong giây lát.');

    try {
        const response = await fetch(PAYMENT_REFRESH_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: PAYMENT_ORDER_ID,
                csrf_token: PAYMENT_CSRF
            })
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            if (response.status === 409) {
                window.location.reload();
                return true;
            }
            throw new Error(data.message || 'Không thể tạo mã QR mới.');
        }
        paymentQrToken = data.qr_token;
        paymentQrPayload = data.qr_payload;
        paymentQrExpiresAt = Date.now() + Number(data.expires_in) * 1000;
        paymentQrRefreshRetryAt = 0;
        document.getElementById('payment-qr-code').textContent = data.transfer_reference;
        document.getElementById('payment-reference').textContent = data.transfer_reference;
        renderPaymentQr(paymentQrPayload);
        setPaymentMessage('Mã mới đã sẵn sàng', 'Quét lại nếu bạn chưa chuyển khoản. Nếu đã chuyển khoản, hãy kiểm tra giao dịch rồi báo kết quả bên dưới.');
        return true;
    } catch (error) {
        setPaymentMessage('Không thể làm mới mã', error.message, true);
        return false;
    } finally {
        paymentQrRefreshing = false;
    }
}

async function reportVietQrPayment(result) {
    const buttons = Array.from(document.querySelectorAll('#payment-actions button'));
    buttons.forEach(button => button.disabled = true);
    setPaymentMessage('Đang gửi trạng thái', 'Vui lòng đợi hệ thống xử lý yêu cầu.');

    try {
        const response = await fetch(PAYMENT_REPORT_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: PAYMENT_ORDER_ID,
                result,
                csrf_token: PAYMENT_CSRF,
                qr_token: paymentQrToken
            })
        });
        const data = await response.json();

        if (response.status === 410) {
            await refreshVietQr();
            buttons.forEach(button => button.disabled = false);
            return;
        }
        if (response.status === 409) {
            window.location.reload();
            return;
        }
        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Không thể gửi trạng thái thanh toán.');
        }

        window.location.reload();
    } catch (error) {
        setPaymentMessage('Không thể gửi trạng thái', error.message, true);
        buttons.forEach(button => button.disabled = false);
    }
}

async function changeVietQrPaymentMethod() {
    const button = document.getElementById('change-payment-method');
    button.disabled = true;

    try {
        const response = await fetch(PAYMENT_CHANGE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: PAYMENT_ORDER_ID,
                csrf_token: PAYMENT_CSRF
            })
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
            if (response.status === 409) {
                window.location.reload();
                return;
            }
            throw new Error(data.message || 'Không thể đổi phương thức thanh toán.');
        }

        document.getElementById('payment-method-label').textContent = 'Thanh toán khi nhận hàng';
        document.getElementById('payment-account-holder-row').hidden = true;
        document.getElementById('payment-account-number-row').hidden = true;
        const qrView = document.getElementById('payment-qr-view');
        if (qrView) qrView.hidden = true;
        const failedView = document.getElementById('payment-failed-state');
        if (failedView) failedView.hidden = true;
        const paymentIntro = document.querySelector('.demo-payment-intro');
        if (paymentIntro) paymentIntro.hidden = true;
        document.querySelector('.demo-transfer').hidden = true;
        document.getElementById('payment-actions').hidden = true;
        document.getElementById('payment-result').hidden = true;
        const failureWindow = document.getElementById('payment-failure-window');
        if (failureWindow) failureWindow.hidden = true;
        document.getElementById('payment-cod-confirmed').hidden = false;
        document.getElementById('payment-navigation-actions').hidden = false;
    } catch (error) {
        setPaymentMessage('Không thể đổi phương thức', error.message, true);
        button.disabled = false;
    }
}

function updatePaymentDeadlineCountdown() {
    const countdown = document.getElementById('payment-deadline-countdown');
    if (!countdown || !paymentDeadlineExpiresAt) return;
    const remaining = Math.max(0, Math.ceil((paymentDeadlineExpiresAt - Date.now()) / 1000));
    countdown.textContent =
        `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (remaining === 0 && !paymentDeadlineExpiryRequested) {
        paymentDeadlineExpiryRequested = true;
        refreshVietQr();
    }
}

function updatePaymentQrCountdown() {
    const countdown = document.getElementById('payment-qr-countdown');
    if (!countdown || !paymentQrPayload) return;
    const remaining = Math.max(0, Math.ceil((paymentQrExpiresAt - Date.now()) / 1000));
    countdown.textContent =
        `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
    if (remaining === 0 && Date.now() >= paymentQrRefreshRetryAt) {
        refreshVietQr().then(refreshed => {
            if (!refreshed) paymentQrRefreshRetryAt = Date.now() + 3000;
        });
    }
}

if (paymentQrPayload) {
    try {
        renderPaymentQr(paymentQrPayload);
    } catch (error) {
        setPaymentMessage('Không hiển thị được mã QR', error.message, true);
    }
    updatePaymentQrCountdown();
    window.setInterval(updatePaymentQrCountdown, 1000);
    if (paymentDeadlineExpiresAt) {
        updatePaymentDeadlineCountdown();
        window.setInterval(updatePaymentDeadlineCountdown, 1000);
    }
    const changeMethodButton = document.getElementById('change-payment-method');
    if (changeMethodButton) {
        changeMethodButton.addEventListener('click', changeVietQrPaymentMethod);
    }
    const retryPaymentButton = document.getElementById('retry-vietqr-payment');
    if (retryPaymentButton) {
        retryPaymentButton.addEventListener('click', () => {
            document.getElementById('payment-failed-state').hidden = true;
            document.getElementById('payment-qr-view').hidden = false;
        });
    }
}
</script>
<?php endif; ?>

</body>
</html>
