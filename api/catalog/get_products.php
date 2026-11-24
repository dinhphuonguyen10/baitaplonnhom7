<?php
// api/catalog/get_products.php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../../include/session.php';
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
$searchInput = $_GET['search'] ?? '';
$suggestInput = $_GET['suggest'] ?? '';
if (!is_string($categoryInput) || !is_string($sortInput) || !is_string($searchInput) || !is_string($suggestInput)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tham số tìm kiếm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$category = trim($categoryInput);
$sort = trim($sortInput);
$searchQuery = preg_replace('/\s+/u', ' ', trim($searchInput));
$searchRequested = array_key_exists('search', $_GET);
if ($searchQuery === null || ($searchRequested && ($searchQuery === '' || strlen($searchQuery) > 300))) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Từ khóa tìm kiếm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$suggestionMode = $suggestInput === '1';
if (($suggestInput !== '' && !in_array($suggestInput, ['0', '1'], true))
    || ($suggestionMode && !$searchRequested)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Yêu cầu gợi ý tìm kiếm không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$paginationMeta = null;
$homepageSections = null;

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
} elseif (in_array($category, ['home', 'featured', 'suggest', 'new', 'latest'], true)) {
    $sendHomeError = static function (string $message): void {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $fetchHomeRows = static function (string $sql, string $types = '', array $params = []) use ($conn, $sendHomeError): array {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('Homepage product query prepare failed: ' . $conn->error);
            $sendHomeError('Không thể tải sản phẩm trang chủ.');
        }
        if ($types !== '' && !$stmt->bind_param($types, ...$params)) {
            $stmt->close();
            error_log('Homepage product query binding failed: ' . $conn->error);
            $sendHomeError('Không thể tải sản phẩm trang chủ.');
        }
        if (!$stmt->execute()) {
            $stmt->close();
            error_log('Homepage product query failed: ' . $conn->error);
            $sendHomeError('Không thể tải sản phẩm trang chủ.');
        }
        $rows = [];
        $homeResult = $stmt->get_result();
        while ($row = $homeResult->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    };
    $homeSalesJoin = "LEFT JOIN (
        SELECT oi.product_id, SUM(oi.quantity) AS quantity_30d
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND o.created_at <= NOW()
          AND o.status IN ('Đã giao hàng', 'Đã giao', 'Hoàn thành')
          AND o.payment_status = 'Đã thanh toán'
        GROUP BY oi.product_id
    ) home_sales ON home_sales.product_id = p.id";
    $eligibleProducts = "p.is_deleted = 0 AND p.status = 'active'
        AND v.is_deleted = 0 AND v.status = 'active'
        AND v.category_is_deleted = 0 AND v.category_status = 'active'";
    $makeExclusion = static function (array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        $clause = $ids === []
            ? ''
            : ' AND p.id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        return [$clause, $ids];
    };
    $rowsToIds = static fn(array $rows): array => array_map(static fn(array $row): int => (int)$row['id'], $rows);

    $newestRows = $fetchHomeRows(
        "SELECT v.*, p.created_at AS product_created_at,
                COALESCE(home_sales.quantity_30d, 0) AS home_sold_count
         FROM view_product_details v
         INNER JOIN products p ON p.id = v.id
         {$homeSalesJoin}
         WHERE {$eligibleProducts}
         ORDER BY p.created_at DESC, p.id DESC
         LIMIT 10"
    );

    [$excludeNewestSql, $newestIds] = $makeExclusion($rowsToIds($newestRows));
    $bestSellingRows = $fetchHomeRows(
        "SELECT v.*, COALESCE(home_sales.quantity_30d, 0) AS home_sold_count
         FROM view_product_details v
         INNER JOIN products p ON p.id = v.id
         {$homeSalesJoin}
         WHERE {$eligibleProducts}
           AND home_sales.quantity_30d > 0
           {$excludeNewestSql}
         ORDER BY home_sales.quantity_30d DESC, p.id DESC
         LIMIT 10",
        str_repeat('i', count($newestIds)),
        $newestIds
    );

    $preferredCategoryId = null;
    if (!empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer') {
        $customerId = (int)$_SESSION['user_id'];
        $purchaseCategories = $fetchHomeRows(
            "SELECT p.category_id, SUM(oi.quantity) AS purchased_quantity
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             INNER JOIN products p ON p.id = oi.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE o.user_id = ?
               AND o.status IN ('Đã giao hàng', 'Đã giao', 'Hoàn thành')
               AND o.payment_status = 'Đã thanh toán'
               AND c.status = 'active' AND c.is_deleted = 0
             GROUP BY p.category_id
             ORDER BY purchased_quantity DESC, p.category_id ASC
             LIMIT 1",
            'i',
            [$customerId]
        );
        if ($purchaseCategories !== []) {
            $preferredCategoryId = (int)$purchaseCategories[0]['category_id'];
        }
    }

    $loadRecommendationRows = static function (?int $categoryId, array $excludeIds) use (
        $fetchHomeRows,
        $homeSalesJoin,
        $eligibleProducts,
        $makeExclusion
    ): array {
        [$excludeSql, $excludeIds] = $makeExclusion($excludeIds);
        $categoryClause = $categoryId === null ? '' : ' AND p.category_id = ?';
        $types = $categoryId === null ? '' : 'i';
        $params = $categoryId === null ? [] : [$categoryId];
        if ($excludeIds !== []) {
            $types .= str_repeat('i', count($excludeIds));
            array_push($params, ...$excludeIds);
        }
        return $fetchHomeRows(
            "SELECT v.*, COALESCE(home_sales.quantity_30d, 0) AS home_sold_count
             FROM view_product_details v
             INNER JOIN products p ON p.id = v.id
             {$homeSalesJoin}
             WHERE {$eligibleProducts}
               {$categoryClause}
               AND (COALESCE(home_sales.quantity_30d, 0) > 0 OR v.wishlist_count > 0)
               {$excludeSql}
             ORDER BY COALESCE(home_sales.quantity_30d, 0) DESC, v.wishlist_count DESC, p.id DESC
             LIMIT 10",
            $types,
            $params
        );
    };
    $excludedForSuggestions = array_merge($newestIds, $rowsToIds($bestSellingRows));
    $suggestionRows = $loadRecommendationRows($preferredCategoryId, $excludedForSuggestions);
    if (count($suggestionRows) < 10) {
        $suggestionRows = array_merge(
            $suggestionRows,
            $loadRecommendationRows(null, array_merge($excludedForSuggestions, $rowsToIds($suggestionRows)))
        );
        $suggestionRows = array_slice($suggestionRows, 0, 10);
    }

    $homepageSections = [
        'new' => $newestRows,
        'featured' => $bestSellingRows,
        'suggest' => $suggestionRows,
    ];
}
// 4. Trang danh mục và tìm kiếm: lọc, đếm và phân trang trực tiếp trong MySQL.
elseif ($category !== '' || $searchQuery !== '') {
    $sortOptions = [
        'best_selling' => 'sold_count DESC, id DESC',
        'popular' => 'wishlist_count DESC, id DESC',
        'suggest' => 'wishlist_count DESC, id DESC',
        'newest' => 'id DESC',
        'price_asc' => 'price_sale ASC, id DESC',
        'price_desc' => 'price_sale DESC, id DESC',
        'name_asc' => 'name ASC, id ASC',
    ];
    if ($sort === '') {
        $sort = $searchQuery !== '' ? 'relevance' : 'best_selling';
    }
    if (!isset($sortOptions[$sort]) && !($searchQuery !== '' && $sort === 'relevance')) {
        $sort = $searchQuery !== '' ? 'relevance' : 'best_selling';
    }

    $sendListingError = static function (int $statusCode, string $message): void {
        http_response_code($statusCode);
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $readOptionalPrice = static function (string $key) use ($sendListingError): ?int {
        if (!array_key_exists($key, $_GET) || $_GET[$key] === '') {
            return null;
        }
        if (!is_string($_GET[$key])) {
            $sendListingError(400, 'Khoảng giá không hợp lệ.');
        }
        $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            $sendListingError(400, 'Giá phải là số nguyên không âm.');
        }
        return $value;
    };
    $minPrice = $readOptionalPrice('min_price');
    $maxPrice = $readOptionalPrice('max_price');
    if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
        $sendListingError(400, 'Giá thấp nhất không được lớn hơn giá cao nhất.');
    }

    foreach (['in_stock', 'has_discount', 'has_gift'] as $filterKey) {
        if (isset($_GET[$filterKey])
            && (!is_string($_GET[$filterKey]) || !in_array($_GET[$filterKey], ['0', '1'], true))) {
            $sendListingError(400, 'Giá trị bộ lọc không hợp lệ.');
        }
    }
    $inStock = ($_GET['in_stock'] ?? '') === '1';
    $hasDiscount = ($_GET['has_discount'] ?? '') === '1';
    $hasGift = ($_GET['has_gift'] ?? '') === '1';

    $requestedPage = 1;
    if (!$suggestionMode) {
        $requestedPage = filter_var($_GET['page'] ?? '1', FILTER_VALIDATE_INT);
        if ($requestedPage === false || $requestedPage < 1) {
            $sendListingError(400, 'Số trang không hợp lệ.');
        }
    }

    $where = "is_deleted = 0 AND status = 'active'
              AND category_is_deleted = 0 AND category_status = 'active'";
    $params = [];
    $types = '';
    if ($category !== '') {
        $categoryStmt = $conn->prepare(
            "SELECT id
             FROM categories
             WHERE status = 'active' AND is_deleted = 0
               AND (slug = ? OR name = ? OR CAST(id AS CHAR) = ?)
             LIMIT 1"
        );
        if (!$categoryStmt) {
            $sendListingError(500, 'Không thể kiểm tra danh mục.');
        }
        $categoryStmt->bind_param('sss', $category, $category, $category);
        if (!$categoryStmt->execute()) {
            $categoryStmt->close();
            $sendListingError(500, 'Không thể kiểm tra danh mục.');
        }
        $categoryRow = $categoryStmt->get_result()->fetch_assoc();
        $categoryStmt->close();
        $where .= ' AND category_id = ?';
        $params[] = (int)($categoryRow['id'] ?? 0);
        $types .= 'i';
    }
    $searchPattern = '';
    if ($searchQuery !== '') {
        $escapedSearch = strtr($searchQuery, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $searchPattern = '%' . $escapedSearch . '%';
        $where .= " AND (
            name LIKE ? ESCAPE '!'
            OR sku LIKE ? ESCAPE '!'
            OR category_name LIKE ? ESCAPE '!'
            OR description LIKE ? ESCAPE '!'
        )";
        array_push($params, $searchPattern, $searchPattern, $searchPattern, $searchPattern);
        $types .= 'ssss';
    }
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

    $totalItems = 0;
    if (!$suggestionMode) {
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM view_product_details WHERE {$where}");
        if (!$countStmt) {
            $sendListingError(500, 'Không thể đếm sản phẩm phù hợp.');
        }
        $countStmt->bind_param($types, ...$params);
        if (!$countStmt->execute()) {
            $countStmt->close();
            $sendListingError(500, 'Không thể đếm sản phẩm phù hợp.');
        }
        $totalItems = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
        $countStmt->close();
    }

    $perPage = $suggestionMode ? 8 : 12;
    $totalPages = max(1, (int)ceil($totalItems / $perPage));
    $currentPage = $suggestionMode ? 1 : min($requestedPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    $orderParams = [];
    if ($sort === 'relevance' && $searchQuery !== '') {
        $orderBy = "CASE
            WHEN name LIKE ? ESCAPE '!' THEN 0
            WHEN sku LIKE ? ESCAPE '!' THEN 1
            WHEN category_name LIKE ? ESCAPE '!' THEN 2
            ELSE 3
        END, sold_count DESC, id DESC";
        $orderParams = [$searchPattern, $searchPattern, $searchPattern];
        $orderTypes = 'sss';
    } else {
        $orderBy = $sortOptions[$sort];
        $orderTypes = '';
    }
    $pageParams = array_merge($params, $orderParams, [$perPage, $offset]);
    $stmt = $conn->prepare(
        "SELECT * FROM view_product_details
         WHERE {$where}
         ORDER BY {$orderBy}
         LIMIT ? OFFSET ?"
    );
    if (!$stmt) {
        $sendListingError(500, 'Không thể tải sản phẩm.');
    }
    $stmt->bind_param($types . $orderTypes . 'ii', ...$pageParams);
    if (!$stmt->execute()) {
        $stmt->close();
        $sendListingError(500, 'Không thể tải sản phẩm.');
    }
    $result = $stmt->get_result();
    if (!$suggestionMode) {
        $paginationMeta = [
            'total' => $totalItems,
            'page' => $currentPage,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ];
    }
}
// 5. Mặc định: Lấy tất cả sản phẩm đang kinh doanh và chưa bị xóa
else {
    $result = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 AND status = 'active' AND category_is_deleted = 0 AND category_status = 'active' ORDER BY id DESC");
}

$formatProductRow = static function (array $row) use ($userWishlist): array {
    $row['average_rating'] = (float)($row['average_rating'] ?? 0);
    $row['review_count'] = (int)($row['review_count'] ?? 0);
    $row['stock'] = (int)($row['stock'] ?? 0);
    $row['sold_count'] = (int)($row['home_sold_count'] ?? $row['sold_count'] ?? 0);
    unset($row['home_sold_count']);
    $row['wishlist_count'] = (int)($row['wishlist_count'] ?? 0);
    $row['is_featured'] = (int)($row['is_featured'] ?? 0);
    $row['is_suggested'] = (int)($row['is_suggested'] ?? 0);
    $row['is_new'] = (int)($row['is_new'] ?? 0);
    $row['price_sale_raw'] = (float)$row['price_sale'];
    $row['price_original_raw'] = (float)($row['price_original'] ?? 0);
    $row['price_sale'] = number_format((float)$row['price_sale'], 0, ',', '.');
    $row['price_original'] = $row['price_original_raw'] > $row['price_sale_raw']
        ? number_format($row['price_original_raw'], 0, ',', '.')
        : null;
    $row['is_favorite'] = isset($userWishlist[(int)$row['id']]);
    return $row;
};

if ($homepageSections !== null) {
    foreach ($homepageSections as $sectionKey => $sectionRows) {
        $homepageSections[$sectionKey] = array_map($formatProductRow, $sectionRows);
    }
    $responseData = $category === 'home'
        ? $homepageSections
        : $homepageSections[$category === 'latest' ? 'new' : $category];
    echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$products = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $formatProductRow($row);
    }
}

$responseData = $suggestionMode
    ? ['products' => $products]
    : ($paginationMeta === null ? $products : ['products' => $products] + $paginationMeta);
echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>
