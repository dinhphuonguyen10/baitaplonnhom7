<?php
// CLI: hủy các đơn hết thời gian giữ tồn kho và hoàn kho. VD: php scripts/het_han_giu_ton_kho.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

use App\Services\TonKhoDonHangService;

try {
    $service = new TonKhoDonHangService();
    $batchSize = 100;
    $expiredCount = 0;
    do {
        $batchCount = $service->expireReservations($batchSize);
        $expiredCount += $batchCount;
    } while ($batchCount === $batchSize);

    echo 'Đã hết hạn và hoàn tồn kho cho ' . $expiredCount . " đơn hàng.\n";
} catch (Throwable $e) {
    error_log('Order inventory expiration task failed: ' . $e->getMessage());
    fwrite(STDERR, "Không thể xử lý đơn hàng hết hạn. Xem nhật ký PHP để biết chi tiết.\n");
    exit(1);
}
