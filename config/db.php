<?php
// config/db.php - Kết nối MySQL, thiết lập charset utf8mb4
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "baitaplon";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Kết nối DB thất bại: " . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

// Lưu phản hồi của quản trị viên riêng để không thay đổi nội dung đánh giá của khách hàng.
$reviewRepliesTableSql = "CREATE TABLE IF NOT EXISTS review_replies (
    review_id int(10) unsigned NOT NULL,
    admin_username varchar(100) NOT NULL,
    reply text NOT NULL,
    replied_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (review_id),
    CONSTRAINT fk_review_replies_review FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if (!$conn->query($reviewRepliesTableSql)) {
    error_log('Review replies schema setup failed: ' . $conn->error);
}
$reviewImagesTable = $conn->query("SHOW TABLES LIKE 'review_images'");
if ($reviewImagesTable && $reviewImagesTable->num_rows === 0) {
    $reviewImagesTableSql = "CREATE TABLE review_images (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        review_id int(10) unsigned NOT NULL,
        image_path varchar(500) NOT NULL,
        sort_order tinyint(3) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_review_images_review (review_id),
        CONSTRAINT fk_review_images_review FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!$conn->query($reviewImagesTableSql)) {
        error_log('Review images schema setup failed: ' . $conn->error);
    }
}
$reviewEditCountColumn = $conn->query("SHOW COLUMNS FROM reviews LIKE 'edit_count'");
if ($reviewEditCountColumn && $reviewEditCountColumn->num_rows === 0) {
    if (!$conn->query('ALTER TABLE reviews ADD COLUMN edit_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER comment')) {
        error_log('Review edit counter schema setup failed: ' . $conn->error);
    }
}

// Tự đồng bộ các cột mới của bảng stores cho database đã tạo từ bản SQL cũ.
$storesTable = $conn->query("SHOW TABLES LIKE 'stores'");
if ($storesTable && $storesTable->num_rows > 0) {
    $storeColumns = [];
    $storeSchemaChanged = false;
    $columnsResult = $conn->query("SHOW COLUMNS FROM stores");
    if ($columnsResult) {
        while ($column = $columnsResult->fetch_assoc()) {
            $storeColumns[$column['Field']] = true;
        }
    }

    $missingStoreColumns = [
        'phone' => "ALTER TABLE stores ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER image",
        'hours' => "ALTER TABLE stores ADD COLUMN hours VARCHAR(100) NOT NULL DEFAULT '08:00 - 22:00' AFTER phone",
        'status' => "ALTER TABLE stores ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER hours",
    ];

    foreach ($missingStoreColumns as $columnName => $alterSql) {
        if (!isset($storeColumns[$columnName])) {
            if ($conn->query($alterSql)) {
                $storeSchemaChanged = true;
            }
        }
    }

    // Khôi phục dữ liệu cửa hàng mặc định từ file SQL cũ khi database chưa có dữ liệu.
    $storeCountResult = $conn->query("SELECT COUNT(*) AS total FROM stores");
    $storeCount = $storeCountResult ? (int) $storeCountResult->fetch_assoc()['total'] : 0;
    if ($storeSchemaChanged || $storeCount === 0) {
        $defaultStores = [
            [1, 'ÉCRIN JEWELRY Cầu Giấy', '123 Xuân Thủy, Dịch Vọng, Cầu Giấy, Hà Nội', '024.1234.5678'],
            [2, 'ÉCRIN JEWELRY Hoàn Kiếm', '45 Hàng Đào, Hoàn Kiếm, Hà Nội', '024.8888.9999'],
            [3, 'ÉCRIN JEWELRY Quận 1', '89 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. HCM', '028.1111.2222'],
            [4, 'ÉCRIN JEWELRY Quận 10', '256 Sư Vạn Hạnh, Phường 12, Quận 10, TP. HCM', '028.4444.5555'],
            [5, 'ÉCRIN JEWELRY Đà Nẵng', '12 Lê Duẩn, Hải Châu, Đà Nẵng', '0236.777.888'],
        ];
        $storeStmt = $conn->prepare(
            "INSERT INTO stores (id, name, address, phone, hours, status)
             VALUES (?, ?, ?, ?, '08:00 - 22:00', 'active')
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), address = VALUES(address), phone = VALUES(phone),
                hours = VALUES(hours), status = VALUES(status)"
        );
        if ($storeStmt) {
            foreach ($defaultStores as [$id, $name, $address, $phone]) {
                $storeStmt->bind_param('isss', $id, $name, $address, $phone);
                $storeStmt->execute();
            }
            $storeStmt->close();
        }
    }
}

// Tự đồng bộ cột description của bảng products và VIEW view_product_details nếu database chưa cập nhật
$prodDescCol = $conn->query("SHOW COLUMNS FROM products LIKE 'description'");
if ($prodDescCol && $prodDescCol->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN description TEXT NULL AFTER category_id");
    $conn->query("CREATE OR REPLACE VIEW view_product_details AS
        SELECT
            p.id, p.name, p.category_id, p.price_sale, p.price_original, p.stock, p.image, p.description,
            c.name AS category_name, c.slug AS category,
            COALESCE(SUM(oi.quantity), 0) AS sold_count
        FROM products p
        JOIN categories c ON p.category_id = c.id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        GROUP BY p.id");
}

// Tự đồng bộ bảng wishlists (danh sách yêu thích / thả tim) nếu chưa tồn tại
$wishlistTable = $conn->query("SHOW TABLES LIKE 'wishlists'");
if ($wishlistTable && $wishlistTable->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS wishlists (
        id int(10) unsigned NOT NULL AUTO_INCREMENT,
        user_id int(10) unsigned NOT NULL,
        product_id int(10) unsigned NOT NULL,
        created_at datetime NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_product (user_id, product_id),
        KEY idx_user (user_id),
        KEY idx_product (product_id),
        CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

// Keep saved checkout locations available in databases created before map support.
$addressTable = $conn->query("SHOW TABLES LIKE 'addresses'");
if ($addressTable && $addressTable->num_rows > 0) {
    $addressColumns = [];
    $addressColumnsResult = $conn->query("SHOW COLUMNS FROM addresses");
    if ($addressColumnsResult) {
        while ($column = $addressColumnsResult->fetch_assoc()) {
            $addressColumns[$column['Field']] = true;
        }
    }

    foreach ([
        'latitude' => 'ALTER TABLE addresses ADD COLUMN latitude DECIMAL(10,7) NULL AFTER is_default',
        'longitude' => 'ALTER TABLE addresses ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
    ] as $columnName => $alterSql) {
        if (!isset($addressColumns[$columnName]) && !$conn->query($alterSql)) {
            error_log('Address map schema update failed: ' . $conn->error);
        }
    }
}
