<?php

namespace App\Services;

use App\Core\View;
use App\Models\Product;
use App\Models\Wishlist;
use InvalidArgumentException;

/**
 * Lớp WishlistService - nghiệp vụ "thả tim" sản phẩm yêu thích của khách hàng:
 * lấy danh sách, bật/tắt, xóa, đồng bộ từ localStorage và phân trang cho trang wishlist.
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

    /** Danh sách ID sản phẩm yêu thích (mới nhất trước). */
    public function ids(int $userId): array
    {
        return $this->wishlists->productIdsForUser($userId, true);
    }

    public function count(int $userId): int
    {
        return $this->wishlists->countForUser($userId);
    }

    /** Chi tiết sản phẩm yêu thích, đã định dạng giá cho API. */
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
            $row['price_original'] = $row['price_original_raw'] > 0 ? View::money($row['price_original_raw']) : null;
            $row['is_favorite'] = true;
            $items[] = $row;
        }

        return $items;
    }

    /** Đồng bộ danh sách ID từ localStorage khi khách vừa đăng nhập, trả về danh sách ID mới nhất. */
    public function sync(int $userId, $syncIds): array
    {
        if (is_array($syncIds)) {
            foreach ($syncIds as $productId) {
                $productId = (int) $productId;
                if ($productId > 0) {
                    $this->wishlists->addForUserIgnoreDuplicate($userId, $productId);
                }
            }
        }

        return $this->wishlists->productIdsForUser($userId);
    }

    /**
     * Bật / tắt yêu thích một sản phẩm.
     *
     * @return array{favorited: bool, count: int, message: string}
     * @throws InvalidArgumentException khi mã sản phẩm không hợp lệ / không tồn tại
     */
    public function toggle(int $userId, int $productId, ?string $productName = null): array
    {
        $productName = $productName ?? $this->requireProductName($productId);

        if ($this->wishlists->isFavorited($userId, $productId)) {
            // Đã tim rồi -> Hủy tim
            $this->wishlists->removeForUser($userId, $productId);
            $favorited = false;
            $message = "Đã bỏ yêu thích '{$productName}'";
        } else {
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
    public function remove(int $userId, int $productId, ?string $productName = null): array
    {
        $productName = $productName ?? $this->requireProductName($productId);
        $this->wishlists->removeForUser($userId, $productId);

        return ['favorited' => false, 'count' => $this->count($userId), 'message' => "Đã bỏ yêu thích '{$productName}'"];
    }

    /**
     * Kiểm tra sản phẩm tồn tại và trả về tên.
     *
     * @throws InvalidArgumentException
     */
    public function requireProductName(int $productId): string
    {
        if ($productId <= 0) {
            throw new InvalidArgumentException('Mã sản phẩm không hợp lệ!');
        }

        $product = $this->products->findIdAndName($productId);
        if (!$product) {
            throw new InvalidArgumentException('Sản phẩm không tồn tại!');
        }

        return (string) $product['name'];
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
