<?php
/**
 * Redirect cũ: payments.php → orders.php
 * Trang thanh toán đã được gộp vào trang quản lý đơn hàng.
 */
require_once __DIR__ . '/../config/db.php';
$appBaseUrl = applicationBaseUrl();
header('Location: ' . $appBaseUrl . '/admin/orders.php', true, 301);
exit;
