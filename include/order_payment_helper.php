<?php

function ensureOrderPaymentDeadlineSchema(mysqli $conn): void
{
    $column = $conn->query("SHOW COLUMNS FROM orders LIKE 'payment_deadline_at'");
    if (!$column) {
        throw new RuntimeException('Không thể kiểm tra cấu trúc thời hạn thanh toán.');
    }

    if ($column->num_rows === 0) {
        try {
            $conn->query(
                'ALTER TABLE orders
                 ADD COLUMN payment_deadline_at DATETIME NULL DEFAULT NULL AFTER payment_status'
            );
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1060) {
                throw $e;
            }
        }

    }

    $index = $conn->query(
        "SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_qr_payment_deadline'"
    );
    if (!$index) {
        throw new RuntimeException('Không thể kiểm tra chỉ mục thời hạn thanh toán.');
    }
    if ($index->num_rows === 0) {
        try {
            $conn->query(
                'ALTER TABLE orders
                 ADD INDEX idx_orders_qr_payment_deadline
                 (payment_method, payment_status, payment_deadline_at)'
            );
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() !== 1061) {
                throw $e;
            }
        }
    }
}

function expireQrPaymentOrder(mysqli $conn, int $orderId): bool
{
    if ($orderId < 1) {
        return false;
    }

    ensureOrderPaymentDeadlineSchema($conn);
    $startLegacyDeadline = $conn->prepare(
        "UPDATE orders
         SET payment_deadline_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
         WHERE id = ?
           AND payment_method = 'BANK_QR'
           AND payment_status = 'Thanh toán thất bại'
           AND payment_deadline_at IS NULL
           AND status IN ('Chờ xác nhận', 'Pending')"
    );
    if (!$startLegacyDeadline) {
        throw new RuntimeException('Không thể khởi tạo thời hạn thanh toán.');
    }
    $startLegacyDeadline->bind_param('i', $orderId);
    $startLegacyDeadline->execute();
    $startLegacyDeadline->close();

    $transactionStarted = false;

    try {
        $conn->begin_transaction();
        $transactionStarted = true;

        $stmt = $conn->prepare(
            'SELECT status, payment_status, payment_method, payment_deadline_at,
                    payment_deadline_at <= NOW() AS deadline_passed
             FROM orders
             WHERE id = ?
             FOR UPDATE'
        );
        if (!$stmt) {
            throw new RuntimeException('Không thể kiểm tra thời hạn đơn hàng.');
        }
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (
            !$order ||
            $order['payment_method'] !== 'BANK_QR' ||
            $order['payment_status'] !== 'Thanh toán thất bại' ||
            !in_array($order['status'], ['Chờ xác nhận', 'Pending'], true) ||
            (int) $order['deadline_passed'] !== 1
        ) {
            $conn->commit();
            $transactionStarted = false;
            return false;
        }

        $updateOrder = $conn->prepare(
            "UPDATE orders
             SET status = 'Đã hủy'
             WHERE id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status = 'Thanh toán thất bại'
               AND payment_deadline_at <= NOW()
               AND status IN ('Chờ xác nhận', 'Pending')"
        );
        if (!$updateOrder) {
            throw new RuntimeException('Không thể hủy đơn hàng đã hết hạn.');
        }
        $updateOrder->bind_param('i', $orderId);
        $updateOrder->execute();
        if ($updateOrder->affected_rows !== 1) {
            $updateOrder->close();
            throw new RuntimeException('Trạng thái đơn hàng đã thay đổi khi hết hạn.');
        }
        $updateOrder->close();

        $items = $conn->prepare(
            'SELECT product_id, quantity
             FROM order_items
             WHERE order_id = ?
             FOR UPDATE'
        );
        if (!$items) {
            throw new RuntimeException('Không thể tải sản phẩm cần hoàn kho.');
        }
        $items->bind_param('i', $orderId);
        $items->execute();
        $itemResult = $items->get_result();

        $restock = $conn->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');
        if (!$restock) {
            throw new RuntimeException('Không thể chuẩn bị hoàn kho sản phẩm.');
        }
        while ($item = $itemResult->fetch_assoc()) {
            if ($item['product_id'] === null) {
                continue;
            }
            $quantity = (int) $item['quantity'];
            $productId = (int) $item['product_id'];
            $restock->bind_param('ii', $quantity, $productId);
            $restock->execute();
            if ($restock->affected_rows !== 1) {
                throw new RuntimeException('Không thể hoàn kho sản phẩm của đơn đã hết hạn.');
            }
        }
        $restock->close();
        $items->close();

        $conn->commit();
        $transactionStarted = false;
        return true;
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        throw $e;
    }
}

function expireOverdueQrPaymentOrders(mysqli $conn): int
{
    ensureOrderPaymentDeadlineSchema($conn);
    if (!$conn->query(
        "UPDATE orders
         SET payment_deadline_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)
         WHERE payment_method = 'BANK_QR'
           AND payment_status = 'Thanh toán thất bại'
           AND payment_deadline_at IS NULL
           AND status IN ('Chờ xác nhận', 'Pending')"
    )) {
        throw new RuntimeException('Không thể khởi tạo thời hạn cho đơn QR cũ.');
    }

    $result = $conn->query(
        "SELECT id
         FROM orders
         WHERE payment_method = 'BANK_QR'
           AND payment_status = 'Thanh toán thất bại'
           AND payment_deadline_at <= NOW()
           AND status IN ('Chờ xác nhận', 'Pending')
         ORDER BY id"
    );
    if (!$result) {
        throw new RuntimeException('Không thể tìm đơn VietQR đã hết hạn.');
    }

    $orderIds = [];
    while ($row = $result->fetch_assoc()) {
        $orderIds[] = (int) $row['id'];
    }
    $result->free();

    $expiredCount = 0;
    foreach ($orderIds as $orderId) {
        if (expireQrPaymentOrder($conn, $orderId)) {
            $expiredCount++;
        }
    }

    return $expiredCount;
}
