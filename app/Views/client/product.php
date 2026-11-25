<?php

include VIEW_PATH . '/layouts/header.php';
?>

<main class="product-detail-page">
    <div class="breadcrumb-container">
        <a href="<?php echo $escape($appBaseUrl . '/index.php'); ?>">Trang chủ</a>
        <span class="separator">&gt;</span>
        <?php if ($product): ?>
            <a href="<?php echo $escape($appBaseUrl . '/client/category.php?slug=' . rawurlencode($product['category'])); ?>">
                <?php echo $escape($product['category_name']); ?>
            </a>
            <span class="separator">&gt;</span>
            <strong class="current"><?php echo $escape($product['name']); ?></strong>
        <?php else: ?>
            <strong class="current">Không tìm thấy sản phẩm</strong>
        <?php endif; ?>
    </div>

    <?php if ($product): ?>
        <?php /* Ảnh, tồn kho, giá giảm và mô tả đã được ProductPageController chuẩn bị */ ?>
        <section class="product-detail-card product-card" data-db-id="<?php echo (int) $product['id']; ?>" data-stock="<?php echo $stock; ?>" aria-labelledby="product-name">
            <!-- CỘT TRÁI: HÌNH ẢNH SẢN PHẨM VUÔNG KHÍT KHUNG -->
            <div class="product-detail-image-wrap">
                <button type="button" class="wishlist-btn" data-product-id="<?php echo (int) $product['id']; ?>" title="Yêu thích">♡</button>
                <img
                    class="product-detail-image"
                    src="<?php echo $escape($imageUrl); ?>"
                    alt="<?php echo $escape($product['name']); ?>"
                    onerror="this.onerror=null;this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>';"
                >
            </div>

            <!-- CỘT PHẢI: THÔNG TIN CHI TIẾT SẢN PHẨM & MUA HÀNG -->
            <div class="product-detail-info">
                <p class="product-detail-eyebrow"><?php echo $escape($product['category_name']); ?> · ÉCRIN JEWELRY</p>
                <h1 id="product-name"><?php echo $escape($product['name']); ?></h1>
                <div class="product-detail-price product-price">
                    <span class="price-sale"><?php echo number_format((float) $product['price_sale'], 0, ',', '.'); ?><sup>đ</sup></span>
                    <?php if ((float) $product['price_original'] > (float) $product['price_sale']): ?>
                        <span class="price-original"><?php echo number_format((float) $product['price_original'], 0, ',', '.'); ?><sup>đ</sup></span>
                        <?php if ($discountPercentage > 0): ?>
                            <span class="price-discount-percent">-<?php echo $discountPercentage; ?>%</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <p class="product-detail-description"><?php echo $escape($description); ?></p>

                <dl class="product-detail-facts">
                    <div>
                        <dt>Mã sản phẩm</dt>
                        <dd>EC-<?php echo str_pad((string) $product['id'], 5, '0', STR_PAD_LEFT); ?></dd>
                    </div>
                    <div>
                        <dt>Danh mục</dt>
                        <dd><?php echo $escape($product['category_name']); ?></dd>
                    </div>
                    <div>
                        <dt>Tình trạng</dt>
                        <?php if ($isDiscontinued): ?>
                            <dd class="product-detail-out-of-stock" style="color: #b42318; font-weight: 700;">Ngừng kinh doanh</dd>
                        <?php else: ?>
                            <dd class="<?php echo $stock > 0 ? 'product-detail-stock' : 'product-detail-out-of-stock'; ?>">
                                <?php echo $stock > 0 ? 'Còn hàng (' . $stock . ')' : 'Tạm hết hàng'; ?>
                            </dd>
                        <?php endif; ?>
                    </div>
                    <?php if ((int) $product['sold_count'] > 0): ?>
                        <div>
                            <dt>Đã bán</dt>
                            <dd><?php echo (int) $product['sold_count']; ?> sản phẩm</dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <!-- CẶP NÚT MUA HÀNG & CHAT TƯ VẤN SANG TRỌNG -->
                <?php $maxQuantity = min($stock, 99); ?>
                <div class="product-quantity-control product-detail-quantity" role="group" aria-label="Chọn số lượng <?php echo $escape($product['name']); ?>">
                    <span>Số lượng</span>
                    <button type="button" class="product-quantity-decrease" aria-label="Giảm số lượng"<?php echo !$canBuy ? ' disabled' : ''; ?>>−</button>
                    <input class="product-quantity" type="number" min="1" max="<?php echo max($maxQuantity, 1); ?>" value="1" inputmode="numeric" aria-label="Số lượng <?php echo $escape($product['name']); ?>"<?php echo !$canBuy ? ' disabled' : ''; ?>>
                    <button type="button" class="product-quantity-increase" aria-label="Tăng số lượng"<?php echo !$canBuy ? ' disabled' : ''; ?>>+</button>
                </div>
                <div class="product-detail-actions">
                    <button type="button" class="add-to-cart" <?php echo !$canBuy ? 'disabled' : ''; ?>>
                        <?php 
                            if ($isDiscontinued) {
                                echo 'Ngừng kinh doanh';
                            } else {
                                echo $stock > 0 ? 'Thêm vào giỏ' : 'Tạm hết hàng';
                            }
                        ?>
                    </button>
                    <button type="button" class="buy-now" <?php echo !$canBuy ? 'disabled' : ''; ?>>Mua ngay</button>
                    <a class="btn-chat-consult" href="<?php echo $escape($appBaseUrl . '/client/chat.php?product_id=' . (int) $product['id']); ?>" title="Chat trực tiếp với cửa hàng">
                        <svg class="btn-chat-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"></path>
                        </svg>
                        <span>Chat tư vấn</span>
                    </a>
                </div>

                <!-- ĐẶC QUYỀN TIN CẬY NHẸ NHÀNG GỌN GÀNG -->
                <div class="product-detail-trust-strip">
                    <div class="trust-strip-item">
                        <span class="trust-strip-check">✓</span>
                        <span>Trang sức chuẩn bạc &amp; đá quý cao cấp 100%</span>
                    </div>
                    <div class="trust-strip-item">
                        <span class="trust-strip-check">✓</span>
                        <span>Bảo hành làm sáng, đánh bóng trọn đời miễn phí</span>
                    </div>
                    <div class="trust-strip-item">
                        <span class="trust-strip-check">✓</span>
                        <span>Hỗ trợ đổi sản phẩm trong 7 ngày nếu có lỗi</span>
                    </div>
                </div>

                <a class="product-detail-back" href="<?php echo $escape($appBaseUrl . '/client/category.php?slug=' . rawurlencode($product['category'])); ?>">
                    ← Tiếp tục xem danh mục
                </a>
            </div>
        </section>
        <?php App\Core\View::render('client/product_reviews', array_merge(['product' => $product, 'escape' => $escape], $reviewSection)); ?>
    <?php else: ?>
        <section class="product-detail-not-found">
            <h1>Không tìm thấy sản phẩm</h1>
            <p>Sản phẩm có thể đã được gỡ khỏi danh mục hoặc đường dẫn không chính xác.</p>
            <a class="view-all-btn" href="<?php echo $escape($appBaseUrl . '/index.php'); ?>">Quay lại trang chủ</a>
        </section>
    <?php endif; ?>
</main>

<?php include VIEW_PATH . '/layouts/footer.php'; ?>
