<?php
require_once __DIR__ . '/../config/db.php';

// 1. Thêm các user khách hàng demo nếu chưa có
$demoUsers = [
    ['nguyenmai', 'mai@gmail.com', 'Nguyễn Thị Mai', '0912345671'],
    ['tranlong', 'long@gmail.com', 'Trần Phi Long', '0912345672'],
    ['leanh', 'leanh@gmail.com', 'Lê Tuấn Anh', '0912345673'],
    ['hoangyen', 'yen@gmail.com', 'Hoàng Hải Yến', '0912345674'],
    ['phamvu', 'vu@gmail.com', 'Phạm Minh Vũ', '0912345675'],
    ['doquyen', 'quyen@gmail.com', 'Đỗ Đỗ Quyên', '0912345676'],
    ['bichtram', 'tram@gmail.com', 'Bùi Bích Trâm', '0912345677'],
    ['minhtriet', 'triet@gmail.com', 'Võ Minh Triết', '0912345678'],
];

$userIds = [];
// Lấy user_id sẵn có
$res = $conn->query("SELECT id FROM users ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
    $userIds[] = (int)$r['id'];
}

$defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
foreach ($demoUsers as $u) {
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $u[0]);
    $check->execute();
    $found = $check->get_result()->fetch_assoc();
    if ($found) {
        if (!in_array((int)$found['id'], $userIds, true)) {
            $userIds[] = (int)$found['id'];
        }
    } else {
        $ins = $conn->prepare("INSERT INTO users (username, password, phone, created_at) VALUES (?, ?, ?, NOW())");
        $ins->bind_param("sss", $u[0], $defaultPassword, $u[3]);
        if ($ins->execute()) {
            $userIds[] = $conn->insert_id;
        }
    }
}
echo "Total users available: " . count($userIds) . "\n";

// 2. Thêm đơn hàng để tạo lượt mua cao (Sold count)
// Một số sản phẩm & set quà bán chạy:
// ID 4: Set quà tặng Trọn Vẹn Yêu Thương
// ID 9: Set quà tặng Hương Sắc Nàng Thơ
// ID 3: Nhẫn Kim Cương Ánh Tuyết Éclat
// ID 2: Dây chuyền Trái Tim Đại Dương
// ID 1: Bông tai Hoa Tuyết Dạ Quang
// ID 10: Vòng tay Khắc Kỷ Niệm Vĩnh Hằng
// ID 8: Nhẫn Vương Miện Nữ Hoàng
// ID 14: Set quà tặng Tình Nồng Say Đắm
// ID 74: Vòng tay Tối Giản Tinh Tế

$featuredSales = [
    4 => 28,  // Set quà tặng Trọn Vẹn Yêu Thương
    3 => 24,  // Nhẫn Kim Cương
    9 => 20,  // Set quà tặng Hương Sắc Nàng Thơ
    2 => 18,  // Dây chuyền Trái Tim
    1 => 16,  // Bông tai Hoa Tuyết
    14 => 15, // Set quà tặng Tình Nồng Say Đắm
    8 => 12,  // Nhẫn Vương Miện
    10 => 10, // Vòng tay Khắc Kỷ Niệm
    74 => 8,  // Vòng tay Tối Giản
    6 => 6,   // Bông tai Giọt Sương
    7 => 5,   // Dây chuyền Vầng Trăng
    13 => 4   // Nhẫn Tình Nồng
];

// Kiểm tra xem đã có đơn hàng demo chưa (ngoài order 1)
$orderCheck = $conn->query("SELECT COUNT(*) as total FROM orders WHERE id > 1");
if ($orderCheck && $orderCheck->fetch_assoc()['total'] == 0) {
    foreach ($featuredSales as $prodId => $qty) {
        // Lấy thông tin sản phẩm
        $pRes = $conn->query("SELECT name, price_sale, image FROM products WHERE id = {$prodId}");
        if (!$pRes || !($prod = $pRes->fetch_assoc())) continue;

        $randUser = $userIds[array_rand($userIds)];
        $totalPrice = $prod['price_sale'] * $qty;
        
        $insOrder = $conn->prepare("INSERT INTO orders (user_id, full_name, phone, email, address, total_price, status, payment_status, payment_method, created_at) VALUES (?, 'Khách hàng VIP', '0912345678', 'order@ecrin.vn', 'Hà Nội', ?, 'Đã giao hàng', 'Đã thanh toán', 'COD', NOW() - INTERVAL FLOOR(RAND() * 30) DAY)");
        $insOrder->bind_param("id", $randUser, $totalPrice);
        $insOrder->execute();
        $newOrderId = $conn->insert_id;

        $insItem = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price, image) VALUES (?, ?, ?, ?, ?, ?)");
        $insItem->bind_param("iisids", $newOrderId, $prodId, $prod['name'], $qty, $prod['price_sale'], $prod['image']);
        $insItem->execute();
    }
    echo "Seed orders completed.\n";
} else {
    echo "Orders already seeded.\n";
}

// 3. Thêm lượt tim (Wishlist) vào bảng wishlists
// Đảm bảo cả Set quà và sản phẩm đơn lẻ có nhiều lượt tim
$wishlistLikes = [
    9  => 7, // Set quà tặng Hương Sắc Nàng Thơ
    13 => 6, // Nhẫn Tình Nồng Vĩnh Cửu
    4  => 5, // Set quà tặng Trọn Vẹn Yêu Thương
    7  => 5, // Dây chuyền Vầng Trăng Yêu Thương
    19 => 4, // Set quà tặng Nữ Thần Kiêu Sa
    11 => 4, // Bông tai Cánh Bướm Tinh Khôi
    15 => 3, // Vòng tay Chuông Gió Thanh Âm
    34 => 3, // Set quà tặng Khởi Sắc Hoàng Kim
    8  => 3, // Nhẫn Vương Miện
    12 => 2, // Dây chuyền Thiên Cốt Khảm Ngọc
];

foreach ($wishlistLikes as $prodId => $numLikes) {
    $targetUsers = array_slice($userIds, 0, min($numLikes, count($userIds)));
    foreach ($targetUsers as $uId) {
        $conn->query("INSERT IGNORE INTO wishlists (user_id, product_id, created_at) VALUES ({$uId}, {$prodId}, NOW())");
    }
}
echo "Seed wishlists completed.\n";
