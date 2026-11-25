<?php

namespace App\Services;

use App\Core\Database;
use App\Database\SchemaManager;
use RuntimeException;
use Throwable;

/**
 * Lớp OrderExpiryService - tự hủy đơn thanh toán VietQR quá hạn (30 phút) và hoàn kho sản phẩm.
 */
class OrderExpiryService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** Đảm bảo cột payment_deadline_at và chỉ mục liên quan tồn tại. */
    public function ensureSchema(): void
    {
        (new SchemaManager($this->db))->ensureOrderPaymentDeadline();
    }

    /**
     * Hủy một đơn QR nếu đã quá hạn thanh toán. Trả về true nếu đơn vừa bị hủy.
     */
    public function expireOrder(int $orderId): bool
    {
        if ($orderId < 1) {
            return false;
        }

        $this->ensureSchema();

        // Đơn cũ chưa có hạn thanh toán: bắt đầu đếm 30 phút từ bây giờ.
        $this->db->execute(
            "UPDATE orders
             SET payment_deadline_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
             WHERE id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status = 'Thanh toán thất bại'
               AND payment_deadline_at IS NULL
               AND status IN ('Chờ xác nhận', 'Pending')",
            [$orderId]
        );

        $transactionStarted = false;
        try {
            $this->db->beginTransaction();
            $transactionStarted = true;

            $order = $this->db->fetchOne(
                'SELECT status, payment_status, payment_method, payment_deadline_at,
                        payment_deadline_at <= NOW() AS deadline_passed
                 FROM orders
                 WHERE id = ?
                 FOR UPDATE',
                [$orderId]
            );

            if (
                !$order
                || $order['payment_method'] !== 'BANK_QR'
                || $order['payment_status'] !== 'Thanh toán thất bại'
                || !in_array($order['status'], ['Chờ xác nhận', 'Pending'], true)
                || (int) $order['deadline_passed'] !== 1
            ) {
                $this->db->commit();
                $transactionStarted = false;

                return false;
            }

            $affected = $this->db->execute(
                "UPDATE orders
                 SET status = 'Đã hủy'
                 WHERE id = ?
                   AND payment_method = 'BANK_QR'
                   AND payment_status = 'Thanh toán thất bại'
                   AND payment_deadline_at <= NOW()
                   AND status IN ('Chờ xác nhận', 'Pending')",
                [$orderId]
            );
            if ($affected !== 1) {
                throw new RuntimeException('Trạng thái đơn hàng đã thay đổi khi hết hạn.');
            }

            $items = $this->db->fetchAll(
                'SELECT product_id, quantity FROM order_items WHERE order_id = ? FOR UPDATE',
                [$orderId]
            );
            foreach ($items as $item) {
                if ($item['product_id'] === null) {
                    continue;
                }
                $restocked = $this->db->execute(
                    'UPDATE products SET stock = stock + ? WHERE id = ?',
                    [(int) $item['quantity'], (int) $item['product_id']]
                );
                if ($restocked !== 1) {
                    throw new RuntimeException('Không thể hoàn kho sản phẩm của đơn đã hết hạn.');
                }
            }

            $this->db->commit();
            $transactionStarted = false;

            return true;
        } catch (Throwable $e) {
            if ($transactionStarted) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Hủy toàn bộ đơn QR quá hạn. Trả về số đơn đã hủy.
     */
    public function expireOverdueOrders(): int
    {
        $this->ensureSchema();
        $this->db->execute(
            "UPDATE orders
             SET payment_deadline_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
             WHERE payment_method = 'BANK_QR'
               AND payment_status = 'Thanh toán thất bại'
               AND payment_deadline_at IS NULL
               AND status IN ('Chờ xác nhận', 'Pending')"
        );

        $orderIds = array_map('intval', $this->db->fetchColumn(
            "SELECT id
             FROM orders
             WHERE payment_method = 'BANK_QR'
               AND payment_status = 'Thanh toán thất bại'
               AND payment_deadline_at <= NOW()
               AND status IN ('Chờ xác nhận', 'Pending')
             ORDER BY id"
        ));

        $expiredCount = 0;
        foreach ($orderIds as $orderId) {
            if ($this->expireOrder($orderId)) {
                $expiredCount++;
            }
        }

        return $expiredCount;
    }
}
