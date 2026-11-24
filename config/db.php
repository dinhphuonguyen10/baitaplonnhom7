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

// Tự đồng bộ cấu trúc bảng products và VIEW view_product_details
$prodColumns = [];
$prodColsRes = $conn->query("SHOW COLUMNS FROM products");
if ($prodColsRes) {
    while ($col = $prodColsRes->fetch_assoc()) {
        $prodColumns[$col['Field']] = true;
    }
}

$needUpdateView = false;
if (!isset($prodColumns['description'])) {
    $conn->query("ALTER TABLE products ADD COLUMN description TEXT NULL AFTER category_id");
    $needUpdateView = true;
}
if (!isset($prodColumns['is_featured'])) {
    $conn->query("ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER description");
    $needUpdateView = true;
}
if (!isset($prodColumns['is_suggested'])) {
    $conn->query("ALTER TABLE products ADD COLUMN is_suggested TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured");
    $needUpdateView = true;
}
if (!isset($prodColumns['is_new'])) {
    $conn->query("ALTER TABLE products ADD COLUMN is_new TINYINT(1) NOT NULL DEFAULT 0 AFTER is_suggested");
    $needUpdateView = true;
}

// Xóa bỏ các view cũ không còn dùng để code gọn gàng, tránh nhầm lẫn
$conn->query("DROP VIEW IF EXISTS view_featured_products");
$conn->query("DROP VIEW IF EXISTS view_suggested_products");
$conn->query("DROP VIEW IF EXISTS view_new_products");

