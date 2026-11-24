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

$productLifecycleColumns = [
    'status' => "ALTER TABLE products ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER is_new",
    'is_deleted' => "ALTER TABLE products ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status",
    'deleted_at' => "ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER is_deleted",
    'deleted_by' => "ALTER TABLE products ADD COLUMN deleted_by VARCHAR(100) NULL DEFAULT NULL AFTER deleted_at",
];
foreach ($productLifecycleColumns as $columnName => $alterSql) {
    if (!isset($prodColumns[$columnName])) {
        if (!$conn->query($alterSql)) {
            error_log('Product lifecycle column migration failed for ' . $columnName . ': ' . $conn->error);
            throw new RuntimeException('Không thể cập nhật cấu trúc quản lý sản phẩm.');
        }
        $prodColumns[$columnName] = true;
        $needUpdateView = true;
    }
}

$productIndexes = [];
$productIndexResult = $conn->query('SHOW INDEX FROM products');
if (!$productIndexResult) {
    throw new RuntimeException('Không thể kiểm tra chỉ mục bảng sản phẩm: ' . $conn->error);
}
while ($productIndexRow = $productIndexResult->fetch_assoc()) {
    $productIndexes[$productIndexRow['Key_name']] = true;
}
foreach ([
    'idx_products_status_deleted' => 'ALTER TABLE products ADD INDEX idx_products_status_deleted (is_deleted, status)',
    'idx_products_cat_deleted' => 'ALTER TABLE products ADD INDEX idx_products_cat_deleted (category_id, is_deleted, status)',
    'idx_products_deleted_at' => 'ALTER TABLE products ADD INDEX idx_products_deleted_at (is_deleted, deleted_at)',
] as $indexName => $alterSql) {
    if (!isset($productIndexes[$indexName]) && !$conn->query($alterSql)) {
        error_log('Product lifecycle index migration failed for ' . $indexName . ': ' . $conn->error);
        throw new RuntimeException('Không thể tạo chỉ mục quản lý sản phẩm.');
    }
}

$productChecks = [];
$productChecksResult = $conn->query(
    "SELECT CONSTRAINT_NAME
     FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE()
       AND TABLE_NAME = 'products'
       AND CONSTRAINT_TYPE = 'CHECK'"
);
if (!$productChecksResult) {
    throw new RuntimeException('Không thể kiểm tra ràng buộc bảng sản phẩm: ' . $conn->error);
}
while ($productCheckRow = $productChecksResult->fetch_assoc()) {
    $productChecks[$productCheckRow['CONSTRAINT_NAME']] = true;
}
foreach ([
    'chk_products_status' => "ALTER TABLE products ADD CONSTRAINT chk_products_status CHECK (status IN ('active', 'inactive', 'hidden'))",
    'chk_products_is_deleted' => 'ALTER TABLE products ADD CONSTRAINT chk_products_is_deleted CHECK (is_deleted IN (0, 1))',
] as $constraintName => $alterSql) {
    if (!isset($productChecks[$constraintName]) && !$conn->query($alterSql)) {
        error_log('Product lifecycle check migration failed for ' . $constraintName . ': ' . $conn->error);
        throw new RuntimeException('Không thể tạo ràng buộc trạng thái sản phẩm.');
    }
}
if (!isset($productChecks['chk_products_deleted_metadata'])) {
    if (!$conn->query(
        "UPDATE products
         SET deleted_at = COALESCE(deleted_at, NOW())
         WHERE is_deleted = 1 AND deleted_at IS NULL"
    ) || !$conn->query(
        "UPDATE products
         SET deleted_at = NULL, deleted_by = NULL
         WHERE is_deleted = 0 AND (deleted_at IS NOT NULL OR deleted_by IS NOT NULL)"
    )) {
        error_log('Product soft-delete metadata normalization failed: ' . $conn->error);
        throw new RuntimeException('Không thể chuẩn hóa thông tin xóa mềm sản phẩm.');
    }
    if (!$conn->query(
        "ALTER TABLE products
         ADD CONSTRAINT chk_products_deleted_metadata
         CHECK (
             (is_deleted = 0 AND deleted_at IS NULL AND deleted_by IS NULL)
             OR (is_deleted = 1 AND deleted_at IS NOT NULL)
         )"
    )) {
        error_log('Product soft-delete metadata constraint migration failed: ' . $conn->error);
        throw new RuntimeException('Không thể tạo ràng buộc thông tin xóa mềm sản phẩm.');
    }
}

