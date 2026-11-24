<?php
// api/catalog/get_products.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';

// Lấy danh sách sản phẩm yêu thích (wishlist) của khách hàng hiện tại
$userWishlist = [];
if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer') {
    $uid = (int)$_SESSION['user_id'];
    $wlRes = $conn->query("SELECT product_id FROM wishlists WHERE user_id = {$uid}");
    if ($wlRes) {
        while ($wlRow = $wlRes->fetch_assoc()) {
            $userWishlist[(int)$wlRow['product_id']] = true;
        }
    }
}

$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort     = isset($_GET['sort']) ? trim($_GET['sort']) : '';

// 1. TRANG CHỦ - Sản phẩm nổi bật: Top 10 sản phẩm có nhiều lượt bán nhất (bán chạy nhất)
if ($category === 'featured') {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' ORDER BY sold_count DESC, id DESC LIMIT 10");
}
// 2. TRANG CHỦ - Gợi ý cho bạn: Top 10 sản phẩm có nhiều lượt tim nhất (yêu thích nhất)
elseif ($category === 'suggest') {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' ORDER BY wishlist_count DESC, id DESC LIMIT 10");
}
// 3. TRANG CHỦ - Sản phẩm mới nhất: Top 10 sản phẩm mới được thêm vào gần đây nhất (ID mới nhất)
elseif ($category === 'new' || $category === 'latest') {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' ORDER BY id DESC LIMIT 10");
}
// 4. TRANG DANH MỤC CỤ THỂ (Nhẫn, Bông tai, Dây chuyền, Vòng tay, Set quà tặng)
// Lọc và sắp xếp hoàn toàn bằng SQL theo tiêu chí truyền từ JS:
elseif ($category !== '') {
    // --- Sắp xếp ---
    $orderBy = "sold_count DESC, id DESC"; // Mặc định: Bán chạy nhất trong danh mục
    if ($sort === 'popular' || $sort === 'suggest') {
        $orderBy = "wishlist_count DESC, id DESC"; // Gợi ý: nhiều tim nhất trong danh mục
    } elseif ($sort === 'newest') {
        $orderBy = "id DESC"; // Mới nhất trong danh mục
    } elseif ($sort === 'price_asc') {
        $orderBy = "price_sale ASC, id DESC"; // Giá từ thấp đến cao
    } elseif ($sort === 'price_desc') {
        $orderBy = "price_sale DESC, id DESC"; // Giá từ cao đến thấp
    }

    // --- Bộ lọc (filter) từ JS ---
    $minPrice   = isset($_GET['min_price'])    ? (float)$_GET['min_price']    : null;
    $maxPrice   = isset($_GET['max_price'])    ? (float)$_GET['max_price']    : null;
    $inStock    = isset($_GET['in_stock'])     && $_GET['in_stock']     === '1';
    $hasDiscount= isset($_GET['has_discount']) && $_GET['has_discount'] === '1';
    $hasGift    = isset($_GET['has_gift'])     && $_GET['has_gift']     === '1';

    // Xây dựng WHERE động - bắt buộc loại trừ sản phẩm đã xóa mềm hoặc không active
    $where  = "is_deleted = 0 AND status = 'active' AND (category = ? OR category_name = ? OR CAST(category_id AS CHAR) = ?)";
    $params = [$category, $category, $category];
    $types  = "sss";

    if ($minPrice !== null) {
        $where   .= " AND price_sale >= ?";
        $params[] = $minPrice;
        $types   .= "d";
    }
    if ($maxPrice !== null) {
        $where   .= " AND price_sale <= ?";
        $params[] = $maxPrice;
        $types   .= "d";
    }
    if ($inStock) {
        $where .= " AND stock > 0";
    }
    if ($hasDiscount) {
        $where .= " AND (discount IS NOT NULL AND discount != '' OR price_original > price_sale)";
    }
    if ($hasGift) {
        $where .= " AND gift IS NOT NULL AND gift != ''";
    }

    $stmt = $conn->prepare("SELECT * FROM view_product_details WHERE {$where} ORDER BY {$orderBy}");
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
}
// 5. Mặc định: Lấy tất cả sản phẩm đang kinh doanh và chưa bị xóa
else {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' ORDER BY id DESC");
}

$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['average_rating']     = (float)($row['average_rating'] ?? 0);
        $row['review_count']       = (int)($row['review_count'] ?? 0);
        $row['stock']              = (int)($row['stock'] ?? 0);
        $row['sold_count']         = (int)($row['sold_count'] ?? 0);
        $row['wishlist_count']     = (int)($row['wishlist_count'] ?? 0);
        $row['is_featured']        = (int)($row['is_featured'] ?? 0);
        $row['is_suggested']       = (int)($row['is_suggested'] ?? 0);
        $row['is_new']             = (int)($row['is_new'] ?? 0);
        $row['price_sale_raw']     = (float)$row['price_sale'];
        $row['price_original_raw'] = (float)($row['price_original'] ?? 0);

        // Định dạng giá hiển thị tiền tệ
        $row['price_sale']     = number_format((float)$row['price_sale'], 0, ',', '.');
        $row['price_original'] = $row['price_original_raw'] > 0 ? number_format($row['price_original_raw'], 0, ',', '.') : null;
        $row['is_favorite']    = isset($userWishlist[(int)$row['id']]);

        $products[] = $row;
    }
}

echo json_encode($products, JSON_UNESCAPED_UNICODE);
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
