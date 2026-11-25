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
     * Danh sách sản phẩm cho API get_products.
     *  - featured / suggest / new|latest: top 10 cho các khối trang chủ
     *  - slug/tên/ID danh mục: lọc + sắp xếp theo tiêu chí từ JS
     *  - rỗng: tất cả sản phẩm đang kinh doanh
     */
    public function listProducts(string $category, string $sort, array $filters, ?int $customerId): array
    {
        $favoriteIds = [];
        if ($customerId !== null && $customerId > 0) {
            foreach ($this->wishlists->productIdsForUser($customerId) as $productId) {
                $favoriteIds[$productId] = true;
            }
        }

        $isSection = in_array($category, ['featured', 'suggest', 'new', 'latest'], true);
        $rows = ($category === '' || $isSection)
            ? $this->products->findStorefrontSection($category)
            : $this->products->findStorefrontByCategory($category, $sort, $filters);

        return array_map(fn(array $row): array => $this->formatListRow($row, $favoriteIds), $rows);
    }

    /** Chuẩn hoá kiểu dữ liệu và định dạng giá cho một dòng sản phẩm. */
    private function formatListRow(array $row, array $favoriteIds): array
    {
        $row['average_rating'] = (float) ($row['average_rating'] ?? 0);
        $row['review_count'] = (int) ($row['review_count'] ?? 0);
        $row['stock'] = (int) ($row['stock'] ?? 0);
        $row['sold_count'] = (int) ($row['sold_count'] ?? 0);
        $row['wishlist_count'] = (int) ($row['wishlist_count'] ?? 0);
        $row['is_featured'] = (int) ($row['is_featured'] ?? 0);
        $row['is_suggested'] = (int) ($row['is_suggested'] ?? 0);
        $row['is_new'] = (int) ($row['is_new'] ?? 0);
        $row['price_sale_raw'] = (float) $row['price_sale'];
        $row['price_original_raw'] = (float) ($row['price_original'] ?? 0);

        // Định dạng giá hiển thị tiền tệ
        $row['price_sale'] = View::money($row['price_sale']);
        $row['price_original'] = $row['price_original_raw'] > 0 ? View::money($row['price_original_raw']) : null;
        $row['is_favorite'] = isset($favoriteIds[(int) $row['id']]);

        return $row;
    }

    /**
     * Chi tiết sản phẩm hiển thị cho khách; null nếu không tồn tại, đã xóa mềm hoặc bị ẩn.
     */
    public function findVisibleProduct(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $product = $this->products->findDetailFromView($productId);
        // Loại bỏ sản phẩm đã xóa mềm hoặc bị ẩn khỏi Client
        if ($product && ((int) ($product['is_deleted'] ?? 0) === 1 || ($product['status'] ?? 'active') === 'hidden')) {
            return null;
        }

        return $product;
    }

    /** Tất cả danh mục (id, name, slug) cho menu điều hướng. */
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
