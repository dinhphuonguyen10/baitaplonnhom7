<?php

namespace App\Models;

/**
 * Model OrderStatusHistory - lịch sử chuyển trạng thái đơn hàng (bảng `order_status_history`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class OrderStatusHistory extends BaseModel
{
    protected string $table = 'order_status_history';

    /**
     * Ghi một sự kiện trạng thái: cột `status` và `new_status` cùng nhận trạng thái mới.
     * Trả về số dòng được thêm.
     */
    public function log(int $orderId, string $newStatus, ?string $oldStatus, string $note, string $actorName): int
    {
        return $this->db->execute(
            'INSERT INTO order_status_history
                (order_id, status, old_status, new_status, note, actor_name)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$orderId, $newStatus, $oldStatus, $newStatus, $note, $actorName],
            'isssss'
        );
    }

    /** Lịch sử của một đơn (cũ nhất trước) cho trang chi tiết đơn của khách. */
    public function listForOrder(int $orderId): array
    {
        return $this->db->fetchAll(
            'SELECT old_status, new_status, status, note, actor_name, created_at
             FROM order_status_history
             WHERE order_id = ?
             ORDER BY created_at ASC, id ASC',
            [$orderId],
            'i'
        );
    }

    // --- end of methods ---
}
