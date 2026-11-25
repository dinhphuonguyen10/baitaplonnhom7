<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Lớp OrderInventoryService - quản lý tồn kho được giữ (reserved) cho đơn hàng:
 * hoàn kho khi đơn hủy/hết hạn và tự hủy các đơn hết thời gian giữ hàng.
 */
class OrderInventoryService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Hoàn tồn kho đang giữ của một đơn (gọi bên trong transaction).
     * Trả về false nếu đơn không ở trạng thái 'reserved'.
     */
    public function releaseReserved(int $orderId): bool
    {
        $order = $this->db->fetchOne('SELECT inventory_status FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$order) {
            throw new RuntimeException('Không tìm thấy đơn hàng cần hoàn tồn kho.');
        }
        if ($order['inventory_status'] !== 'reserved') {
            return false;
        }

        $rows = $this->db->fetchAll(
            'SELECT product_id, SUM(quantity) AS quantity
             FROM order_items
             WHERE order_id = ?
             GROUP BY product_id
             ORDER BY product_id',
            [$orderId]
        );
        $items = [];
        foreach ($rows as $item) {
            if ($item['product_id'] === null || (int) $item['quantity'] < 1) {
                throw new RuntimeException('Chi tiết đơn hàng không đủ dữ liệu để hoàn tồn kho.');
            }
            $items[] = ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity']];
        }
        if ($items === []) {
            throw new RuntimeException('Đơn hàng không có chi tiết để hoàn tồn kho.');
        }

        $released = $this->db->execute(
            "UPDATE orders
             SET inventory_status = 'released', inventory_released_at = NOW()
             WHERE id = ? AND inventory_status = 'reserved'",
            [$orderId]
        );
        if ($released !== 1) {
            throw new RuntimeException('Tồn kho của đơn hàng đã được xử lý ở thao tác khác.');
        }

        foreach ($items as $item) {
            $restocked = $this->db->execute(
                'UPDATE products SET stock = stock + ? WHERE id = ?',
                [$item['quantity'], $item['product_id']],
                'ii'
            );
            if ($restocked !== 1) {
                throw new RuntimeException('Không thể hoàn tồn kho cho sản phẩm trong đơn hàng.');
            }
        }

        return true;
    }

    /**
     * Hủy các đơn hết thời gian giữ tồn kho (tối đa $limit đơn), hoàn kho và ghi lịch sử.
     * Trả về số đơn đã hủy.
     */
    public function expireReservations(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $this->db->beginTransaction();

        try {
            $orderIds = array_map('intval', $this->db->fetchColumn(
                "SELECT id
                 FROM orders
                 WHERE inventory_status = 'reserved'
                   AND status = 'Chờ xác nhận'
                   AND payment_status IN ('Chưa thanh toán', 'Thanh toán thất bại')
                   AND COALESCE(payment_deadline_at, expires_at) <= NOW()
                 ORDER BY expires_at, id
                 LIMIT ?
                 FOR UPDATE",
                [$limit],
                'i'
            ));

            $expiredCount = 0;
            foreach ($orderIds as $orderId) {
                if (!$this->releaseReserved($orderId)) {
                    throw new RuntimeException('Tồn kho của đơn hàng hết hạn đã được xử lý ở thao tác khác.');
                }

                $expired = $this->db->execute(
                    "UPDATE orders
                     SET status = 'Đã hủy',
                         cancelled_at = COALESCE(cancelled_at, NOW()),
                         cancellation_reason = COALESCE(NULLIF(cancellation_reason, ''), 'Đơn hàng hết hạn giữ tồn kho.')
                     WHERE id = ?
                       AND inventory_status = 'released'
                       AND status = 'Chờ xác nhận'
                       AND payment_status IN ('Chưa thanh toán', 'Thanh toán thất bại')
                       AND COALESCE(payment_deadline_at, expires_at) <= NOW()",
                    [$orderId]
                );
                if ($expired !== 1) {
                    throw new RuntimeException('Không thể chuyển đơn hàng hết hạn sang trạng thái đã hủy.');
                }

                $logged = $this->db->execute(
                    "INSERT INTO order_status_history
                        (order_id, status, old_status, new_status, note, actor_name)
                     VALUES (?, 'Đã hủy', ?, 'Đã hủy', ?, 'Hệ thống')",
                    [$orderId, 'Chờ xác nhận', 'Đơn hàng hết hạn giữ tồn kho; hệ thống đã hủy đơn và hoàn kho.'],
                    'iss'
                );
                if ($logged !== 1) {
                    throw new RuntimeException('Không thể ghi lịch sử hủy đơn hàng hết hạn.');
                }
                $expiredCount++;
            }

            $this->db->commit();

            return $expiredCount;
        } catch (Throwable $e) {
            $this->db->rollback();
            error_log('Order inventory expiration failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
