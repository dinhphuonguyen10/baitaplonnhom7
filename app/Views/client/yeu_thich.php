<?php
// Tác dụng: Trang hiển thị danh sách sản phẩm yêu thích (thả tim) của khách hàng

include VIEW_PATH . '/layouts/dau_trang.php';
?>

<main class="category-page wishlist-page">
    <div class="breadcrumb-container">
        <a href="../index.php">Trang chủ</a> <span class="separator">></span>
        <strong class="current">Sản phẩm yêu thích</strong>
    </div>

    <section class="wishlist-header-card">
        <div class="wishlist-title-wrap">
            <h1>Sản phẩm yêu thích</h1>
            <p>Danh sách các món trang sức ÉCRIN bạn đã lưu lại để tham khảo hoặc mua sắm.</p>
        </div>
        <div class="wishlist-header-meta">
            <span class="wishlist-total-badge" id="wishlist-total-badge"><?php echo $totalWishlistItems; ?> sản phẩm</span>
        </div>
    </section>

    <!-- CONTAINER HIỂN THỊ SẢN PHẨM YÊU THÍCH -->
    <div class="product-grid category-grid" id="wishlist-products-container">
        <?php if (!empty($wishlistItems)): ?>
            <?php foreach ($wishlistItems as $item): ?>
                <?php
                    $id = (int)$item['id'];
                    $name = $item['name'];
                    $priceSale = (float)$item['price_sale'];
                    $priceOriginal = (float)($item['price_original'] ?? 0);
                    $stock = (int)$item['stock'];
                    $sold = (int)$item['sold_count'];
                    $averageRating = (float)$item['average_rating'];
                    $reviewCount = (int)$item['review_count'];
                    $img = $item['image'];
                    $imgSrc = (str_starts_with($img, 'http://') || str_starts_with($img, 'https://'))
                        ? $img
                        : $appBaseUrl . '/' . ltrim($img, '/');
                    $detailUrl = $appBaseUrl . '/client/san_pham.php?id=' . $id;
                ?>
                <div class="product-card" data-db-id="<?php echo $id; ?>" data-stock="<?php echo $stock; ?>" data-detail-url="<?php echo $escape($detailUrl); ?>" role="link" tabindex="0">
                    <div class="product-image-wrap">
                        <button class="wishlist-btn active" data-product-id="<?php echo $id; ?>" title="Bỏ yêu thích">♥</button>
                        <img src="<?php echo $escape($imgSrc); ?>" alt="<?php echo $escape($name); ?>" class="product-image" onerror="this.onerror=null;this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>';">
                    </div>
                    <div class="product-info">
                        <h3><?php echo $escape($name); ?></h3>
                        <div class="product-price">
                            <span class="price-sale"><?php echo number_format($priceSale, 0, ',', '.'); ?><sup>đ</sup></span>
                            <?php if ($priceOriginal > $priceSale): ?>
                                <span class="price-original"><?php echo number_format($priceOriginal, 0, ',', '.'); ?><sup>đ</sup></span>
                            <?php endif; ?>
                        </div>
                        <div class="product-meta">
                            <?php if ($sold > 0): ?>
                                <span class="sold">Đã bán <?php echo $sold; ?></span>
                            <?php else: ?>
                                <span class="product-meta-empty" aria-hidden="true"></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($reviewCount > 0): ?>
                            <div class="product-rating" aria-label="Đánh giá <?php echo number_format($averageRating, 1, '.', ''); ?> trên 5 sao, <?php echo $reviewCount; ?> lượt">
                                <span class="product-rating-star" aria-hidden="true">★</span>
                                <span><?php echo number_format($averageRating, 1, '.', ''); ?></span>
                                <span class="product-rating-count">(<?php echo $reviewCount; ?>)</span>
                            </div>
                        <?php else: ?>
                            <div class="product-rating product-rating--empty" aria-hidden="true"></div>
                        <?php endif; ?>
                        <?php $maxQuantity = min($stock, 99); ?>
                        <div class="product-purchase-controls">
                            <div class="product-quantity-control" role="group" aria-label="Chọn số lượng <?php echo $escape($name); ?>">
                                <button type="button" class="product-quantity-decrease" aria-label="Giảm số lượng"<?php echo $maxQuantity <= 0 ? ' disabled' : ''; ?>>−</button>
                                <input class="product-quantity" type="number" min="1" max="<?php echo max($maxQuantity, 1); ?>" value="1" inputmode="numeric" aria-label="Số lượng <?php echo $escape($name); ?>"<?php echo $maxQuantity <= 0 ? ' disabled' : ''; ?>>
                                <button type="button" class="product-quantity-increase" aria-label="Tăng số lượng"<?php echo $maxQuantity <= 0 ? ' disabled' : ''; ?>>+</button>
                            </div>
                            <div class="product-card-actions">
                                <button type="button" class="add-to-cart" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                                    <?php echo $stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'; ?>
                                </button>
                                <button type="button" class="buy-now" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>Mua ngay</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- PHÂN TRANG WISHLIST (5 DÒNG: 15 SẢN PHẨM) -->
    <?php if ($totalPages > 1): ?>
        <nav class="pagination-controls" aria-label="Phân trang sản phẩm yêu thích">
            <a href="?page=<?php echo max(1, $currentPage - 1); ?>" class="pagination-btn pagination-prev <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" aria-label="Trang trước">‹</a>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?page=<?php echo $p; ?>" class="pagination-btn <?php echo $p === $currentPage ? 'active' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
            <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>" class="pagination-btn pagination-next <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" aria-label="Trang sau">›</a>
        </nav>
    <?php endif; ?>
    <!-- KHUNG TRỐNG (NẾU CHƯA CÓ SẢN PHẨM YÊU THÍCH) -->
    <div class="empty-wishlist" id="empty-wishlist" style="<?php echo empty($wishlistItems) ? 'display: block;' : 'display: none;'; ?>">
        <div class="empty-wishlist-icon">♡</div>
        <h3>Danh sách yêu thích đang trống</h3>
        <p><?php echo $userId > 0
            ? 'Hãy nhấn vào biểu tượng trái tim trên các món trang sức bạn yêu thích để dễ dàng lưu lại và xem lại bất cứ lúc nào!'
            : 'Vui lòng đăng nhập để lưu và xem danh sách sản phẩm yêu thích.'; ?></p>
        <?php if ($userId > 0): ?>
            <a href="<?php echo $escape($appBaseUrl . '/index.php'); ?>" class="btn-explore-wishlist">Khám phá bộ sưu tập ÉCRIN</a>
        <?php else: ?>
            <a href="#" class="btn-explore-wishlist login-trigger">Đăng nhập tài khoản</a>
        <?php endif; ?>
    </div>
</main>

<script>
// Khách chưa đăng nhập không có danh sách yêu thích tạm trên trình duyệt.
document.addEventListener('DOMContentLoaded', () => {
    if (typeof getCurrentCustomer === 'function' && getCurrentCustomer()) return;
    localStorage.removeItem('ecrin_wishlist');
    const container = document.getElementById('wishlist-products-container');
    const emptyBox = document.getElementById('empty-wishlist');
    const badge = document.getElementById('wishlist-total-badge');
    if (container) container.innerHTML = '';
    if (emptyBox) emptyBox.style.display = 'block';
    if (badge) badge.textContent = '0 sản phẩm';
});
</script>

<?php include VIEW_PATH . '/layouts/chan_trang.php'; ?>
