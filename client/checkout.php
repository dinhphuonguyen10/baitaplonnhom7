<?php
require_once __DIR__ . '/../include/session.php';
require_once __DIR__ . '/../include/payment_qr_helper.php';
$qrPaymentConfig = getVietQrPaymentConfig();
$customerUsername = ($_SESSION['role'] ?? '') === 'customer' && isset($_SESSION['user_id'], $_SESSION['username'])
    ? (string) $_SESSION['username']
    : null;
$checkoutAddresses = [];
if ($customerUsername !== null) {
    require_once __DIR__ . '/../config/db.php';
    $addressesStmt = $conn->prepare(
        'SELECT id, recipient_name, address, phone, is_default, latitude, longitude
         FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC'
    );
    if (!$addressesStmt) {
        throw new RuntimeException('Không thể tải địa chỉ nhận hàng.');
    }
    $checkoutUserId = (int) $_SESSION['user_id'];
    $addressesStmt->bind_param('i', $checkoutUserId);
    $addressesStmt->execute();
    $addressesResult = $addressesStmt->get_result();
    while ($addressRow = $addressesResult->fetch_assoc()) {
        $checkoutAddresses[] = $addressRow;
    }
    $addressesStmt->close();
    $conn->close();
}
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
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script>window.APP_BASE_URL = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script>window.CUSTOMER_USERNAME = <?php echo json_encode($customerUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.CHECKOUT_ADDRESSES = <?php echo json_encode($checkoutAddresses, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
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
                    <fieldset class="checkout-saved-addresses" id="checkout-saved-addresses" hidden>
                        <legend>Chọn địa chỉ nhận hàng</legend>
                        <div id="checkout-address-options"></div>
                        <button class="checkout-address-dropdown-toggle" id="checkout-address-dropdown-toggle" type="button" aria-expanded="false" aria-controls="checkout-other-addresses" hidden>
                            <span>Địa chỉ khác</span>
                            <span class="checkout-address-dropdown-arrow" aria-hidden="true">⌄</span>
                        </button>
                        <div class="checkout-other-addresses" id="checkout-other-addresses" hidden>
                            <div id="checkout-other-address-options"></div>
                            <button class="checkout-add-address" id="checkout-add-address" type="button">＋ Thêm địa chỉ khác</button>
                        </div>
                    </fieldset>
                    <input id="checkout-address-id" type="hidden" name="address_id" value="">
                    <p class="checkout-address-edit-note" id="checkout-address-edit-note" hidden>
                        Địa chỉ mới chỉ được lưu vào tài khoản khi bạn chọn lưu làm mặc định.
                    </p>
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
                    </div>
                    <div class="checkout-address-fields" id="checkout-address-fields">
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
                            <input id="checkout-address-detail" name="address_detail" type="text" autocomplete="street-address" maxlength="350" placeholder="Nhập số nhà, tên đường..." aria-autocomplete="list" aria-controls="checkout-address-suggestions" required>
                            <small class="checkout-address-region-note">Khi đổi tỉnh/thành hoặc xã/phường, hãy nhập lại địa chỉ cụ thể để tránh ghép sai vị trí.</small>
                            <div class="checkout-address-suggestions" id="checkout-address-suggestions" role="listbox" hidden></div>
                        </div>
                    </div>

                    <div class="checkout-map-section" id="checkout-map-section" hidden>
                        <div class="checkout-map-heading">
                            <div>
                                <strong>Vị trí trên bản đồ</strong>
                                <p>Vị trí được tìm theo địa chỉ. Bạn có thể nhấn hoặc kéo ghim để điều chỉnh. Tìm kiếm gửi địa chỉ tới dịch vụ Photon/OpenStreetMap.</p>
                            </div>
                            <button class="checkout-map-search" id="checkout-map-search" type="button">Tìm lại vị trí</button>
                        </div>
                        <div id="checkout-address-map" role="application" aria-label="Bản đồ chọn vị trí giao hàng"></div>
                        <p class="checkout-map-status" id="checkout-map-status" aria-live="polite">Đang chờ địa chỉ đầy đủ.</p>
                        <small class="checkout-map-attribution">Bản đồ © OpenStreetMap contributors</small>
                    </div>
                    <label class="checkout-default-address" for="checkout-default-address">
                        <input id="checkout-default-address" type="checkbox">
                        Lưu địa chỉ này làm mặc định cho lần mua sau
                    </label>

                    <div class="checkout-method-card checkout-delivery-card">
                        <div class="checkout-method-icon" aria-hidden="true">⌂</div>
                        <div class="checkout-method-content">
                            <strong>Giao hàng tiêu chuẩn</strong>
                            <p>Giao hàng đến địa chỉ bạn cung cấp</p>
                        </div>
                        <span class="checkout-method-check" aria-hidden="true"></span>
                    </div>
                    <fieldset class="checkout-payment-methods">
                        <legend>Phương thức thanh toán</legend>
                        <label class="checkout-method-card is-selected" data-payment-card="COD">
                            <input type="radio" name="payment_method" value="COD" checked>
                            <div class="checkout-method-icon" aria-hidden="true">₫</div>
                            <div class="checkout-method-content">
                                <strong>Thanh toán khi nhận hàng</strong>
                                <p>Thanh toán trực tiếp cho nhân viên giao hàng</p>
                            </div>
                            <span class="checkout-method-check" aria-hidden="true"></span>
                        </label>
                        <label class="checkout-method-card<?php echo $qrPaymentConfig === null ? ' is-disabled' : ''; ?>" data-payment-card="BANK_QR">
                            <input type="radio" name="payment_method" value="BANK_QR" <?php echo $qrPaymentConfig === null ? 'disabled' : ''; ?>>
                            <div class="checkout-method-icon" aria-hidden="true">▣</div>
                            <div class="checkout-method-content">
                                <strong>Chuyển khoản qua VietQR</strong>
                                <p><?php echo $qrPaymentConfig === null ? 'Phương thức chưa được cấu hình.' : 'Chuyển khoản đến tài khoản ' . htmlspecialchars($qrPaymentConfig['bank_name'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="checkout-method-check" aria-hidden="true"></span>
                        </label>
                    </fieldset>
                    <div class="checkout-qr-panel" id="checkout-qr-panel" hidden>
                        <div>
                            <span class="checkout-qr-eyebrow">VietQR · <?php echo htmlspecialchars($qrPaymentConfig['bank_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                            <h3>Lấy mã thanh toán cho đơn hàng</h3>
                            <p>Nhấn “Lấy mã QR” để tạo đơn hàng và mở mã chuyển khoản. Mã hiển thị đổi sau 1 phút; ngân hàng vẫn có thể nhận chuyển khoản từ mã cũ nên hãy kiểm tra nội dung trước khi xác nhận.</p>
                            <div class="checkout-qr-meta">
                                <span>Số tiền cần thanh toán</span>
                                <strong id="checkout-qr-amount">0 đ</strong>
                            </div>
                        </div>
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
                <p class="checkout-receipt-payment">Thanh toán khi nhận hàng</p>
            </div>
            <div class="checkout-success-actions">
                <a class="checkout-submit-btn" href="<?php echo htmlspecialchars($appBaseUrl . '/client/tracuudh.php', ENT_QUOTES, 'UTF-8'); ?>">Tra cứu đơn hàng</a>
                <a class="checkout-success-home" href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Tiếp tục mua sắm</a>
            </div>
        </section>
    </div>
</main>
<?php include __DIR__ . '/../include/auth_modals.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="<?php echo htmlspecialchars($appBaseUrl . '/assets/script.js?v=' . filemtime(__DIR__ . '/../assets/script.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
