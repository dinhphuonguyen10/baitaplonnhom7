<?php
// scripts/cleanup_vietqr_orders.php - Hủy các đơn VietQR quá hạn thanh toán và hoàn kho (chạy bằng CLI/cron).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

try {
    $expiredCount = (new App\Services\OrderExpiryService())->expireOverdueOrders();
    echo sprintf("Cancelled %d expired VietQR order(s).%s", $expiredCount, PHP_EOL);
} catch (Throwable $e) {
    error_log('Expired VietQR order cleanup failed: ' . $e->getMessage());
    fwrite(STDERR, "Could not cancel expired VietQR orders.\n");
    exit(1);
}
