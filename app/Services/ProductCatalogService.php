<?php

namespace App\Services;

use App\Core\View;
use App\Models\Category;
use App\Models\Product;
use App\Models\Wishlist;

/**
 * Lớp ProductCatalogService - nghiệp vụ hiển thị danh mục sản phẩm ở giao diện cửa hàng:
 * danh sách sản phẩm cho trang chủ / trang danh mục (kèm trạng thái yêu thích) và chi tiết sản phẩm.
 */
class ProductCatalogService
{
    /** Các slug danh mục nội bộ không hiển thị trên menu / trang chủ. */
    public const HIDDEN_CATEGORY_SLUGS = ['combo', 'suggest', 'featured'];

    private Product $products;
    private Category $categories;
    private Wishlist $wishlists;

    public function __construct(?Product $products = null, ?Category $categories = null, ?Wishlist $wishlists = null)
    {
        $this->products = $products ?? new Product();
        $this->categories = $categories ?? new Category();
        $this->wishlists = $wishlists ?? new Wishlist();
    }

    /**
     * Xử lý yêu cầu API get_products (tham số $_GET) giống bản thủ tục:
     *  - ids=1,2,3        : kiểm tra sản phẩm còn hiển thị (tối đa 100 ID)
     *  - category=home|featured|suggest|new|latest : các khối trang chủ
     *  - category/search  : trang danh mục / tìm kiếm có lọc, sắp xếp, phân trang (suggest=1: gợi ý 8 sản phẩm)
     *  - rỗng             : tất cả sản phẩm đang kinh doanh
     *
     * @return array{status: int, body: mixed} mã HTTP và dữ liệu JSON trả về
     */
    public function handleProductsRequest(array $query, ?int $customerId): array
    {
        $categoryInput = $query['category'] ?? '';
        $sortInput = $query['sort'] ?? '';
        $searchInput = $query['search'] ?? '';
        $suggestInput = $query['suggest'] ?? '';
        if (!is_string($categoryInput) || !is_string($sortInput) || !is_string($searchInput) || !is_string($suggestInput)) {
            return self::error(400, 'Tham số tìm kiếm không hợp lệ.');
        }
        $category = trim($categoryInput);
        $sort = trim($sortInput);
        $searchQuery = preg_replace('/\s+/u', ' ', trim($searchInput));
        $searchRequested = array_key_exists('search', $query);
        if ($searchQuery === null || ($searchRequested && ($searchQuery === '' || strlen($searchQuery) > 300))) {
            return self::error(400, 'Từ khóa tìm kiếm không hợp lệ.');
        }
        $suggestionMode = $suggestInput === '1';
        if (($suggestInput !== '' && !in_array($suggestInput, ['0', '1'], true))
            || ($suggestionMode && !$searchRequested)) {
            return self::error(400, 'Yêu cầu gợi ý tìm kiếm không hợp lệ.');
        }

        $favoriteIds = [];
        if ($customerId !== null && $customerId > 0) {
            foreach ($this->wishlists->productIdsForUser($customerId) as $productId) {
                $favoriteIds[$productId] = true;
            }
        }
        $format = fn(array $row): array => $this->formatListRow($row, $favoriteIds);

        if (array_key_exists('ids', $query)) {
            $rawIds = $query['ids'];
            if (!is_string($rawIds)) {
                return self::error(400, 'Danh sách mã sản phẩm không hợp lệ.');
            }
            $productIds = [];
            if (trim($rawIds) !== '') {
                foreach (explode(',', $rawIds) as $rawId) {
                    $productId = filter_var($rawId, FILTER_VALIDATE_INT);
                    if ($productId === false || $productId < 1) {
                        return self::error(400, 'Danh sách mã sản phẩm không hợp lệ.');
                    }
                    $productIds[$productId] = $productId;
                }
            }
            $productIds = array_values($productIds);
            if (count($productIds) > 100) {
                return self::error(400, 'Chỉ được kiểm tra tối đa 100 sản phẩm mỗi lần.');
            }

            return ['status' => 200, 'body' => array_map($format, $this->products->findVisibleByIds($productIds))];
        }

        if (in_array($category, ['home', 'featured', 'suggest', 'new', 'latest'], true)) {
            $sections = array_map(
                static fn(array $rows): array => array_map($format, $rows),
                $this->homepageSections($customerId)
            );

            return [
                'status' => 200,
                'body' => $category === 'home' ? $sections : $sections[$category === 'latest' ? 'new' : $category],
            ];
        }

        if ($category === '' && $searchQuery === '') {
            return ['status' => 200, 'body' => array_map($format, $this->products->findAllVisible())];
        }

        // Trang danh mục và tìm kiếm: lọc, đếm và phân trang trực tiếp trong MySQL.
        if ($sort === '') {
            $sort = $searchQuery !== '' ? 'relevance' : 'best_selling';
        }
        if (!isset(Product::STOREFRONT_SORTS[$sort]) && !($searchQuery !== '' && $sort === 'relevance')) {
            $sort = $searchQuery !== '' ? 'relevance' : 'best_selling';
        }

        $minPrice = $this->optionalPrice($query, 'min_price');
        if (is_array($minPrice)) {
            return $minPrice;
        }
        $maxPrice = $this->optionalPrice($query, 'max_price');
        if (is_array($maxPrice)) {
            return $maxPrice;
        }
        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            return self::error(400, 'Giá thấp nhất không được lớn hơn giá cao nhất.');
        }
        foreach (['in_stock', 'has_discount', 'has_gift'] as $filterKey) {
            if (isset($query[$filterKey])
                && (!is_string($query[$filterKey]) || !in_array($query[$filterKey], ['0', '1'], true))) {
                return self::error(400, 'Giá trị bộ lọc không hợp lệ.');
            }
        }

