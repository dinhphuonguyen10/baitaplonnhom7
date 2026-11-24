<?php

function releaseReservedOrderInventory(mysqli $conn, int $orderId): bool
{
    $inventoryStmt = $conn->prepare(
        'SELECT inventory_status FROM orders WHERE id = ? FOR UPDATE'
    );
    if (!$inventoryStmt) {
        throw new RuntimeException('Không thể kiểm tra trạng thái tồn kho của đơn hàng.');
    }
    $inventoryStmt->bind_param('i', $orderId);
    $inventoryStmt->execute();
    $order = $inventoryStmt->get_result()->fetch_assoc();
    $inventoryStmt->close();

    if (!$order) {
        throw new RuntimeException('Không tìm thấy đơn hàng cần hoàn tồn kho.');
    }
    if ($order['inventory_status'] !== 'reserved') {
        return false;
    }

    $itemsStmt = $conn->prepare(
        'SELECT product_id, SUM(quantity) AS quantity
         FROM order_items
         WHERE order_id = ?
         GROUP BY product_id
         ORDER BY product_id'
    );
    if (!$itemsStmt) {
        throw new RuntimeException('Không thể đọc sản phẩm cần hoàn tồn kho.');
    }
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        if ($item['product_id'] === null || (int) $item['quantity'] < 1) {
            $itemsStmt->close();
            throw new RuntimeException('Chi tiết đơn hàng không đủ dữ liệu để hoàn tồn kho.');
        }
        $items[] = [
            'product_id' => (int) $item['product_id'],
            'quantity' => (int) $item['quantity'],
        ];
    }
    $itemsStmt->close();

    if ($items === []) {
        throw new RuntimeException('Đơn hàng không có chi tiết để hoàn tồn kho.');
    }

    $releaseStmt = $conn->prepare(
        "UPDATE orders
         SET inventory_status = 'released', inventory_released_at = NOW()
         WHERE id = ? AND inventory_status = 'reserved'"
    );
    if (!$releaseStmt) {
        throw new RuntimeException('Không thể đánh dấu tồn kho đã hoàn.');
    }
    $releaseStmt->bind_param('i', $orderId);
    $releaseStmt->execute();
    if ($releaseStmt->affected_rows !== 1) {
        $releaseStmt->close();
        throw new RuntimeException('Tồn kho của đơn hàng đã được xử lý ở thao tác khác.');
    }
    $releaseStmt->close();

    $restockStmt = $conn->prepare(
        'UPDATE products SET stock = stock + ? WHERE id = ?'
    );
    if (!$restockStmt) {
        throw new RuntimeException('Không thể chuẩn bị thao tác hoàn tồn kho.');
    }
    foreach ($items as $item) {
        $restockStmt->bind_param('ii', $item['quantity'], $item['product_id']);
        $restockStmt->execute();
        if ($restockStmt->affected_rows !== 1) {
            $restockStmt->close();
            throw new RuntimeException('Không thể hoàn tồn kho cho sản phẩm trong đơn hàng.');
        }
    }
    $restockStmt->close();

    return true;
}

function expireOrderInventoryReservations(mysqli $conn, int $limit = 100): int
{
    $limit = max(1, min(500, $limit));
    $conn->begin_transaction();

    try {
        $ordersStmt = $conn->prepare(
            "SELECT id
             FROM orders
             WHERE inventory_status = 'reserved'
               AND status = 'Chờ xác nhận'
               AND payment_status <> 'Đã thanh toán'
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()
             ORDER BY expires_at, id
             LIMIT ?
             FOR UPDATE"
        );
        if (!$ordersStmt) {
            throw new RuntimeException('Không thể tìm đơn hàng đã hết thời gian giữ tồn kho.');
        }
        $ordersStmt->bind_param('i', $limit);
        $ordersStmt->execute();
        $ordersResult = $ordersStmt->get_result();
        $orderIds = [];
        while ($row = $ordersResult->fetch_assoc()) {
            $orderIds[] = (int) $row['id'];
        }
        $ordersStmt->close();

        $expireStmt = $conn->prepare(
            "UPDATE orders
             SET status = 'Đã hủy',
                 cancelled_at = COALESCE(cancelled_at, NOW()),
                 cancellation_reason = COALESCE(NULLIF(cancellation_reason, ''), 'Đơn hàng hết hạn giữ tồn kho.')
             WHERE id = ?
               AND inventory_status = 'released'
               AND status = 'Chờ xác nhận'
               AND payment_status <> 'Đã thanh toán'
               AND expires_at <= NOW()"
        );
        $historyStmt = $conn->prepare(
            "INSERT INTO order_status_history
                (order_id, status, old_status, new_status, note, actor_name)
             VALUES (?, 'Đã hủy', ?, 'Đã hủy', ?, 'Hệ thống')"
        );
        if (!$expireStmt || !$historyStmt) {
            throw new RuntimeException('Không thể ghi nhận đơn hàng hết hạn.');
        }

        $expiredCount = 0;
        foreach ($orderIds as $orderId) {
            if (!releaseReservedOrderInventory($conn, $orderId)) {
                throw new RuntimeException('Tồn kho của đơn hàng hết hạn đã được xử lý ở thao tác khác.');
            }

            $oldStatus = 'Chờ xác nhận';
            $expireStmt->bind_param('i', $orderId);
            $expireStmt->execute();
            if ($expireStmt->affected_rows !== 1) {
                throw new RuntimeException('Không thể chuyển đơn hàng hết hạn sang trạng thái đã hủy.');
            }

            $note = 'Đơn hàng hết hạn giữ tồn kho; hệ thống đã hủy đơn và hoàn kho.';
            $historyStmt->bind_param('iss', $orderId, $oldStatus, $note);
            $historyStmt->execute();
            if ($historyStmt->affected_rows !== 1) {
                throw new RuntimeException('Không thể ghi lịch sử hủy đơn hàng hết hạn.');
            }
            $expiredCount++;
        }

        $expireStmt->close();
        $historyStmt->close();
        $conn->commit();
        return $expiredCount;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('Order inventory expiration failed: ' . $e->getMessage());
        throw $e;
    }
}
