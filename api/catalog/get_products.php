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

$categoryInput = $_GET['category'] ?? '';
$sortInput = $_GET['sort'] ?? '';
if (!is_string($categoryInput) || !is_string($sortInput)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Bộ lọc danh mục không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$category = trim($categoryInput);
$sort = trim($sortInput);
$paginationMeta = null;

if (array_key_exists('ids', $_GET)) {
    $rawIds = $_GET['ids'];
    if (!is_string($rawIds)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Danh sách mã sản phẩm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $productIds = [];
    if (trim($rawIds) !== '') {
        foreach (explode(',', $rawIds) as $rawId) {
            $productId = filter_var($rawId, FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Danh sách mã sản phẩm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $productIds[$productId] = $productId;
        }
    }
    $productIds = array_values($productIds);
    if (count($productIds) > 100) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Chỉ được kiểm tra tối đa 100 sản phẩm mỗi lần.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($productIds === []) {
        $result = $conn->query('SELECT * FROM view_product_details WHERE 1 = 0');
    } else {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $conn->prepare(
            "SELECT * FROM view_product_details
             WHERE is_deleted = 0 AND status = 'active'
               AND category_is_deleted = 0 AND category_status = 'active'
               AND id IN ({$placeholders})"
        );
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Không thể kiểm tra trạng thái sản phẩm.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $types = str_repeat('i', count($productIds));
        $stmt->bind_param($types, ...$productIds);
        if (!$stmt->execute()) {
            $stmt->close();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Không thể kiểm tra trạng thái sản phẩm.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $result = $stmt->get_result();
    }
    if (!$result) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Không thể kiểm tra trạng thái sản phẩm.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
// 1. TRANG CHỦ - Sản phẩm nổi bật: Top 10 sản phẩm có nhiều lượt bán nhất (bán chạy nhất)
} elseif ($category === 'featured') {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' AND category_is_deleted = 0 AND category_status = 'active' ORDER BY sold_count DESC, id DESC LIMIT 10");
}
// 2. TRANG CHỦ - Gợi ý cho bạn: Top 10 sản phẩm có nhiều lượt tim nhất (yêu thích nhất)
elseif ($category === 'suggest') {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' AND category_is_deleted = 0 AND category_status = 'active' ORDER BY wishlist_count DESC, id DESC LIMIT 10");
}
// 3. TRANG CHỦ - Sản phẩm mới nhất: Top 10 sản phẩm mới được thêm vào gần đây nhất (ID mới nhất)
elseif ($category === 'new' || $category === 'latest') {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' AND category_is_deleted = 0 AND category_status = 'active' ORDER BY id DESC LIMIT 10");
}
// 4. Trang danh mục: lọc, đếm và phân trang trực tiếp trong MySQL.
elseif ($category !== '') {
    $sortOptions = [
        'best_selling' => 'sold_count DESC, id DESC',
        'popular' => 'wishlist_count DESC, id DESC',
        'suggest' => 'wishlist_count DESC, id DESC',
        'newest' => 'id DESC',
        'price_asc' => 'price_sale ASC, id DESC',
        'price_desc' => 'price_sale DESC, id DESC',
        'name_asc' => 'name ASC, id ASC',
    ];
    if (!isset($sortOptions[$sort])) {
        $sort = 'best_selling';
    }

    $sendCategoryError = static function (int $statusCode, string $message): void {
        http_response_code($statusCode);
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $readOptionalPrice = static function (string $key) use ($sendCategoryError): ?int {
        if (!array_key_exists($key, $_GET) || $_GET[$key] === '') {
            return null;
        }
        if (!is_string($_GET[$key])) {
            $sendCategoryError(400, 'Khoảng giá không hợp lệ.');
        }
        $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            $sendCategoryError(400, 'Giá phải là số nguyên không âm.');
        }
        return $value;
    };
    $minPrice = $readOptionalPrice('min_price');
    $maxPrice = $readOptionalPrice('max_price');
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        $sendCategoryError(400, 'Giá thấp nhất không được lớn hơn giá cao nhất.');
    }

    foreach (['in_stock', 'has_discount', 'has_gift'] as $filterKey) {
        if (isset($_GET[$filterKey])
            && (!is_string($_GET[$filterKey]) || !in_array($_GET[$filterKey], ['0', '1'], true))) {
            $sendCategoryError(400, 'Giá trị bộ lọc không hợp lệ.');
        }
    }
    $inStock = ($_GET['in_stock'] ?? '') === '1';
    $hasDiscount = ($_GET['has_discount'] ?? '') === '1';
    $hasGift = ($_GET['has_gift'] ?? '') === '1';

    $requestedPage = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
    if ($requestedPage === false || $requestedPage < 1) {
        $sendCategoryError(400, 'Số trang không hợp lệ.');
    }

    $categoryStmt = $conn->prepare(
        "SELECT id
         FROM categories
         WHERE status = 'active' AND is_deleted = 0
           AND (slug = ? OR name = ? OR CAST(id AS CHAR) = ?)
         LIMIT 1"
    );
    if (!$categoryStmt) {
        $sendCategoryError(500, 'Không thể kiểm tra danh mục.');
    }
    $categoryStmt->bind_param('sss', $category, $category, $category);
    if (!$categoryStmt->execute()) {
        $categoryStmt->close();
        $sendCategoryError(500, 'Không thể kiểm tra danh mục.');
    }
    $categoryRow = $categoryStmt->get_result()->fetch_assoc();
    $categoryStmt->close();

    $where = "is_deleted = 0 AND status = 'active'
              AND category_is_deleted = 0 AND category_status = 'active'
              AND category_id = ?";
    $params = [(int)($categoryRow['id'] ?? 0)];
    $types = 'i';
    if ($minPrice !== null) {
        $where .= ' AND price_sale >= ?';
        $params[] = $minPrice;
        $types .= 'i';
    }
    if ($maxPrice !== null) {
        $where .= ' AND price_sale <= ?';
        $params[] = $maxPrice;
        $types .= 'i';
    }
    if ($inStock) {
        $where .= ' AND stock > 0';
    }
    if ($hasDiscount) {
        $where .= " AND (discount IS NOT NULL AND discount <> '' OR price_original > price_sale)";
    }
    if ($hasGift) {
        $where .= " AND gift IS NOT NULL AND gift <> ''";
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM view_product_details WHERE {$where}");
    if (!$countStmt) {
        $sendCategoryError(500, 'Không thể đếm sản phẩm phù hợp.');
    }
    $countStmt->bind_param($types, ...$params);
    if (!$countStmt->execute()) {
        $countStmt->close();
        $sendCategoryError(500, 'Không thể đếm sản phẩm phù hợp.');
    }
    $totalItems = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();

    $perPage = 15;
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $currentPage = min($requestedPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    $pageParams = array_merge($params, [$perPage, $offset]);
    $stmt = $conn->prepare(
        "SELECT * FROM view_product_details
         WHERE {$where}
         ORDER BY {$sortOptions[$sort]}
         LIMIT ? OFFSET ?"
    );
    if (!$stmt) {
        $sendCategoryError(500, 'Không thể tải sản phẩm.');
    }
    $stmt->bind_param($types . 'ii', ...$pageParams);
    if (!$stmt->execute()) {
        $stmt->close();
        $sendCategoryError(500, 'Không thể tải sản phẩm.');
    }
    $result = $stmt->get_result();
    $paginationMeta = [
        'total' => $totalItems,
        'page' => $currentPage,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
    ];
}
// 5. Mặc định: Lấy tất cả sản phẩm đang kinh doanh và chưa bị xóa
else {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' AND category_is_deleted = 0 AND category_status = 'active' ORDER BY id DESC");
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
        $row['price_original'] = $row['price_original_raw'] > $row['price_sale_raw']
            ? number_format($row['price_original_raw'], 0, ',', '.')
            : null;
        $row['is_favorite']    = isset($userWishlist[(int)$row['id']]);

        $products[] = $row;
    }
}

echo json_encode(
    $paginationMeta === null
        ? $products
        : ['products' => $products] + $paginationMeta,
    JSON_UNESCAPED_UNICODE
);
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