        $requestedPage = 1;
        if (!$suggestionMode) {
            $requestedPage = filter_var($query['page'] ?? '1', FILTER_VALIDATE_INT);
            if ($requestedPage === false || $requestedPage < 1) {
                return self::error(400, 'Số trang không hợp lệ.');
            }
        }

        $criteria = [
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'in_stock' => ($query['in_stock'] ?? '') === '1',
            'has_discount' => ($query['has_discount'] ?? '') === '1',
            'has_gift' => ($query['has_gift'] ?? '') === '1',
        ];
        if ($category !== '') {
            // Danh mục không tồn tại / ngừng hoạt động => category_id = 0 (không có sản phẩm nào).
            $categoryRow = $this->categories->findBySlugNameOrId($category);
            $criteria['category_id'] = (int) ($categoryRow['id'] ?? 0);
        }
        if ($searchQuery !== '') {
            $criteria['search_pattern'] = '%' . strtr($searchQuery, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }

        $totalItems = $suggestionMode ? 0 : $this->products->countStorefrontListing($criteria);
        $perPage = $suggestionMode ? 8 : 12;
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $currentPage = $suggestionMode ? 1 : min($requestedPage, $totalPages);
        $rows = $this->products->findStorefrontListingPage($criteria, $sort, $perPage, ($currentPage - 1) * $perPage);
        $products = array_map($format, $rows);

        if ($suggestionMode) {
            return ['status' => 200, 'body' => ['products' => $products]];
        }

        return ['status' => 200, 'body' => ['products' => $products] + [
            'total' => $totalItems,
            'page' => $currentPage,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ]];
    }

    /** Ba khối trang chủ: mới nhất, bán chạy 30 ngày, gợi ý (ưu tiên danh mục khách hay mua). */
    private function homepageSections(?int $customerId): array
    {
        $rowsToIds = static fn(array $rows): array => array_map(static fn(array $row): int => (int) $row['id'], $rows);

        $newestRows = $this->products->findHomeNewest();
        $newestIds = $rowsToIds($newestRows);
        $bestSellingRows = $this->products->findHomeBestSelling($newestIds);

        $preferredCategoryId = ($customerId !== null && $customerId > 0)
            ? $this->products->findPreferredCategoryIdForCustomer($customerId)
            : null;

        $excludedForSuggestions = array_merge($newestIds, $rowsToIds($bestSellingRows));
        $suggestionRows = $this->products->findHomeRecommendations($preferredCategoryId, $excludedForSuggestions);
        if (count($suggestionRows) < 10) {
            $suggestionRows = array_merge(
                $suggestionRows,
                $this->products->findHomeRecommendations(null, array_merge($excludedForSuggestions, $rowsToIds($suggestionRows)))
            );
            $suggestionRows = array_slice($suggestionRows, 0, 10);
        }

        return ['new' => $newestRows, 'featured' => $bestSellingRows, 'suggest' => $suggestionRows];
    }

