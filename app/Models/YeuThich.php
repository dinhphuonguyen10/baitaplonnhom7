<?php

namespace App\Models;

/**
 * Model YeuThich - sản phẩm yêu thích của khách (bảng `wishlists`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class YeuThich extends BaseModel
{
    protected string $table = 'wishlists';

    /**
     * Danh sách ID sản phẩm yêu thích của khách.
     * $newestFirst = true sắp xếp mục thêm gần nhất lên đầu.
     */
    public function productIdsForUser(int $userId, bool $newestFirst = false): array
    {
        $sql = 'SELECT product_id FROM wishlists WHERE user_id = ?' . ($newestFirst ? ' ORDER BY id DESC' : '');

        return array_map('intval', $this->db->fetchColumn($sql, [$userId], 'i'));
    }

    /** Đếm số sản phẩm yêu thích của khách (chỉ sản phẩm & danh mục còn đang kinh doanh). */
    public function countForUser(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) AS total
             FROM wishlists w
             INNER JOIN products p ON p.id = w.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE w.user_id = ?
               AND p.is_deleted = 0 AND p.status = 'active'
               AND c.is_deleted = 0 AND c.status = 'active'",
            [$userId],
            'i'
        );
    }

    /** Kiểm tra sản phẩm đã nằm trong danh sách yêu thích của khách chưa. */
    public function isFavorited(int $userId, int $productId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1',
            [$userId, $productId],
            'ii'
        ) !== null;
    }

    /** Thêm sản phẩm vào danh sách yêu thích. */
    public function addForUser(int $userId, int $productId): int
    {
        return $this->db->execute('INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)', [$userId, $productId], 'ii');
    }

    /** Thêm sản phẩm vào danh sách yêu thích, bỏ qua nếu đã tồn tại. */
    public function addForUserIgnoreDuplicate(int $userId, int $productId): int
    {
        return $this->db->execute('INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)', [$userId, $productId], 'ii');
    }

    /** Xóa sản phẩm khỏi danh sách yêu thích của khách. */
    public function removeForUser(int $userId, int $productId): int
    {
        return $this->db->execute('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?', [$userId, $productId], 'ii');
    }

    /** Chi tiết sản phẩm yêu thích của khách (dùng cho API wishlist?type=items). */
    public function itemsWithProductForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT w.id AS wishlist_id, w.created_at AS favorited_at,
                   p.id, p.name, p.category_id,
                   CASE
                       WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                       THEN p.price_original
                       ELSE p.price_sale
                   END AS price_sale,
                   p.price_original,
                   CASE
                       WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW() THEN NULL
                       WHEN p.price_sale < p.price_original
                       THEN CONCAT('-', ROUND((p.price_original - p.price_sale) / p.price_original * 100), '%')
                       ELSE NULL
                   END AS discount,
                   p.stock, p.image, p.description,
                   c.name AS category_name, c.slug AS category,
                   COALESCE(SUM(oi.quantity), 0) AS sold_count
            FROM wishlists w
            JOIN products p ON w.product_id = p.id
            JOIN categories c ON p.category_id = c.id AND c.status = 'active' AND c.is_deleted = 0
            LEFT JOIN order_items oi ON p.id = oi.product_id
            WHERE w.user_id = ? AND p.is_deleted = 0 AND p.status = 'active'
            GROUP BY p.id
            ORDER BY w.id DESC",
            [$userId],
            'i'
        );
    }

    /** Một trang sản phẩm yêu thích (còn kinh doanh) kèm lượt bán, giá hiệu lực và điểm đánh giá (trang client/yeu_thich.php). */
    public function pageWithProductStatsForUser(int $userId, int $limit, int $offset): array
    {
        return $this->db->fetchAll(
            "SELECT w.id AS wishlist_id,
               p.id, p.name, p.category_id,
               CASE
                   WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                   THEN p.price_original
                   ELSE p.price_sale
               END AS price_sale,
               p.price_original,
               CASE
                   WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW() THEN NULL
                   WHEN p.price_sale < p.price_original
                   THEN CONCAT('-', ROUND((p.price_original - p.price_sale) / p.price_original * 100), '%')
                   ELSE NULL
               END AS discount,
               p.stock, p.image, p.gift, p.description,
               c.name AS category_name, c.slug AS category,
               COALESCE(SUM(oi.quantity), 0) AS sold_count,
               COALESCE(review_stats.average_rating, 0) AS average_rating,
               COALESCE(review_stats.review_count, 0) AS review_count
            FROM wishlists w
            JOIN products p ON w.product_id = p.id
            JOIN categories c ON p.category_id = c.id AND c.status = 'active' AND c.is_deleted = 0
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
                        AND o.status IN ('Đã giao hàng', 'Đã giao', 'Hoàn thành')
                  )
                GROUP BY r.product_id
            ) review_stats ON review_stats.product_id = p.id
            WHERE w.user_id = ? AND p.is_deleted = 0 AND p.status = 'active'
            GROUP BY p.id
            ORDER BY w.id DESC
            LIMIT ? OFFSET ?",
            [$userId, $limit, $offset],
            'iii'
        );
    }

    // ───────────── Chỉ sản phẩm còn kinh doanh (portCatalog) ─────────────

    /** ID sản phẩm yêu thích của khách mà sản phẩm & danh mục còn đang kinh doanh. */
    public function activeProductIdsForUser(int $userId, bool $newestFirst = false): array
    {
        $sql = "SELECT w.product_id
                FROM wishlists w
                INNER JOIN products p ON p.id = w.product_id
                INNER JOIN categories c ON c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0
                WHERE w.user_id = ? AND p.is_deleted = 0 AND p.status = 'active'"
            . ($newestFirst ? ' ORDER BY w.id DESC' : '');

        return array_map('intval', $this->db->fetchColumn($sql, [$userId], 'i'));
    }

    /** Thêm yêu thích (bỏ qua nếu trùng) chỉ khi sản phẩm & danh mục còn đang kinh doanh. */
    public function addActiveForUserIgnoreDuplicate(int $userId, int $productId): int
    {
        return $this->db->execute(
            "INSERT IGNORE INTO wishlists (user_id, product_id)
             SELECT ?, p.id
             FROM products p
             WHERE p.id = ? AND p.is_deleted = 0 AND p.status = 'active'
               AND EXISTS (
                   SELECT 1 FROM categories c
                   WHERE c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0
               )",
            [$userId, $productId],
            'ii'
        );
    }

    // --- end of methods ---
}