// Ensure view dependencies exist before creating view_product_details.
$wishlistTable = $conn->query("SHOW TABLES LIKE 'wishlists'");
if (!$wishlistTable) {
    throw new RuntimeException('Không thể kiểm tra bảng danh sách yêu thích: ' . $conn->error);
}
if ($wishlistTable->num_rows === 0 && !$conn->query("CREATE TABLE wishlists (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    user_id int(10) unsigned NOT NULL,
    product_id int(10) unsigned NOT NULL,
    created_at datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    UNIQUE KEY uniq_user_product (user_id, product_id),
    KEY idx_user (user_id),
    KEY idx_product (product_id),
    CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_wishlists_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci")) {
    error_log('Wishlist table migration failed: ' . $conn->error);
    throw new RuntimeException('Không thể khởi tạo bảng danh sách yêu thích.');
}

// Xóa bỏ các view cũ không còn dùng để code gọn gàng, tránh nhầm lẫn
$conn->query("DROP VIEW IF EXISTS view_featured_products");
$conn->query("DROP VIEW IF EXISTS view_suggested_products");
$conn->query("DROP VIEW IF EXISTS view_new_products");

// Khởi tạo hoặc cập nhật VIEW view_product_details tính toán hoàn toàn bằng SQL
if ($needUpdateView || true) {
    $viewUpdate = $conn->query("CREATE OR REPLACE VIEW view_product_details AS
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
    if (!$viewUpdate) {
        error_log('Product details view migration failed: ' . $conn->error);
        throw new RuntimeException('Không thể cập nhật danh sách sản phẩm.');
    }
}

// Keep product-linked records when a product is removed; order_items is already RESTRICT.
$productForeignKeys = [];
$productForeignKeysResult = $conn->query(
    "SELECT kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, rc.DELETE_RULE, rc.UPDATE_RULE
     FROM information_schema.KEY_COLUMN_USAGE kcu
     INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
       ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
      AND rc.TABLE_NAME = kcu.TABLE_NAME
      AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
     WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
       AND kcu.REFERENCED_TABLE_NAME = 'products'
       AND kcu.REFERENCED_COLUMN_NAME = 'id'
       AND kcu.COLUMN_NAME = 'product_id'"
);
if (!$productForeignKeysResult) {
    throw new RuntimeException('Không thể kiểm tra khóa ngoại sản phẩm: ' . $conn->error);
}
while ($productForeignKey = $productForeignKeysResult->fetch_assoc()) {
    $productForeignKeys[] = $productForeignKey;
}
foreach ($productForeignKeys as $productForeignKey) {
    $deleteRule = strtoupper((string)$productForeignKey['DELETE_RULE']);
    if (in_array($deleteRule, ['RESTRICT', 'NO ACTION'], true)) {
        continue;
    }

    $rawTableName = (string)$productForeignKey['TABLE_NAME'];
    $tableName = str_replace('`', '``', $rawTableName);
    $constraintName = str_replace('`', '``', (string)$productForeignKey['CONSTRAINT_NAME']);
    $newConstraintName = str_replace('`', '``', 'fk_' . $rawTableName . '_product_restrict');
    $updateRule = strtoupper((string)$productForeignKey['UPDATE_RULE']);
    if (!in_array($updateRule, ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'], true)) {
        throw new RuntimeException('Quy tắc cập nhật khóa ngoại sản phẩm không hợp lệ.');
    }
    $alterForeignKeySql = "ALTER TABLE `{$tableName}` DROP FOREIGN KEY `{$constraintName}`,
        ADD CONSTRAINT `{$newConstraintName}` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE RESTRICT ON UPDATE {$updateRule}";
    if (!$conn->query($alterForeignKeySql)) {
        error_log('Product foreign-key migration failed for ' . $productForeignKey['TABLE_NAME'] . ': ' . $conn->error);
        throw new RuntimeException('Không thể bảo vệ dữ liệu liên quan đến sản phẩm.');
    }
}

$orderItemsOrderForeignKey = $conn->query(
    "SELECT kcu.TABLE_NAME, kcu.CONSTRAINT_NAME, rc.DELETE_RULE, rc.UPDATE_RULE
     FROM information_schema.KEY_COLUMN_USAGE kcu
     INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
       ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
      AND rc.TABLE_NAME = kcu.TABLE_NAME
      AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME
     WHERE kcu.CONSTRAINT_SCHEMA = DATABASE()
       AND kcu.TABLE_NAME = 'order_items'
       AND kcu.COLUMN_NAME = 'order_id'
       AND kcu.REFERENCED_TABLE_NAME = 'orders'
       AND kcu.REFERENCED_COLUMN_NAME = 'id'
     LIMIT 1"
);
if (!$orderItemsOrderForeignKey) {
    throw new RuntimeException('Không thể kiểm tra khóa ngoại chi tiết đơn hàng.');
}
$orderItemsOrderForeignKeyRow = $orderItemsOrderForeignKey->fetch_assoc();
if ($orderItemsOrderForeignKeyRow
    && !in_array(strtoupper((string)$orderItemsOrderForeignKeyRow['DELETE_RULE']), ['RESTRICT', 'NO ACTION'], true)) {
    $constraintName = str_replace('`', '``', (string)$orderItemsOrderForeignKeyRow['CONSTRAINT_NAME']);
    $newConstraintName = 'fk_order_items_order_restrict';
    $updateRule = strtoupper((string)$orderItemsOrderForeignKeyRow['UPDATE_RULE']);
    if (!in_array($updateRule, ['CASCADE', 'RESTRICT', 'SET NULL', 'NO ACTION'], true)) {
        throw new RuntimeException('Quy tắc cập nhật khóa ngoại chi tiết đơn hàng không hợp lệ.');
    }
    if (!$conn->query(
        "ALTER TABLE order_items DROP FOREIGN KEY `{$constraintName}`,
         ADD CONSTRAINT `{$newConstraintName}` FOREIGN KEY (order_id) REFERENCES orders (id)
         ON DELETE RESTRICT ON UPDATE {$updateRule}"
    )) {
        error_log('Order-item foreign-key migration failed: ' . $conn->error);
        throw new RuntimeException('Không thể bảo vệ lịch sử chi tiết đơn hàng.');
    }
} elseif (!$orderItemsOrderForeignKeyRow) {
    $orphanOrderItems = $conn->query(
        'SELECT COUNT(*) AS total
         FROM order_items oi
         LEFT JOIN orders o ON o.id = oi.order_id
         WHERE o.id IS NULL'
    );
    if (!$orphanOrderItems) {
        throw new RuntimeException('Không thể kiểm tra chi tiết đơn hàng mồ côi: ' . $conn->error);
    }
    $orphanOrderItemCount = (int)($orphanOrderItems->fetch_assoc()['total'] ?? 0);
    if ($orphanOrderItemCount === 0
        && !$conn->query(
            'ALTER TABLE order_items
             ADD CONSTRAINT fk_order_items_order
             FOREIGN KEY (order_id) REFERENCES orders (id)
             ON DELETE RESTRICT ON UPDATE CASCADE'
        )) {
        error_log('Order-item foreign-key setup failed: ' . $conn->error);
        throw new RuntimeException('Không thể bảo vệ lịch sử chi tiết đơn hàng.');
    }
    if ($orphanOrderItemCount > 0) {
        error_log('Order-item foreign key was not added because ' . $orphanOrderItemCount . ' orphaned references exist.');
    }
}

$chatProductTable = $conn->query(
    "SELECT 1 FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'chat_messages'
     LIMIT 1"
);
if (!$chatProductTable) {
    throw new RuntimeException('Không thể kiểm tra bảng lịch sử chat: ' . $conn->error);
}
if ($chatProductTable->num_rows > 0) {
    $chatProductColumn = $conn->query(
        "SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'chat_messages'
           AND COLUMN_NAME = 'product_id'
         LIMIT 1"
    );
    if (!$chatProductColumn) {
        throw new RuntimeException('Không thể kiểm tra cột sản phẩm trong lịch sử chat: ' . $conn->error);
    }
    if ($chatProductColumn->num_rows > 0) {
        $chatProductIndexExists = $conn->query(
        "SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'chat_messages'
           AND INDEX_NAME = 'idx_chat_messages_product'
         LIMIT 1"
        );
        if (!$chatProductIndexExists) {
            throw new RuntimeException('Không thể kiểm tra chỉ mục lịch sử chat: ' . $conn->error);
        }
        if ($chatProductIndexExists->num_rows === 0
            && !$conn->query('ALTER TABLE chat_messages ADD INDEX idx_chat_messages_product (product_id)')) {
            error_log('Chat product index migration failed: ' . $conn->error);
            throw new RuntimeException('Không thể tạo chỉ mục lịch sử sản phẩm trong chat.');
        }

        $chatProductForeignKey = $conn->query(
            "SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'chat_messages'
               AND COLUMN_NAME = 'product_id'
               AND REFERENCED_TABLE_NAME = 'products'
             LIMIT 1"
        );
        if (!$chatProductForeignKey) {
            throw new RuntimeException('Không thể kiểm tra khóa ngoại lịch sử chat: ' . $conn->error);
        }
        if ($chatProductForeignKey->num_rows === 0) {
            $orphanChatProducts = $conn->query(
                'SELECT COUNT(*) AS total
                 FROM chat_messages cm
                 LEFT JOIN products p ON p.id = cm.product_id
                 WHERE cm.product_id IS NOT NULL AND p.id IS NULL'
            );
            if (!$orphanChatProducts) {
                throw new RuntimeException('Không thể kiểm tra dữ liệu chat liên quan đến sản phẩm: ' . $conn->error);
            }
            $orphanCount = (int)($orphanChatProducts->fetch_assoc()['total'] ?? 0);
            if ($orphanCount === 0
                && !$conn->query(
                    'ALTER TABLE chat_messages
                     ADD CONSTRAINT fk_chat_messages_product
                     FOREIGN KEY (product_id) REFERENCES products (id)
                     ON DELETE RESTRICT ON UPDATE CASCADE'
                )) {
                error_log('Chat product foreign-key migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể bảo vệ liên kết sản phẩm trong lịch sử chat.');
            }
            if ($orphanCount > 0) {
                error_log('Chat product foreign key was not added because ' . $orphanCount . ' orphaned references exist.');
            }
        }
    }
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
