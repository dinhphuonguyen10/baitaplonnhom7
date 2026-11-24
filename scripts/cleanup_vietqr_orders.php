<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../include/order_payment_helper.php';

try {
    $expiredCount = expireOverdueQrPaymentOrders($conn);
    echo sprintf("Cancelled %d expired VietQR order(s).%s", $expiredCount, PHP_EOL);
} catch (Throwable $e) {
    error_log('Expired VietQR order cleanup failed: ' . $e->getMessage());
    fwrite(STDERR, "Could not cancel expired VietQR orders.\n");
    exit(1);
}

$conn->close();