    /** Đọc giá lọc tùy chọn; trả về int, null (không lọc) hoặc mảng lỗi [status, body]. */
    private function optionalPrice(array $query, string $key)
    {
        if (!array_key_exists($key, $query) || $query[$key] === '') {
            return null;
        }
        if (!is_string($query[$key])) {
            return self::error(400, 'Khoảng giá không hợp lệ.');
        }
        $value = filter_var($query[$key], FILTER_VALIDATE_INT);
        if ($value === false || $value < 0) {
            return self::error(400, 'Giá phải là số nguyên không âm.');
        }

        return $value;
    }

    private static function error(int $status, string $message): array
    {
        return ['status' => $status, 'body' => ['status' => 'error', 'message' => $message]];
    }

    /** Chuẩn hoá kiểu dữ liệu và định dạng giá cho một dòng sản phẩm. */
    private function formatListRow(array $row, array $favoriteIds): array
    {
        $row['average_rating'] = (float) ($row['average_rating'] ?? 0);
        $row['review_count'] = (int) ($row['review_count'] ?? 0);
        $row['stock'] = (int) ($row['stock'] ?? 0);
        $row['sold_count'] = (int) ($row['home_sold_count'] ?? $row['sold_count'] ?? 0);
        unset($row['home_sold_count']);
        $row['wishlist_count'] = (int) ($row['wishlist_count'] ?? 0);
        $row['is_featured'] = (int) ($row['is_featured'] ?? 0);
        $row['is_suggested'] = (int) ($row['is_suggested'] ?? 0);
        $row['is_new'] = (int) ($row['is_new'] ?? 0);
        $row['price_sale_raw'] = (float) $row['price_sale'];
        $row['price_original_raw'] = (float) ($row['price_original'] ?? 0);

        // Định dạng giá hiển thị tiền tệ; chỉ hiện giá gốc khi đang giảm giá.
        $row['price_sale'] = View::money($row['price_sale']);
        $row['price_original'] = $row['price_original_raw'] > $row['price_sale_raw'] ? View::money($row['price_original_raw']) : null;
        $row['is_favorite'] = isset($favoriteIds[(int) $row['id']]);

        return $row;
    }

    /**
     * Chi tiết sản phẩm hiển thị cho khách: chỉ sản phẩm đang kinh doanh, chưa xóa mềm,
     * thuộc danh mục đang hoạt động; null nếu không thỏa.
     */
    public function findVisibleProduct(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        return $this->products->findVisibleDetail($productId);
    }

    /** Slug có thuộc danh sách danh mục đang hoạt động hay không. */
    public function isActiveCategorySlug(string $slug, array $navCategories): bool
    {
        return in_array($slug, array_column($navCategories, 'slug'), true);
    }

    /** Danh mục đang hoạt động (id, name, slug, image) cho menu điều hướng. */
    public function navigationCategories(): array
    {
        return $this->categories->findAllForNavigation();
    }

    /** Danh mục hiển thị cho khách (bỏ các slug nội bộ). */
    public function visibleCategories(array $categories): array
    {
        return array_values(array_filter(
            $categories,
            static fn(array $cat): bool => !in_array($cat['slug'], self::HIDDEN_CATEGORY_SLUGS, true)
        ));
    }

    /** Tìm danh mục theo slug / tên / ID. */
    public function findCategory(string $key): ?array
    {
        return $key === '' ? null : $this->categories->findBySlugNameOrId($key);
    }
}
