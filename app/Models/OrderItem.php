<?php

namespace App\Models;

/**
 * Model OrderItem - chi tiết sản phẩm trong đơn hàng (bảng `order_items`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class OrderItem extends BaseModel
{
    protected string $table = 'order_items';

    // ───────────── Đơn hàng phía khách hàng (agentB) ─────────────

    /** Toàn bộ dòng sản phẩm của một đơn. */
    public function listByOrder(int $orderId): array
    {
        return $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ?', [$orderId], 'i');
    }

    /** Dòng sản phẩm của đơn kèm cờ is_reviewed (khách đã đánh giá sản phẩm chưa). */
    public function listByOrderWithReviewFlag(int $orderId, int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT oi.*, EXISTS (
                SELECT 1 FROM reviews r
                WHERE r.product_id = oi.product_id AND r.user_id = ?
            ) AS is_reviewed
             FROM order_items oi
             WHERE oi.order_id = ?',
            [$userId, $orderId],
            'ii'
        );
    }

    /** product_id + quantity của đơn (dùng để hoàn kho). */
    public function listStockLinesByOrder(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT product_id, quantity FROM order_items WHERE order_id = ?',
            [$orderId],
            'i'
        );
    }

    /** Thêm một dòng sản phẩm vào đơn. */
    public function createLine(int $orderId, int $productId, string $productName, int $quantity, float $price, ?string $image): int
    {
        return $this->db->insert(
            'INSERT INTO order_items (order_id, product_id, product_name, quantity, price, image)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$orderId, $productId, $productName, $quantity, $price, $image],
            'iisids'
        );
    }

    // --- end of methods ---
}
