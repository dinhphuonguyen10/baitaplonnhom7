
<?php
session_start();

require_once __DIR__ . '/../config/db.php';

// Chỉ cho phép khách hàng đăng nhập thanh toán
if (
    !isset($_SESSION['user_id']) ||
    ($_SESSION['role'] ?? '') !== 'customer'
) {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$orderId = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);

if (!$orderId || $orderId < 1) {
    http_response_code(400);
    exit('Mã đơn hàng không hợp lệ.');
}

// Lấy đơn hàng thuộc tài khoản hiện tại
$stmt = $conn->prepare(
    "SELECT id, total_price, payment_method,
            payment_status, status, created_at
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

if (in_array($order['status'], ['Đã hủy', 'Canceled'], true)) {
    exit('Đơn hàng đã bị hủy, không thể thanh toán.');
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

$qrPath = __DIR__ . '/../assets/payment-qr-demo.svg';
$qrUrl = $appBaseUrl . '/assets/payment-qr-demo.svg?v=' . filemtime($qrPath);

$amount = number_format(
    (float) $order['total_price'],
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
            max-width: 920px;
            margin: 48px auto;
            background: #fff;
            border: 1px solid #dfe7ef;
            border-radius: 18px;
            color: #102a43;
            box-shadow: 0 24px 70px rgba(16, 42, 67, .16);
            overflow: hidden;
        }

        .demo-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            min-height: 88px;
            padding: 20px 32px;
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
            grid-template-columns: 310px minmax(0, 1fr);
            min-height: 560px;
        }

        .demo-order {
            padding: 34px 28px;
            background: #f7f9fc;
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
            margin-top: 18px;
            padding: 16px;
            background: #102a43;
            border-radius: 10px;
        }

        .demo-transfer span {
            display: block;
            color: #c8d5e2;
            font-size: 11px;
            margin-bottom: 6px;
        }

        .demo-transfer strong {
            color: #fff;
            font-size: 14px;
            letter-spacing: .4px;
        }

        .demo-payment-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 32px;
            text-align: center;
        }

        .demo-qr h3 {
            margin: 0 0 18px;
            color: #102a43;
            font-size: 17px;
            letter-spacing: .5px;
        }

        .demo-qr-frame {
            display: inline-flex;
            padding: 13px;
            background: #fff;
            border: 1px solid #dfe7ef;
            border-radius: 18px;
            box-shadow: 0 14px 35px rgba(16, 42, 67, .12);
        }

        .demo-qr img {
            width: 220px;
            height: 220px;
            max-width: 100%;
            object-fit: contain;
            border-radius: 10px;
        }

        .demo-qr-caption {
            margin: 14px 0 0;
            color: #7890a5;
            font-size: 12px;
        }

        .demo-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            width: 100%;
            margin-top: 24px;
        }

        .demo-actions button,
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

        .demo-success-btn {
            background: #102a43;
            border-color: #102a43;
            color: #fff;
        }

        .demo-fail-btn {
            border-color: #dcaea8;
            color: #9b3d35;
        }

        .demo-back {
            display: inline-block;
            background: #fff;
            color: #102a43;
        }

        .demo-result {
            min-height: 20px;
            text-align: center;
            margin: 16px 0 0;
            font-weight: 700;
        }

        .demo-actions button:disabled {
            opacity: .6;
            cursor: not-allowed;
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

            .demo-actions button,
            .demo-back {
                width: 100%;
            }

            .demo-qr img {
                width: 200px;
                height: 200px;
            }
        }
    </style>
</head>

<body>

<main class="demo-payment">
    <header class="demo-header">
        <div>
            <p class="demo-brand">ÉCRIN JEWELRY</p>
            <h1 class="demo-title">Thanh toán đơn hàng</h1>
        </div>
        <span class="demo-secure">Giao dịch bảo mật</span>
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
                <strong>QR Banking</strong>
            </div>

            <div class="demo-row">
                <span>Tổng thanh toán</span>
                <strong class="demo-amount"><?= e($amount) ?>đ</strong>
            </div>

            <section class="demo-transfer">
                <span>NỘI DUNG CHUYỂN KHOẢN</span>
                <strong>THANHTOAN <?= e((string) $order['id']) ?></strong>
            </section>
        </aside>

        <section class="demo-payment-panel">
            <div class="demo-qr">
                <h3>QUÉT MÃ QR ĐỂ THANH TOÁN</h3>
                <div class="demo-qr-frame">
                    <img src="<?= e($qrUrl) ?>"
                         alt="Mã QR thanh toán">
                </div>
            </div>

            <div id="payment-result"
                 class="demo-result"
                 role="status"
                 aria-live="polite">
                <?= $isPaid ? 'Đơn hàng đã được thanh toán.' : '' ?>
            </div>

            <div class="demo-actions" id="payment-actions"
                 <?= $isPaid ? 'hidden' : '' ?>>
                <button type="button"
                        class="demo-success-btn"
                        onclick="confirmDemoPayment('success')">
                    Xác nhận thanh toán
                </button>
                <button type="button"
                        class="demo-fail-btn"
                        onclick="confirmDemoPayment('failed')">
                    Thanh toán thất bại
                </button>
            </div>

            <div class="demo-actions">
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

<script>
const PAYMENT_ORDER_ID = <?= (int) $order['id'] ?>;
const PAYMENT_CSRF = <?= json_encode($csrfToken) ?>;
const PAYMENT_API =
    <?= json_encode($appBaseUrl . '/api/payment/confirm_demo.php') ?>;

async function confirmDemoPayment(result) {

    const buttons = document.querySelectorAll(
        '#payment-actions button'
    );

    const message = document.getElementById('payment-result');

    buttons.forEach(button => button.disabled = true);

    message.textContent = 'Đang xử lý giao dịch...';

    try {

        const response = await fetch(PAYMENT_API, {
            method: 'POST',

            headers: {
                'Content-Type': 'application/json'
            },

            body: JSON.stringify({
                order_id: PAYMENT_ORDER_ID,
                result: result,
                csrf_token: PAYMENT_CSRF
            })
        });

        const data = await response.json();

        if (!response.ok || data.status !== 'success') {
            throw new Error(
                data.message || 'Không thể xử lý thanh toán.'
            );
        }

        if (result === 'success') {

            message.textContent =
                'Thanh toán thành công! Cảm ơn bạn đã mua hàng.';

            message.style.color = '#1b7550';

            document.getElementById('payment-actions').hidden = true;

        } else {

            message.textContent =
                'Thanh toán thất bại. Bạn có thể thử lại.';

            message.style.color = '#a83c3c';

            buttons.forEach(button => button.disabled = false);
        }

    } catch (error) {

        message.textContent = error.message;
        message.style.color = '#a83c3c';

        buttons.forEach(button => button.disabled = false);
    }
}
</script>

</body>
</html>
