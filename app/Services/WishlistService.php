<?php

namespace App\Services;

use App\Core\View;
use App\Models\Product;
use App\Models\Wishlist;
use DomainException;
use InvalidArgumentException;

/**
 * Lớp WishlistService - nghiệp vụ "thả tim" sản phẩm yêu thích của khách hàng:
 * lấy danh sách, bật/tắt, xóa, đồng bộ từ localStorage và phân trang cho trang wishlist.
 * Chỉ sản phẩm đang kinh doanh thuộc danh mục đang hoạt động mới được đếm / hiển thị / thêm mới.
 */
class WishlistService
{
    /** Số sản phẩm mỗi trang ở client/wishlist.php (3 cột × 5 dòng). */
    public const ITEMS_PER_PAGE = 15;

    private Wishlist $wishlists;
    private Product $products;

    public function __construct(?Wishlist $wishlists = null, ?Product $products = null)
    {
        $this->wishlists = $wishlists ?? new Wishlist();
        $this->products = $products ?? new Product();
    }

    /** Danh sách ID sản phẩm yêu thích còn kinh doanh (mới nhất trước). */
    public function ids(int $userId): array
    {
        return $this->wishlists->activeProductIdsForUser($userId, true);
    }

    /** Số sản phẩm yêu thích còn kinh doanh. */
    public function count(int $userId): int
    {
        return $this->wishlists->countForUser($userId);
    }

    /** Chi tiết sản phẩm yêu thích (giá hiệu lực sau khuyến mãi), đã định dạng giá cho API. */
    public function items(int $userId): array
    {
        $items = [];
        foreach ($this->wishlists->itemsWithProductForUser($userId) as $row) {
            $row['id'] = (int) $row['id'];
            $row['stock'] = (int) $row['stock'];
            $row['sold_count'] = (int) $row['sold_count'];
            $row['price_sale_raw'] = (float) $row['price_sale'];
            $row['price_original_raw'] = (float) ($row['price_original'] ?? 0);
            $row['price_sale'] = View::money($row['price_sale']);
            $row['price_original'] = $row['price_original_raw'] > $row['price_sale_raw'] ? View::money($row['price_original_raw']) : null;
            $row['is_favorite'] = true;
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Đồng bộ danh sách ID từ localStorage khi khách vừa đăng nhập (bỏ qua sản phẩm không còn kinh doanh),
     * trả về danh sách ID còn hiển thị.
     */
    public function sync(int $userId, $syncIds): array
    {
        if (is_array($syncIds)) {
            foreach ($syncIds as $productId) {
                $productId = (int) $productId;
                if ($productId > 0) {
                    $this->wishlists->addActiveForUserIgnoreDuplicate($userId, $productId);
                }
            }
        }

        return $this->wishlists->activeProductIdsForUser($userId);
    }

    /**
     * Bật / tắt yêu thích một sản phẩm. Bỏ tim luôn được phép; thả tim mới chỉ khi sản phẩm còn kinh doanh.
     *
     * @return array{favorited: bool, count: int, message: string}
     * @throws InvalidArgumentException khi mã sản phẩm không hợp lệ / không tồn tại
     * @throws DomainException khi sản phẩm không còn được kinh doanh (HTTP 409)
     */
    public function toggle(int $userId, int $productId, ?array $product = null): array
    {
        $product = $product ?? $this->requireProduct($productId);
        $productName = (string) $product['name'];

        if ($this->wishlists->isFavorited($userId, $productId)) {
            // Đã tim rồi -> Hủy tim
            $this->wishlists->removeForUser($userId, $productId);
            $favorited = false;
            $message = "Đã bỏ yêu thích '{$productName}'";
        } else {
            if ((int) $product['is_deleted'] === 1 || $product['status'] !== 'active'
                || (int) $product['category_is_deleted'] === 1 || $product['category_status'] !== 'active') {
                throw new DomainException('Sản phẩm hiện không còn được kinh doanh.');
            }
            $this->wishlists->addForUser($userId, $productId);
            $favorited = true;
            $message = "❤️ Đã lưu '{$productName}' vào danh sách yêu thích!";
        }

        return ['favorited' => $favorited, 'count' => $this->count($userId), 'message' => $message];
    }

    /**
     * Xóa thẳng sản phẩm khỏi danh sách yêu thích.
     *
     * @return array{favorited: bool, count: int, message: string}
     */
    public function remove(int $userId, int $productId, ?array $product = null): array
    {
        $product = $product ?? $this->requireProduct($productId);
        $this->wishlists->removeForUser($userId, $productId);

        return ['favorited' => false, 'count' => $this->count($userId), 'message' => "Đã bỏ yêu thích '{$product['name']}'"];
    }

    /**
     * Kiểm tra sản phẩm (có danh mục) tồn tại; trả về id, name, status, is_deleted, category_status, category_is_deleted.
     *
     * @throws InvalidArgumentException
     */
    public function requireProduct(int $productId): array
    {
        if ($productId <= 0) {
            throw new InvalidArgumentException('Mã sản phẩm không hợp lệ!');
        }

        $product = $this->products->findWithCategoryStatus($productId);
        if (!$product) {
            throw new InvalidArgumentException('Sản phẩm không tồn tại!');
        }

        return $product;
    }

    /**
     * Kiểm tra sản phẩm tồn tại và trả về tên.
     *
     * @throws InvalidArgumentException
     */
    public function requireProductName(int $productId): string
    {
        return (string) $this->requireProduct($productId)['name'];
    }

    /**
     * Dữ liệu phân trang cho trang client/wishlist.php.
     *
     * @return array{wishlistItems: array, totalWishlistItems: int, totalPages: int}
     */
    public function page(int $userId, int $currentPage): array
    {
        $items = [];
        $total = 0;
        if ($userId > 0) {
            $total = $this->count($userId);
            $offset = ($currentPage - 1) * self::ITEMS_PER_PAGE;
            $items = $this->wishlists->pageWithProductStatsForUser($userId, self::ITEMS_PER_PAGE, $offset);
        }

        return [
            'wishlistItems' => $items,
            'totalWishlistItems' => $total,
            'totalPages' => $total > 0 ? (int) ceil($total / self::ITEMS_PER_PAGE) : 1,
        ];
    }
}
