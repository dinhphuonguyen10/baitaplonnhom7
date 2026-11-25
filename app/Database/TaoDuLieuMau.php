<?php

namespace App\Database;

use App\Core\Database;

/**
 * Lớp TaoDuLieuMau - tạo dữ liệu mẫu cho môi trường demo:
 * khách hàng mẫu, đơn hàng đã giao (tạo lượt bán) và lượt yêu thích (wishlist).
 * Dùng bởi scripts/tao_du_lieu_mau_don_hang.php.
 */
class TaoDuLieuMau
{
    /** [username, email, họ tên, số điện thoại] */
    public const DEMO_USERS = [
        ['nguyenmai', 'mai@gmail.com', 'Nguyễn Thị Mai', '0912345671'],
        ['tranlong', 'long@gmail.com', 'Trần Phi Long', '0912345672'],
        ['leanh', 'leanh@gmail.com', 'Lê Tuấn Anh', '0912345673'],
        ['hoangyen', 'yen@gmail.com', 'Hoàng Hải Yến', '0912345674'],
        ['phamvu', 'vu@gmail.com', 'Phạm Minh Vũ', '0912345675'],
        ['doquyen', 'quyen@gmail.com', 'Đỗ Đỗ Quyên', '0912345676'],
        ['bichtram', 'tram@gmail.com', 'Bùi Bích Trâm', '0912345677'],
        ['minhtriet', 'triet@gmail.com', 'Võ Minh Triết', '0912345678'],
    ];

    /** product_id => số lượng bán (sản phẩm & set quà bán chạy). */
    public const FEATURED_SALES = [
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
        13 => 4,  // Nhẫn Tình Nồng
    ];

    /** product_id => số lượt tim (cả set quà và sản phẩm đơn lẻ). */
    public const WISHLIST_LIKES = [
        9 => 7,  // Set quà tặng Hương Sắc Nàng Thơ
        13 => 6, // Nhẫn Tình Nồng Vĩnh Cửu
        4 => 5,  // Set quà tặng Trọn Vẹn Yêu Thương
        7 => 5,  // Dây chuyền Vầng Trăng Yêu Thương
        19 => 4, // Set quà tặng Nữ Thần Kiêu Sa
        11 => 4, // Bông tai Cánh Bướm Tinh Khôi
        15 => 3, // Vòng tay Chuông Gió Thanh Âm
        34 => 3, // Set quà tặng Khởi Sắc Hoàng Kim
        8 => 3,  // Nhẫn Vương Miện
        12 => 2, // Dây chuyền Thiên Cốt Khảm Ngọc
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Thêm các khách hàng demo còn thiếu. Trả về danh sách ID người dùng hiện có.
     *
     * @return int[]
     */
    public function seedUsers(): array
    {
        $userIds = array_map('intval', $this->db->fetchColumn('SELECT id FROM users ORDER BY id ASC'));

        $defaultPassword = password_hash('123456', PASSWORD_DEFAULT);
        foreach (self::DEMO_USERS as $user) {
            $found = $this->db->fetchOne('SELECT id FROM users WHERE username = ?', [$user[0]], 's');
            if ($found) {
                if (!in_array((int) $found['id'], $userIds, true)) {
                    $userIds[] = (int) $found['id'];
                }
                continue;
            }

            $userIds[] = $this->db->insert(
                'INSERT INTO users (username, password, phone, created_at) VALUES (?, ?, ?, NOW())',
                [$user[0], $defaultPassword, $user[3]],
                'sss'
            );
        }

        return $userIds;
    }

    /**
     * Tạo đơn hàng đã giao cho các sản phẩm bán chạy nếu chưa có đơn demo (ngoài đơn #1).
     *
     * @param int[] $userIds
     * @return bool true nếu vừa tạo, false nếu đã có dữ liệu
     */
    public function seedOrders(array $userIds): bool
    {
        if ((int) $this->db->fetchValue('SELECT COUNT(*) as total FROM orders WHERE id > 1') !== 0) {
            return false;
        }

        foreach (self::FEATURED_SALES as $productId => $quantity) {
            // Giá bán hiệu lực: hết hạn khuyến mãi thì quay về giá gốc; lưu kèm ảnh chụp SKU/danh mục.
            $product = $this->db->fetchOne(
                "SELECT p.sku, p.name, p.price_original, c.name AS category_name,
                        CASE WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                             THEN p.price_original ELSE p.price_sale END AS effective_price,
                        (CASE WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                              THEN p.price_original ELSE p.price_sale END) * ? AS line_total,
                        (p.price_original - CASE WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                                                 THEN p.price_original ELSE p.price_sale END) * ? AS line_discount,
                        p.image
                 FROM products p
                 INNER JOIN categories c ON c.id = p.category_id
                 WHERE p.id = ?",
                [$quantity, $quantity, $productId],
                'iii'
            );
            if (!$product) {
                continue;
            }

            $randomUser = $userIds[array_rand($userIds)];
            $totalPrice = (string) $product['line_total'];
            $discountAmount = (string) $product['line_discount'];

            $orderId = $this->db->insert(
                "INSERT INTO orders (user_id, full_name, phone, email, address, total_price, discount_amount, status, payment_status, payment_method, created_at) VALUES (?, 'Khách hàng VIP', '0912345678', 'order@ecrin.vn', 'Hà Nội', ?, ?, 'Đã giao hàng', 'Đã thanh toán', 'COD', NOW() - INTERVAL FLOOR(RAND() * 30) DAY)",
                [$randomUser, $totalPrice, $discountAmount],
                'iss'
            );

            $this->db->insert(
                'INSERT INTO order_items
                    (order_id, product_id, product_sku, attributes_snapshot, category_name_snapshot,
                     product_name, quantity, price, original_price, discount_amount, line_total, image)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $orderId, $productId, $product['sku'], '', $product['category_name'],
                    $product['name'], $quantity, $product['effective_price'], $product['price_original'],
                    $discountAmount, $totalPrice, $product['image'],
                ],
                'iissssisssss'
            );
        }

        return true;
    }

    /**
     * Thêm lượt tim cho các sản phẩm (bỏ qua nếu đã có).
     *
     * @param int[] $userIds
     */
    public function seedWishlists(array $userIds): void
    {
        foreach (self::WISHLIST_LIKES as $productId => $likes) {
            foreach (array_slice($userIds, 0, min($likes, count($userIds))) as $userId) {
                $this->db->execute(
                    'INSERT IGNORE INTO wishlists (user_id, product_id, created_at) VALUES (?, ?, NOW())',
                    [(int) $userId, $productId],
                    'ii'
                );
            }
        }
    }
}
