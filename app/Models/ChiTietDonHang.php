<?php

namespace App\Models;

/**
 * Model ChiTietDonHang - chi tiết sản phẩm trong đơn hàng (bảng `order_items`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class ChiTietDonHang extends BaseModel
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

    // ───────────── Snapshot giá + chi tiết đơn (portOrders) ─────────────

    /** Thêm dòng sản phẩm kèm snapshot SKU/danh mục/giá gốc/giảm giá/thành tiền (tiền dạng chuỗi decimal). */
    public function createSnapshotLine(
        int $orderId,
        int $productId,
        string $sku,
        string $attributesSnapshot,
        string $categoryNameSnapshot,
        string $productName,
        int $quantity,
        string $price,
        string $originalPrice,
        string $discountAmount,
        string $lineTotal,
        ?string $image
    ): int {
        return $this->db->execute(
            'INSERT INTO order_items
                (order_id, product_id, product_sku, attributes_snapshot, category_name_snapshot,
                 product_name, quantity, price, original_price, discount_amount, line_total, image)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $orderId, $productId, $sku, $attributesSnapshot, $categoryNameSnapshot,
                $productName, $quantity, $price, $originalPrice, $discountAmount, $lineTotal, $image,
            ],
            'iissssisssss'
        );
    }

    /** Toàn bộ dòng sản phẩm của một đơn theo thứ tự thêm. */
    public function listByOrderOrdered(int $orderId): array
    {
        return $this->db->fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$orderId], 'i');
    }

    /** Dòng sản phẩm của một đơn kèm cờ is_reviewed, theo thứ tự thêm. */
    public function listByOrderWithReviewFlagOrdered(int $orderId, int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT oi.*,
                    EXISTS (
                        SELECT 1 FROM reviews r
                        WHERE r.product_id = oi.product_id AND r.user_id = ?
                    ) AS is_reviewed
             FROM order_items oi
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC',
            [$userId, $orderId],
            'ii'
        );
    }

    /** Dòng sản phẩm của nhiều đơn kèm cờ is_reviewed (theo order_id, id). */
    public function listByOrdersWithReviewFlag(array $orderIds, int $userId): array
    {
        if ($orderIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

        return $this->db->fetchAll(
            "SELECT oi.*,
                    EXISTS (
                        SELECT 1 FROM reviews r
                        WHERE r.product_id = oi.product_id AND r.user_id = ?
                    ) AS is_reviewed
             FROM order_items oi
             WHERE oi.order_id IN ({$placeholders})
             ORDER BY oi.order_id ASC, oi.id ASC",
            array_merge([$userId], array_map('intval', $orderIds)),
            'i' . str_repeat('i', count($orderIds))
        );
    }

    /** Tên, số lượng, đơn giá các dòng của đơn - cho biên nhận ở trang thanh toán. */
    public function listReceiptLines(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT product_name, quantity, price
             FROM order_items
             WHERE order_id = ?
             ORDER BY id',
            [$orderId],
            'i'
        );
    }

    // --- end of methods ---
}
