<?php
// scripts/tao_du_lieu_mau_don_hang.php - Tạo khách hàng, đơn hàng và lượt yêu thích mẫu (chạy bằng CLI).
require_once __DIR__ . '/../app/bootstrap.php';

use App\Database\TaoDuLieuMau;

$seeder = new TaoDuLieuMau();

// 1. Thêm các user khách hàng demo nếu chưa có
$userIds = $seeder->seedUsers();
echo "Total users available: " . count($userIds) . "\n";

// 2. Thêm đơn hàng để tạo lượt mua cao (Sold count)
echo $seeder->seedOrders($userIds) ? "Seed orders completed.\n" : "Orders already seeded.\n";

// 3. Thêm lượt tim (Wishlist) vào bảng wishlists
$seeder->seedWishlists($userIds);
echo "Seed wishlists completed.\n";
