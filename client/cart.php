<?php
$pageTitle = "Giỏ hàng - ÉCRIN JEWELRY";
$bodyClass = 'cart-page';
include __DIR__ . '/../include/header.php';
?>
<main class="cart-page-main">
    <div class="cart-page-container">
        <div class="cart-page-heading">
            <div>
                <h1>Giỏ hàng của bạn</h1>
                <p>Kiểm tra sản phẩm và số lượng trước khi đặt hàng.</p>
            </div>
            <a class="cart-continue-link" href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">← Tiếp tục mua sắm</a>
        </div>

        <div class="cart-page-layout">
            <section class="cart-page-card" aria-label="Sản phẩm trong giỏ hàng">
                <div class="cart-page-card-heading">
                    <h2>Sản phẩm</h2>
                    <span class="cart-total-count">0 sản phẩm</span>
                    <label class="cart-select-all-label">
                        <input type="checkbox" class="cart-select-all">
                        <span>Chọn tất cả</span>
                    </label>
                </div>
                <div class="cart-items cart-page-items">
                    <p class="empty-cart">Giỏ hàng đang trống. Thêm sản phẩm nào đó nhé!</p>
                </div>
            </section>

            <aside class="cart-page-summary" aria-label="Tóm tắt giỏ hàng">
                <h2>Tóm tắt đơn hàng</h2>
                <div class="cart-page-summary-row">
                    <span>Tạm tính</span>
                    <strong class="cart-total-price">0 đ</strong>
                </div>
                <div class="cart-page-summary-row">
                    <span>Giao hàng</span>
                    <span>Tiêu chuẩn</span>
                </div>
                <div class="cart-page-summary-total">
                    <span>Tổng tiền hàng</span>
                    <strong class="cart-total-price">0 đ</strong>
                </div>
                <button class="checkout-btn" type="button">Tiến hành đặt hàng</button>
                <p class="cart-cod-note">Thanh toán COD khi nhận hàng. Phí giao hàng (nếu có) sẽ được xác nhận khi cửa hàng liên hệ.</p>
            </aside>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../include/footer.php'; ?>
