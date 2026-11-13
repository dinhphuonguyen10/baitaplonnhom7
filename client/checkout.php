<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$customerUsername = ($_SESSION['role'] ?? '') === 'customer' && isset($_SESSION['user_id'], $_SESSION['username'])
    ? (string) $_SESSION['username']
    : null;
$pageTitle = "Đặt hàng - ÉCRIN JEWELRY";
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$appBaseUrl = preg_replace('~/(?:client|admin|api)(?:/.*)?$~', '', $scriptDirectory);
$appBaseUrl = rtrim($appBaseUrl, '/');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($appBaseUrl . '/assets/style.css?v=' . filemtime(__DIR__ . '/../assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script>window.APP_BASE_URL = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script>window.CUSTOMER_USERNAME = <?php echo json_encode($customerUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
</head>
<body class="checkout-page">
    <header class="checkout-brandbar">
        <a class="checkout-brand" href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Écrin Jewelry - Trang chủ">
            <img src="<?php echo htmlspecialchars($appBaseUrl . '/assets/banner/logo.png', ENT_QUOTES, 'UTF-8'); ?>" alt="" class="logo-img">
            <span class="logo-wordmark"><strong>ÉCRIN</strong><small>JEWELRY</small></span>
        </a>
    </header>
<main class="checkout-page-main">
    <div class="checkout-page-container">
        <div class="checkout-page-heading">
            <div class="checkout-page-heading-row">
                <h1>Thông tin đặt hàng</h1>
                <a class="checkout-back-link" href="<?php echo htmlspecialchars($appBaseUrl . '/client/cart.php', ENT_QUOTES, 'UTF-8'); ?>">← Quay lại giỏ hàng</a>
            </div>
            <p>Điền thông tin nhận hàng để chúng tôi giao sản phẩm đến bạn.</p>
        </div>

        <div class="checkout-page-layout">
            <section class="checkout-form-card">
                <h2>Thông tin người nhận</h2>
                <p class="checkout-login-notice" id="checkout-login-notice" hidden>
                    Vui lòng <button type="button" class="login-trigger">đăng nhập</button> trước khi đặt hàng.
                </p>
                <p class="checkout-form-message" id="checkout-form-message" role="status" aria-live="polite"></p>
                <form id="checkout-form">
                    <div class="checkout-form-grid">
                        <div class="checkout-field checkout-field-full">
                            <label for="checkout-name">Họ và tên <span>*</span></label>
                            <input id="checkout-name" name="name" type="text" autocomplete="name" maxlength="150" placeholder="Tên người nhận hàng" required>
                        </div>
                        <div class="checkout-field">
                            <label for="checkout-phone">Số điện thoại <span>*</span></label>
                            <input id="checkout-phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" minlength="10" maxlength="20" pattern="0[0-9]{9}" title="Nhập đúng 10 chữ số, bắt đầu bằng số 0." placeholder="Ví dụ: 0912345678" required>
                        </div>
                        <div class="checkout-field">
                            <label for="checkout-email">Email <span>*</span></label>
                            <input id="checkout-email" name="email" type="email" autocomplete="email" maxlength="254" pattern="[^\s@]+@[^\s@]+\.[^\s@]{2,}" title="Nhập địa chỉ email hợp lệ, ví dụ: ten@example.com." placeholder="email@example.com" required>
                        </div>
                        <div class="checkout-field">
                            <label for="checkout-province">Tỉnh / thành phố <span>*</span></label>
                            <select id="checkout-province" name="province" autocomplete="address-level1" required>
                                <option value="">Đang tải tỉnh/thành...</option>
                            </select>
                        </div>
                        <div class="checkout-field">
                            <label for="checkout-ward">Xã / phường <span>*</span></label>
                            <select id="checkout-ward" name="ward" autocomplete="address-level2" required disabled>
                                <option value="">Chọn tỉnh/thành trước</option>
                            </select>
                        </div>
                        <div class="checkout-field checkout-field-full">
                            <label for="checkout-address-detail">Địa chỉ cụ thể <span>*</span></label>
                            <input id="checkout-address-detail" name="address_detail" type="text" autocomplete="street-address" maxlength="350" placeholder="Số nhà, tên đường, tòa nhà..." required>
                        </div>
                    </div>

                    <div class="checkout-method-card">
                        <div class="checkout-method-icon" aria-hidden="true">⌂</div>
                        <div>
                            <strong>Giao hàng tiêu chuẩn</strong>
                            <p>Giao hàng đến địa chỉ bạn cung cấp</p>
                        </div>
                        <span class="checkout-method-check" aria-label="Đang chọn">✓</span>
                    </div>
                    <div class="checkout-method-card">
                        <div class="checkout-method-icon" aria-hidden="true">₫</div>
                        <div>
                            <strong>Thanh toán khi nhận hàng (COD)</strong>
                            <p>Thanh toán trực tiếp cho nhân viên giao hàng</p>
                        </div>
                        <span class="checkout-method-check" aria-label="Đang chọn">✓</span>
                    </div>

                    <button class="checkout-submit-btn" id="submit-checkout" type="submit">Hoàn tất đặt hàng</button>
                </form>
            </section>

            <aside class="checkout-order-card" aria-label="Thông tin đơn hàng">
                <h2>Đơn hàng của bạn</h2>
                <div class="checkout-order-items"></div>
                <div class="checkout-order-total">
                    <span>Tổng tiền hàng</span>
                    <strong id="checkout-total-price">0 đ</strong>
                </div>
                <p class="checkout-shipping-note">Phí giao hàng (nếu có) sẽ được cửa hàng xác nhận khi liên hệ.</p>
            </aside>
        </div>
        <section class="checkout-success" id="checkout-success" hidden aria-live="polite">
            <div class="checkout-success-icon" aria-hidden="true">✓</div>
            <h2>Đặt hàng thành công</h2>
            <p class="checkout-success-order-id"></p>
            <p class="checkout-success-guide">Để tra cứu tình trạng đơn hàng, mở trang Tra cứu đơn hàng và nhập mã đơn hoặc số điện thoại đã đặt.</p>
            <div class="checkout-receipt">
                <h3>Thông tin đơn hàng</h3>
                <div class="checkout-receipt-customer"></div>
                <h3>Sản phẩm đã đặt</h3>
                <div class="checkout-receipt-items"></div>
                <div class="checkout-order-total">
                    <span>Tổng tiền hàng</span>
                    <strong class="checkout-receipt-total"></strong>
                </div>
                <p class="checkout-receipt-payment">Thanh toán khi nhận hàng (COD)</p>
            </div>
            <div class="checkout-success-actions">
                <a class="checkout-submit-btn" href="<?php echo htmlspecialchars($appBaseUrl . '/client/tracuudh.php', ENT_QUOTES, 'UTF-8'); ?>">Tra cứu đơn hàng</a>
                <a class="checkout-success-home" href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Tiếp tục mua sắm</a>
            </div>
        </section>
    </div>
</main>
<?php include __DIR__ . '/../include/auth_modals.php'; ?>
<script src="<?php echo htmlspecialchars($appBaseUrl . '/assets/script.js?v=' . filemtime(__DIR__ . '/../assets/script.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
