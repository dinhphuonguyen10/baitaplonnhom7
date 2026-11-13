<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product = null;

if ($productId !== false && $productId !== null && $productId > 0) {
    $stmt = $conn->prepare('SELECT * FROM view_product_details WHERE id = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Không thể chuẩn bị truy vấn chi tiết sản phẩm: ' . $conn->error);
    }

    $stmt->bind_param('i', $productId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Không thể tải chi tiết sản phẩm: ' . $stmt->error);
    }
    $product = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

if ($product === null) {
    http_response_code(404);
}

$pageTitle = $product
    ? $product['name'] . ' - ÉCRIN JEWELRY'
    : 'Không tìm thấy sản phẩm - ÉCRIN JEWELRY';
include __DIR__ . '/../include/header.php';

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$descriptionByCategory = [
    'bong-tai' => 'Một thiết kế bông tai thanh lịch, dễ kết hợp cùng trang phục thường ngày hoặc những dịp đặc biệt.',
    'day-chuyen' => 'Mẫu dây chuyền tinh tế giúp hoàn thiện phong cách và là lựa chọn ý nghĩa để dành tặng người thân.',
    'nhan' => 'Thiết kế nhẫn trang nhã, phù hợp để tạo điểm nhấn cho phong cách riêng hoặc làm quà tặng.',
    'vong-tay' => 'Mẫu vòng tay duyên dáng, dễ phối cùng nhiều phong cách và thích hợp làm món quà tinh tế.',
    'set-qua-tang' => 'Set quà tặng được chuẩn bị chỉn chu, phù hợp dành tặng người thân yêu trong những dịp đáng nhớ.',
];
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
        <?php
        $imagePath = (string) $product['image'];
        $imageUrl = preg_match('~^https?://~i', $imagePath)
            ? $imagePath
            : $appBaseUrl . '/' . ltrim($imagePath, '/');
        $stock = (int) $product['stock'];
        $rawDescription = trim((string)($product['description'] ?? ''));
        $description = $rawDescription !== ''
            ? $rawDescription
            : ($descriptionByCategory[$product['category']] ?? 'Một thiết kế được ÉCRIN tuyển chọn để bạn dễ dàng tìm thấy món trang sức phù hợp với phong cách của mình.');
        ?>
        <section class="product-detail-card product-card" data-db-id="<?php echo (int) $product['id']; ?>" aria-labelledby="product-name">
            <!-- CỘT TRÁI: HÌNH ẢNH SẢN PHẨM VUÔNG KHÍT KHUNG -->
            <div class="product-detail-image-wrap">
                <img
                    class="product-detail-image product-image"
                    src="<?php echo $escape($imageUrl); ?>"
                    alt="<?php echo $escape($product['name']); ?>"
                    onerror="this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>';"
                >
            </div>

            <!-- CỘT PHẢI: THÔNG TIN CHI TIẾT SẢN PHẨM & MUA HÀNG -->
            <div class="product-detail-info">
                <p class="product-detail-eyebrow"><?php echo $escape($product['category_name']); ?> · ÉCRIN JEWELRY</p>
                <h1 id="product-name"><?php echo $escape($product['name']); ?></h1>
                <div class="product-detail-price product-price">
                    <span class="price-sale"><?php echo number_format((float) $product['price_sale'], 0, ',', '.'); ?><sup>đ</sup></span>
                    <?php if ((float) $product['price_original'] > 0): ?>
                        <span class="price-original"><?php echo number_format((float) $product['price_original'], 0, ',', '.'); ?><sup>đ</sup></span>
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
                        <dd class="<?php echo $stock > 0 ? 'product-detail-stock' : 'product-detail-out-of-stock'; ?>">
                            <?php echo $stock > 0 ? 'Còn hàng (' . $stock . ')' : 'Tạm hết hàng'; ?>
                        </dd>
                    </div>
                    <?php if ((int) $product['sold_count'] > 0): ?>
                        <div>
                            <dt>Đã bán</dt>
                            <dd><?php echo (int) $product['sold_count']; ?> sản phẩm</dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($product['gift'])): ?>
                    <p class="product-detail-gift">🎁 <?php echo $escape($product['gift']); ?></p>
                <?php endif; ?>

                <!-- CẶP NÚT MUA HÀNG & CHAT TƯ VẤN SANG TRỌNG -->
                <div class="product-detail-actions">
                    <button class="add-to-cart" <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        <?php echo $stock > 0 ? 'Thêm vào giỏ' : 'Tạm hết hàng'; ?>
                    </button>
                    <a class="btn-chat-consult" href="<?php echo $escape($appBaseUrl . '/client/chat.php'); ?>" title="Chat trực tiếp với cửa hàng">
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
        <?php include __DIR__ . '/danhgia.php'; ?>
    <?php else: ?>
        <section class="product-detail-not-found">
            <h1>Không tìm thấy sản phẩm</h1>
            <p>Sản phẩm có thể đã được gỡ khỏi danh mục hoặc đường dẫn không chính xác.</p>
            <a class="view-all-btn" href="<?php echo $escape($appBaseUrl . '/index.php'); ?>">Quay lại trang chủ</a>
        </section>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../include/footer.php'; ?>