// Khởi tạo hoặc cập nhật VIEW view_product_details tính toán hoàn toàn bằng SQL
if ($needUpdateView || true) {
    $conn->query("CREATE OR REPLACE VIEW view_product_details AS
        SELECT 
            p.id,
            p.name,
            p.price_sale,
            p.price_original,
            p.discount,
            p.gift,
            p.image,
            p.stock,
            p.category_id,
            p.description,
            p.is_featured,
            p.is_suggested,
            p.is_new,
            c.slug AS category,
            c.name AS category_name,
            COALESCE((
                SELECT SUM(oi.quantity)
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id
                WHERE oi.product_id = p.id
                  AND (o.status IS NULL OR o.status NOT IN ('Đã hủy', 'huy', 'cancelled'))
            ), 0) AS sold_count,
            COALESCE((
                SELECT COUNT(*)
                FROM wishlists w
                WHERE w.product_id = p.id
            ), 0) AS wishlist_count,
            COALESCE((
                SELECT ROUND(AVG(r.rating), 1)
                FROM reviews r
                WHERE r.product_id = p.id AND r.rating BETWEEN 1 AND 5
            ), 0) AS average_rating,
            COALESCE((
                SELECT COUNT(*)
                FROM reviews r
                WHERE r.product_id = p.id AND r.rating BETWEEN 1 AND 5
            ), 0) AS review_count
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        GROUP BY p.id, c.slug, c.name");
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

// 1. Tự đồng bộ SQL Function: Tính toán và định dạng phần trăm giảm giá
$conn->query("DROP FUNCTION IF EXISTS fn_format_discount");
$conn->query("CREATE FUNCTION fn_format_discount(sale DECIMAL(12,2), orig DECIMAL(12,2)) 
RETURNS VARCHAR(50) DETERMINISTIC
BEGIN
    IF orig > 0 AND sale < orig THEN
        RETURN CONCAT('-', ROUND((1 - (sale / orig)) * 100), '%');
    END IF;
    RETURN NULL;
END");

// 2. Tự đồng bộ SQL Function: Lọc thời gian đơn hàng chuẩn phong cách sàn TMĐT (Shopee)
$conn->query("DROP FUNCTION IF EXISTS fn_order_time_condition");
$conn->query("CREATE FUNCTION fn_order_time_condition(order_date DATETIME, filter_time VARCHAR(20))
RETURNS TINYINT(1) DETERMINISTIC
BEGIN
    IF filter_time = '30days' THEN
        RETURN order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY);
    ELSEIF filter_time = '6months' THEN
        RETURN order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH);
    ELSEIF filter_time = 'one_year' THEN
        RETURN order_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR);
    ELSE
        RETURN 1;
    END IF;
END");

// 4. Tự đồng bộ cột Soft Delete và Trạng thái sản phẩm (Status, IsDeleted, DeletedAt, DeletedBy)
$colStatusCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'status'");
if ($colStatusCheck && $colStatusCheck->num_rows === 0) {
    $conn->query("ALTER TABLE products ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER is_new");
    $conn->query("ALTER TABLE products ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
    $conn->query("ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER is_deleted");
    $conn->query("ALTER TABLE products ADD COLUMN deleted_by VARCHAR(100) NULL DEFAULT NULL AFTER deleted_at");
    $conn->query("ALTER TABLE products ADD INDEX idx_products_status_deleted (is_deleted, status)");
    $conn->query("ALTER TABLE products ADD INDEX idx_products_cat_deleted (category_id, is_deleted, status)");
    $conn->query("ALTER TABLE products ADD CONSTRAINT chk_products_status CHECK (status IN ('active', 'inactive', 'hidden'))");
    $conn->query("ALTER TABLE products ADD CONSTRAINT chk_products_is_deleted CHECK (is_deleted IN (0, 1))");
}

// Đảm bảo VIEW view_product_details luôn có đầy đủ cột status, is_deleted, deleted_at, deleted_by
$viewColCheck = $conn->query("SHOW COLUMNS FROM view_product_details LIKE 'is_deleted'");
if ($viewColCheck && $viewColCheck->num_rows === 0) {
    $conn->query("CREATE OR REPLACE VIEW view_product_details AS
    SELECT 
        p.id,
        p.name,
        p.price_sale,
        p.price_original,
        p.discount,
        p.gift,
        p.image,
        p.stock,
        p.category_id,
        p.description,
        p.is_featured,
        p.is_suggested,
        p.is_new,
        p.status,
        p.is_deleted,
        p.deleted_at,
        p.deleted_by,
        c.slug AS category,
        c.name AS category_name,
        COALESCE((
            SELECT SUM(oi.quantity)
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE oi.product_id = p.id
              AND (o.status IS NULL OR o.status NOT IN ('Đã hủy', 'huy', 'cancelled'))
        ), 0) AS sold_count,
        COALESCE((
            SELECT COUNT(0)
            FROM wishlists w
            WHERE w.product_id = p.id
        ), 0) AS wishlist_count,
        COALESCE((
            SELECT ROUND(AVG(r.rating), 1)
            FROM reviews r
            WHERE r.product_id = p.id
              AND r.rating BETWEEN 1 AND 5
        ), 0) AS average_rating,
        COALESCE((
            SELECT COUNT(0)
            FROM reviews r
            WHERE r.product_id = p.id
              AND r.rating BETWEEN 1 AND 5
        ), 0) AS review_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    GROUP BY p.id, c.slug, c.name");
}

$conn->query("DROP TRIGGER IF EXISTS trg_products_before_insert");
$conn->query("CREATE TRIGGER trg_products_before_insert BEFORE INSERT ON products FOR EACH ROW
BEGIN
    IF NEW.price_original > 0 AND NEW.price_sale < NEW.price_original THEN
        SET NEW.discount = fn_format_discount(NEW.price_sale, NEW.price_original);
    ELSE
        SET NEW.price_original = 0.00;
        SET NEW.discount = NULL;
    END IF;
END");

$conn->query("DROP TRIGGER IF EXISTS trg_products_before_update");
$conn->query("CREATE TRIGGER trg_products_before_update BEFORE UPDATE ON products FOR EACH ROW
BEGIN
    -- Nếu admin chủ động bỏ giảm giá (truyền price_original = 0 hoặc âm)
    IF NEW.price_original IS NOT NULL AND NEW.price_original <= 0 AND (OLD.price_original > 0 OR OLD.discount IS NOT NULL) THEN
        SET NEW.price_original = 0.00;
        SET NEW.discount = NULL;
    -- Nếu admin chủ động truyền giá gốc mới hợp lệ (> 0)
    ELSEIF NEW.price_original IS NOT NULL AND NEW.price_original > 0 THEN
        IF NEW.price_sale < NEW.price_original THEN
            SET NEW.discount = fn_format_discount(NEW.price_sale, NEW.price_original);
        ELSE
            SET NEW.price_original = 0.00;
            SET NEW.discount = NULL;
        END IF;
    -- Nếu giá bán giảm so với giá bán cũ
    ELSEIF NEW.price_sale < OLD.price_sale THEN
        SET NEW.price_original = OLD.price_sale;
        SET NEW.discount = fn_format_discount(NEW.price_sale, OLD.price_sale);
    -- Nếu giá bán tăng so với giá bán cũ (không hiện giá cũ, xóa giảm giá)
    ELSEIF NEW.price_sale > OLD.price_sale THEN
        SET NEW.price_original = 0.00;
        SET NEW.discount = NULL;
    -- Giá không đổi: giữ nguyên giảm giá cũ nếu còn hợp lệ
    ELSE
        IF OLD.price_original > 0 AND NEW.price_sale < OLD.price_original THEN
            SET NEW.price_original = OLD.price_original;
            SET NEW.discount = OLD.discount;
        ELSE
            SET NEW.price_original = 0.00;
            SET NEW.discount = NULL;
        END IF;
    END IF;
END");
