<?php
// client/wishlist.php
// Tác dụng: Trang hiển thị danh sách sản phẩm yêu thích (thả tim) của khách hàng
require_once __DIR__ . '/../config/db.php';
session_start();

$pageTitle = "Sản phẩm yêu thích - ÉCRIN JEWELRY";
include __DIR__ . '/../include/header.php';

$userId = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer' ? (int)$_SESSION['user_id'] : 0;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$wishlistItems = [];
if ($userId > 0) {
    $stmt = $conn->prepare("
        SELECT w.id AS wishlist_id,
               p.id, p.name, p.category_id, p.price_sale, p.price_original, p.stock, p.image, p.discount, p.gift, p.description,
               c.name AS category_name, c.slug AS category,
               COALESCE(SUM(oi.quantity), 0) AS sold_count
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE w.user_id = ?
        GROUP BY p.id
        ORDER BY w.id DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $wishlistItems[] = $row;
    }
    $stmt->close();
}
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
            <span class="wishlist-total-badge" id="wishlist-total-badge"><?php echo count($wishlistItems); ?> sản phẩm</span>
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
                    $img = $item['image'];
                    $imgSrc = (str_starts_with($img, 'http://') || str_starts_with($img, 'https://'))
                        ? $img
                        : $appBaseUrl . '/' . ltrim($img, '/');
                    $detailUrl = $appBaseUrl . '/client/product.php?id=' . $id;
                ?>
                <div class="product-card" data-db-id="<?php echo $id; ?>" data-detail-url="<?php echo $escape($detailUrl); ?>" role="link" tabindex="0">
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
                            <span class="stock-status <?php echo $stock > 0 ? 'stock-in' : 'stock-out'; ?>">
                                <?php echo $stock > 0 ? 'Còn hàng' : 'Tạm hết hàng'; ?>
                            </span>
                            <?php if ($sold > 0): ?>
                                <span class="product-sold">Đã bán <?php echo $sold; ?></span>
                            <?php endif; ?>
                        </div>
                        <button class="add-to-cart" <?php echo $stock <= 0 ? 'disabled style="background:#bbb;cursor:not-allowed;"' : ''; ?>>
                            <?php echo $stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'; ?>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

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
                    container.innerHTML = favProducts.map(p => {
                        const priceSaleRaw = (typeof p.price_sale_raw !== 'undefined') ? p.price_sale_raw : parsePrice(p.price_sale);
                        const hasDiscount = Boolean((p.discount && p.discount.trim() !== '') || (p.price_original_raw > p.price_sale_raw));
                        const hasGift = Boolean(p.gift && p.gift.trim() !== '');
                        const stock = Number(p.stock) || 0;
                        const sold = Number(p.sold_count) || 0;
                        const priceSaleFormatted = typeof p.price_sale === 'string' && p.price_sale.includes('.') ? p.price_sale : formatPrice(p.price_sale);
                        const priceOriginalFormatted = p.price_original ? (typeof p.price_original === 'string' && p.price_original.includes('.') ? p.price_original : formatPrice(p.price_original)) : '';
                        const priceOriginalHTML = p.price_original && parsePrice(p.price_original) > 0 ? `<span class="price-original">${priceOriginalFormatted}<sup>đ</sup></span>` : '';
                        const badgeHTML = hasDiscount ? `<span class="product-badge">${p.discount || 'ƯU ĐÃI'}</span>` : '';
                        const stockHTML = stock > 0 ? `<span class="stock-status stock-in">Còn hàng</span>` : `<span class="stock-status stock-out">Tạm hết hàng</span>`;
                        const soldHTML = sold > 0 ? `<span class="product-sold">Đã bán ${sold}</span>` : '';

                        return `
                            <div class="product-card" data-db-id="${p.id}" data-detail-url="${window.APP_BASE_URL}/client/product.php?id=${encodeURIComponent(p.id)}" role="link" tabindex="0">
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
                                    <div class="product-meta">
                                        ${stockHTML}
                                        ${soldHTML}
                                    </div>
                                    <button class="add-to-cart"${stock <= 0 ? ' disabled style="background:#bbb;cursor:not-allowed;"' : ''}>${stock <= 0 ? 'Tạm hết hàng' : 'Thêm vào giỏ'}</button>
                                </div>
                            </div>
                        `;
                    }).join('');
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
