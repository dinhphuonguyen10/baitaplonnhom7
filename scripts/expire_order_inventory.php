<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    require_once __DIR__ . '/../config/db.php';

    $batchSize = 100;
    $expiredCount = 0;
    do {
        $batchCount = expireOrderInventoryReservations($conn, $batchSize);
        $expiredCount += $batchCount;
    } while ($batchCount === $batchSize);

    echo 'Đã hết hạn và hoàn tồn kho cho ' . $expiredCount . " đơn hàng.\n";
    $conn->close();
} catch (Throwable $e) {
    error_log('Order inventory expiration task failed: ' . $e->getMessage());
    fwrite(STDERR, "Không thể xử lý đơn hàng hết hạn. Xem nhật ký PHP để biết chi tiết.\n");
    exit(1);
}
