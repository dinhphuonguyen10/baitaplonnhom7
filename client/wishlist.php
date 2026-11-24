<?php
// client/wishlist.php
// Tác dụng: Trang hiển thị danh sách sản phẩm yêu thích (thả tim) của khách hàng
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../include/session.php';

$pageTitle = "Sản phẩm yêu thích - ÉCRIN JEWELRY";
include __DIR__ . '/../include/header.php';

$userId = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer' ? (int)$_SESSION['user_id'] : 0;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

// Phân trang danh sách yêu thích (5 dòng: 3 cột × 5 dòng = 15 sản phẩm/trang)
$itemsPerPage = 15;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;
$totalWishlistItems = 0;

$wishlistItems = [];
if ($userId > 0) {
    // 1. Đếm tổng số lượng yêu thích bằng SQL
    $cntStmt = $conn->prepare("SELECT COUNT(*) AS total FROM wishlists WHERE user_id = ?");
    $cntStmt->bind_param('i', $userId);
    $cntStmt->execute();
    $totalWishlistItems = (int)($cntStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $cntStmt->close();

    // 2. Truy vấn phân trang LIMIT & OFFSET hoàn toàn bằng SQL
    $stmt = $conn->prepare("
        SELECT w.id AS wishlist_id,
               p.id, p.name, p.category_id, p.price_sale, p.price_original, p.stock, p.image, p.discount, p.gift, p.description,
               c.name AS category_name, c.slug AS category,
               COALESCE(SUM(oi.quantity), 0) AS sold_count,
               COALESCE(review_stats.average_rating, 0) AS average_rating,
               COALESCE(review_stats.review_count, 0) AS review_count
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN (
            SELECT r.product_id, ROUND(AVG(r.rating), 1) AS average_rating, COUNT(*) AS review_count
            FROM reviews r
            INNER JOIN users reviewer ON reviewer.id = r.user_id
            WHERE r.rating BETWEEN 1 AND 5
              AND EXISTS (
                  SELECT 1
                  FROM orders o
                  INNER JOIN order_items reviewed_item ON reviewed_item.order_id = o.id
                  WHERE o.user_id = r.user_id
                    AND reviewed_item.product_id = r.product_id
                    AND o.status IN ('Đã giao hàng', 'Đã giao')
              )
            GROUP BY r.product_id
        ) review_stats ON review_stats.product_id = p.id
        WHERE w.user_id = ?
        GROUP BY p.id
        ORDER BY w.id DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param('iii', $userId, $itemsPerPage, $offset);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $wishlistItems[] = $row;
    }
    $stmt->close();
}

$totalPages = $totalWishlistItems > 0 ? (int)ceil($totalWishlistItems / $itemsPerPage) : 1;
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
                    $detailUrl = $appBaseUrl . '/client/product.php?id=' . $id;
                ?>
                <div class="product-card" data-db-id="<?php echo $id; ?>" data-stock="<?php echo $stock; ?>" data-detail-url="<?php echo $escape($detailUrl); ?>" role="link" tabindex="0">
                    <div class="product-image-wrap">
                        <button class="wishlist-btn active" data-product-id="<?php echo $id; ?>" title="Bỏ yêu thích">♥</button>
                        <img src="<?php echo $escape($imgSrc); ?>" alt="<?php echo $escape($name); ?>" class="product-image" onerror="this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>';">
                    </div>
                    <div class="product-info">
                        <h3><?php echo $escape($name); ?></h3>
                        <div class="product-price">
                            <span class="price-sale"><?php echo number_format($priceSale, 0, ',', '.'); ?><sup>đ</sup></span>
                            <?php if ($priceOriginal > 0): ?>
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
    <div id="guest-wishlist-pagination"></div>

    <!-- KHUNG TRỐNG (NẾU CHƯA CÓ SẢN PHẨM YÊU THÍCH) -->
    <div class="empty-wishlist" id="empty-wishlist" style="<?php echo empty($wishlistItems) ? 'display: block;' : 'display: none;'; ?>">
        <div class="empty-wishlist-icon">♡</div>
        <h3>Danh sách yêu thích đang trống</h3>
        <p>Hãy nhấn vào biểu tượng trái tim trên các món trang sức bạn yêu thích để dễ dàng lưu lại và xem lại bất cứ lúc nào!</p>
        <a href="<?php echo $escape($appBaseUrl . '/index.php'); ?>" class="btn-explore-wishlist">Khám phá bộ sưu tập ÉCRIN</a>
    </div>
</main>

<script>
// Script chuyên biệt cho trang Wishlist: cập nhật real-time khi khách thả tim / bỏ tim
document.addEventListener('DOMContentLoaded', async () => {
    // Nếu khách vãng lai, tải danh sách yêu thích từ localStorage
    const customer = typeof getCurrentCustomer === 'function' ? getCurrentCustomer() : null;
    const container = document.getElementById('wishlist-products-container');
    const emptyBox = document.getElementById('empty-wishlist');
    const badge = document.getElementById('wishlist-total-badge');

    if (!customer) {
        let localIds = [];
        try {
            localIds = JSON.parse(localStorage.getItem('ecrin_wishlist') || '[]');
        } catch {}

        if (localIds.length > 0) {
            try {
                // Tải tất cả sản phẩm và lọc theo localIds
                const res = await fetch(window.APP_BASE_URL + '/api/catalog/get_products.php');
                const products = await res.json();
                const favProducts = products.filter(p => localIds.includes(Number(p.id)));

                if (favProducts.length > 0) {
                    emptyBox.style.display = 'none';
                    badge.textContent = favProducts.length + ' sản phẩm';

                    // Phân trang Client-side cho khách vãng lai (15 sản phẩm = 5 dòng)
                    const WISHLIST_PER_PAGE = 15;
                    const urlParams = new URLSearchParams(window.location.search);
                    let guestPage = parseInt(urlParams.get('page'), 10) || 1;
                    const totalGuestPages = Math.ceil(favProducts.length / WISHLIST_PER_PAGE);
                    if (guestPage > totalGuestPages) guestPage = totalGuestPages;
                    if (guestPage < 1) guestPage = 1;

                    const startIdx = (guestPage - 1) * WISHLIST_PER_PAGE;
                    const pagedProducts = favProducts.slice(startIdx, startIdx + WISHLIST_PER_PAGE);

                    container.innerHTML = pagedProducts.map(p => {
                        const priceSaleRaw = (typeof p.price_sale_raw !== 'undefined') ? p.price_sale_raw : parsePrice(p.price_sale);
                        const hasDiscount = Boolean((p.discount && p.discount.trim() !== '') || (p.price_original_raw > p.price_sale_raw));
                        const stock = Number(p.stock) || 0;
                        const sold = Number(p.sold_count) || 0;
                        const priceSaleFormatted = typeof p.price_sale === 'string' && p.price_sale.includes('.') ? p.price_sale : formatPrice(p.price_sale);
                        const priceOriginalFormatted = p.price_original ? (typeof p.price_original === 'string' && p.price_original.includes('.') ? p.price_original : formatPrice(p.price_original)) : '';
                        const priceOriginalHTML = p.price_original && parsePrice(p.price_original) > 0 ? `<span class="price-original">${priceOriginalFormatted}<sup>đ</sup></span>` : '';
                        const badgeHTML = hasDiscount ? `<span class="product-badge">${p.discount || 'ƯU ĐÃI'}</span>` : '';
                        const metaHTML = renderProductMeta(sold);

                        return `
                            <div class="product-card" data-db-id="${p.id}" data-stock="${stock}" data-detail-url="${window.APP_BASE_URL}/client/product.php?id=${encodeURIComponent(p.id)}" role="link" tabindex="0">
                                <div class="product-image-wrap">
                                    ${badgeHTML}
                                    <button class="wishlist-btn active" data-product-id="${p.id}" title="Bỏ yêu thích">♥</button>
                                    <img src="${typeof getProductImageUrl === 'function' ? getProductImageUrl(p.image) : p.image}" alt="${p.name}" class="product-image">
                                </div>
                                <div class="product-info">
                                    <h3>${p.name}</h3>
                                    <div class="product-price">
                                        <span class="price-sale">${priceSaleFormatted}<sup>đ</sup></span>
                                        ${priceOriginalHTML}
                                    </div>
                                    ${metaHTML}
                                    ${renderProductRating(p)}
                                    <div class="product-purchase-controls">
                                        ${typeof getProductQuantityControlMarkup === 'function' ? getProductQuantityControlMarkup(stock, p.name) : ''}
                                        <div class="product-card-actions">
                                            <button type="button" class="add-to-cart"${stock <= 0 ? ' disabled' : ''}>${stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'}</button>
                                            <button type="button" class="buy-now"${stock <= 0 ? ' disabled' : ''}>Mua ngay</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');

                    // Render phân trang cho khách vãng lai nếu > 1 trang
                    const guestPagEl = document.getElementById('guest-wishlist-pagination');
                    if (guestPagEl && totalGuestPages > 1) {
                        let pagHtml = '<nav class="pagination-controls" aria-label="Phân trang sản phẩm yêu thích">';
                        pagHtml += `<a href="?page=${Math.max(1, guestPage - 1)}" class="pagination-btn pagination-prev ${guestPage <= 1 ? 'disabled' : ''}">‹</a>`;
                        for (let p = 1; p <= totalGuestPages; p++) {
                            pagHtml += `<a href="?page=${p}" class="pagination-btn ${p === guestPage ? 'active' : ''}">${p}</a>`;
                        }
                        pagHtml += `<a href="?page=${Math.min(totalGuestPages, guestPage + 1)}" class="pagination-btn pagination-next ${guestPage >= totalGuestPages ? 'disabled' : ''}">›</a>`;
                        pagHtml += '</nav>';
                        guestPagEl.innerHTML = pagHtml;
                    }
                } else {
                    container.innerHTML = '';
                    emptyBox.style.display = 'block';
                    badge.textContent = '0 sản phẩm';
                }
            } catch (err) {
                console.error(err);
            }
        } else {
            container.innerHTML = '';
            emptyBox.style.display = 'block';
            badge.textContent = '0 sản phẩm';
        }
    }
});
</script>

<?php include __DIR__ . '/../include/footer.php'; ?>
