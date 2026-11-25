<?php

namespace App\Database;

use App\Core\Database;
use mysqli;
use mysqli_sql_exception;
use RuntimeException;

/**
 * Lớp QuanLyCauTruc - tự đồng bộ cấu trúc CSDL (bảng, cột, chỉ mục, ràng buộc, VIEW, FUNCTION, TRIGGER)
 * cho các database được tạo từ file SQL cũ. Chạy một lần khi khởi tạo kết nối (Database::getInstance()).
 */
class QuanLyCauTruc
{
    private Database $db;
    private mysqli $conn;

    private static bool $chatTableReady = false;
    private static bool $orderDeadlineReady = false;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->conn = $db->connection();
    }

    /**
     * Chạy toàn bộ các bước đồng bộ cấu trúc chung:
     * đơn hàng (tiền, tồn kho, lịch sử trạng thái), đánh giá, cửa hàng, danh mục (mã, xóa mềm),
     * sản phẩm (vòng đời, SKU, khuyến mãi, lịch sử giá), VIEW view_product_details, FUNCTION, TRIGGER.
     *
     * @return bool true nếu bảng orders tồn tại
     */
    public function migrate(): bool
    {
        $conn = $this->conn;
        // Keep customer order totals and status history available on existing MySQL databases.
        $ordersTable = $conn->query("SHOW TABLES LIKE 'orders'");
        if (!$ordersTable) {
            error_log('Order schema inspection failed: ' . $conn->error);
            throw new RuntimeException('Không thể kiểm tra cấu trúc đơn hàng.');
        }
        if ($ordersTable->num_rows > 0) {
            $orderColumns = [];
            $orderColumnsResult = $conn->query('SHOW COLUMNS FROM orders');
            if (!$orderColumnsResult) {
                error_log('Order column inspection failed: ' . $conn->error);
                throw new RuntimeException('Không thể kiểm tra cấu trúc đơn hàng.');
            }
            while ($orderColumn = $orderColumnsResult->fetch_assoc()) {
                $orderColumns[$orderColumn['Field']] = true;
            }

            $orderColumnMigrations = [
                'shipping_fee' => 'ALTER TABLE orders ADD COLUMN shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER total_price',
                'discount_amount' => 'ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER shipping_fee',
                'cancelled_at' => 'ALTER TABLE orders ADD COLUMN cancelled_at DATETIME NULL DEFAULT NULL AFTER created_at',
                'cancellation_reason' => 'ALTER TABLE orders ADD COLUMN cancellation_reason VARCHAR(500) NULL DEFAULT NULL AFTER cancelled_at',
                'inventory_status' => "ALTER TABLE orders ADD COLUMN inventory_status VARCHAR(20) NOT NULL DEFAULT 'legacy' AFTER cancellation_reason",
                'inventory_reserved_at' => 'ALTER TABLE orders ADD COLUMN inventory_reserved_at DATETIME NULL DEFAULT NULL AFTER inventory_status',
                'inventory_released_at' => 'ALTER TABLE orders ADD COLUMN inventory_released_at DATETIME NULL DEFAULT NULL AFTER inventory_reserved_at',
                'expires_at' => 'ALTER TABLE orders ADD COLUMN expires_at DATETIME NULL DEFAULT NULL AFTER inventory_released_at',
            ];
            foreach ($orderColumnMigrations as $columnName => $alterSql) {
                if (!isset($orderColumns[$columnName]) && !$conn->query($alterSql)) {
                    error_log('Order column migration failed for ' . $columnName . ': ' . $conn->error);
                    throw new RuntimeException('Không thể cập nhật cấu trúc đơn hàng.');
                }
            }
            foreach ([
                'total_price' => 'ALTER TABLE orders MODIFY COLUMN total_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'shipping_fee' => 'ALTER TABLE orders MODIFY COLUMN shipping_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00',
                'discount_amount' => 'ALTER TABLE orders MODIFY COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            ] as $columnName => $alterSql) {
                $columnResult = $conn->query("SHOW COLUMNS FROM orders LIKE '{$columnName}'");
                $column = $columnResult ? $columnResult->fetch_assoc() : null;
                if (!$column) {
                    throw new RuntimeException('Không thể xác minh cột tiền đơn hàng ' . $columnName . '.');
                }
                if (strtolower((string) $column['Type']) !== 'decimal(12,2)' && !$conn->query($alterSql)) {
                    error_log('Order money type migration failed for ' . $columnName . ': ' . $conn->error);
                    throw new RuntimeException('Không thể chuẩn hóa kiểu tiền đơn hàng.');
                }
            }

            $orderIndexResult = $conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_user_created'");
            if (!$orderIndexResult) {
                error_log('Order index inspection failed: ' . $conn->error);
                throw new RuntimeException('Không thể kiểm tra chỉ mục đơn hàng.');
            }
            if ($orderIndexResult->num_rows === 0 && !$conn->query(
                'ALTER TABLE orders ADD INDEX idx_orders_user_created (user_id, created_at, id)'
            )) {
                error_log('Order list index migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể tối ưu truy vấn đơn hàng.');
            }

            $inventoryIndexResult = $conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_inventory_expiry'");
            if (!$inventoryIndexResult) {
                error_log('Order inventory index inspection failed: ' . $conn->error);
                throw new RuntimeException('Không thể kiểm tra chỉ mục tồn kho đơn hàng.');
            }
            if ($inventoryIndexResult->num_rows === 0 && !$conn->query(
                'ALTER TABLE orders
                 ADD INDEX idx_orders_inventory_expiry (inventory_status, status, payment_status, expires_at, id)'
            )) {
                error_log('Order inventory index migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể tối ưu truy vấn hết hạn tồn kho.');
            }

            $inventoryConstraint = $conn->query(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'orders'
                   AND CONSTRAINT_NAME = 'chk_orders_inventory_status'
                   AND CONSTRAINT_TYPE = 'CHECK'"
            );
            if (!$inventoryConstraint) {
                error_log('Order inventory constraint inspection failed: ' . $conn->error);
                throw new RuntimeException('Không thể kiểm tra trạng thái tồn kho đơn hàng.');
            }
            if ($inventoryConstraint->num_rows === 0 && !$conn->query(
                "ALTER TABLE orders
                 ADD CONSTRAINT chk_orders_inventory_status
                 CHECK (inventory_status IN ('legacy', 'reserved', 'released', 'committed'))"
            )) {
                error_log('Order inventory constraint migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể bảo vệ trạng thái tồn kho đơn hàng.');
            }

            $orderAmountConstraint = $conn->query(
                "SELECT CONSTRAINT_NAME
                 FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'orders'
                   AND CONSTRAINT_NAME = 'chk_orders_amounts'
                   AND CONSTRAINT_TYPE = 'CHECK'"
            );
            if (!$orderAmountConstraint) {
                error_log('Order amount constraint inspection failed: ' . $conn->error);
                throw new RuntimeException('Không thể kiểm tra ràng buộc giá trị đơn hàng.');
            }
            if ($orderAmountConstraint->num_rows === 0 && !$conn->query(
                'ALTER TABLE orders
                 ADD CONSTRAINT chk_orders_amounts
                 CHECK (total_price >= 0 AND shipping_fee >= 0 AND discount_amount >= 0)'
            )) {
                error_log('Order amount constraint migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể bảo vệ giá trị tiền của đơn hàng.');
            }

            $orderStatusHistoryTableSql = "CREATE TABLE IF NOT EXISTS order_status_history (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                order_id int(10) unsigned NOT NULL,
                old_status varchar(50) DEFAULT NULL,
                new_status varchar(50) NOT NULL,
                status varchar(50) NOT NULL,
                note varchar(500) NOT NULL DEFAULT '',
                actor_name varchar(100) NOT NULL DEFAULT 'Hệ thống',
                created_at datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (id),
                KEY idx_order_status_history_order_created (order_id, created_at, id),
                CONSTRAINT fk_order_status_history_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!$conn->query($orderStatusHistoryTableSql)) {
                error_log('Order status history schema setup failed: ' . $conn->error);
                throw new RuntimeException('Không thể tạo lịch sử trạng thái đơn hàng.');
            }

            $orderHistoryColumnsResult = $conn->query('SHOW COLUMNS FROM order_status_history');
            if (!$orderHistoryColumnsResult) {
                error_log('Order status history inspection failed: ' . $conn->error);
                throw new RuntimeException('Không thể kiểm tra lịch sử trạng thái đơn hàng.');
            }
            $orderHistoryColumns = [];
            while ($column = $orderHistoryColumnsResult->fetch_assoc()) {
                $orderHistoryColumns[$column['Field']] = $column;
            }
            $orderHistoryDataNeedsMigration = !isset($orderHistoryColumns['new_status'])
                || $orderHistoryColumns['new_status']['Null'] === 'YES';
            $orderHistorySchemaChanged = false;
            foreach ([
                'old_status' => 'ALTER TABLE order_status_history ADD COLUMN old_status VARCHAR(50) NULL AFTER order_id',
                'new_status' => 'ALTER TABLE order_status_history ADD COLUMN new_status VARCHAR(50) NULL AFTER old_status',
            ] as $columnName => $alterSql) {
                if (!isset($orderHistoryColumns[$columnName])) {
                    if (!$conn->query($alterSql)) {
                        error_log('Order status history column migration failed for ' . $columnName . ': ' . $conn->error);
                        throw new RuntimeException('Không thể cập nhật lịch sử trạng thái đơn hàng.');
                    }
                    $orderHistorySchemaChanged = true;
                }
            }

            if ($orderHistoryDataNeedsMigration && !$conn->query(
                'UPDATE order_status_history SET new_status = status WHERE new_status IS NULL'
            )) {
                error_log('Order status history value migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể bảo toàn lịch sử trạng thái cũ.');
            }
            if (($orderHistorySchemaChanged || $orderHistoryDataNeedsMigration) && !$conn->query(
                "UPDATE order_status_history current_event
                 LEFT JOIN order_status_history prior_event
                   ON prior_event.order_id = current_event.order_id
                  AND (
                      prior_event.created_at < current_event.created_at
                      OR (prior_event.created_at = current_event.created_at AND prior_event.id < current_event.id)
                  )
                 LEFT JOIN order_status_history newer_prior
                   ON newer_prior.order_id = prior_event.order_id
                  AND (
                      newer_prior.created_at < current_event.created_at
                      OR (newer_prior.created_at = current_event.created_at AND newer_prior.id < current_event.id)
                  )
                  AND (
                      newer_prior.created_at > prior_event.created_at
                      OR (newer_prior.created_at = prior_event.created_at AND newer_prior.id > prior_event.id)
                  )
                 SET current_event.old_status = prior_event.new_status
                 WHERE current_event.old_status IS NULL
                   AND prior_event.id IS NOT NULL
                   AND newer_prior.id IS NULL"
            )) {
                error_log('Order status history transition migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể chuyển đổi lịch sử trạng thái cũ.');
            }

            $orderHistoryColumnsResult = $conn->query('SHOW COLUMNS FROM order_status_history LIKE "new_status"');
            $newStatusColumn = $orderHistoryColumnsResult ? $orderHistoryColumnsResult->fetch_assoc() : null;
            if (!$newStatusColumn) {
                error_log('Order status history new-status column verification failed: ' . $conn->error);
                throw new RuntimeException('Không thể xác minh lịch sử trạng thái đơn hàng.');
            }
            if ($newStatusColumn['Null'] === 'YES' && !$conn->query(
                'ALTER TABLE order_status_history MODIFY COLUMN new_status VARCHAR(50) NOT NULL'
            )) {
                error_log('Order status history new-status constraint migration failed: ' . $conn->error);
                throw new RuntimeException('Không thể bảo vệ lịch sử trạng thái đơn hàng.');
            }
        }

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

        // Keep category lifecycle and management fields compatible with existing MySQL databases.
        $categoryColumns = [];
        $categoryColumnsResult = $conn->query('SHOW COLUMNS FROM categories');
        if (!$categoryColumnsResult) {
            error_log('Category schema inspection failed: ' . $conn->error);
            throw new RuntimeException('Không thể kiểm tra cấu trúc danh mục.');
        }
        while ($categoryColumn = $categoryColumnsResult->fetch_assoc()) {
            $categoryColumns[$categoryColumn['Field']] = $categoryColumn;
        }

        $categoryColumnMigrations = [
            'code' => 'ALTER TABLE categories ADD COLUMN code VARCHAR(32) NULL AFTER id',
            'description' => 'ALTER TABLE categories ADD COLUMN description TEXT NULL AFTER slug',
            'image' => 'ALTER TABLE categories ADD COLUMN image VARCHAR(500) NULL AFTER description',
            'status' => "ALTER TABLE categories ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER image",
            'is_deleted' => 'ALTER TABLE categories ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status',
            'deleted_at' => 'ALTER TABLE categories ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER is_deleted',
            'deleted_by' => 'ALTER TABLE categories ADD COLUMN deleted_by VARCHAR(100) NULL DEFAULT NULL AFTER deleted_at',
            'created_at' => 'ALTER TABLE categories ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'ALTER TABLE categories ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
        foreach ($categoryColumnMigrations as $columnName => $alterSql) {
            if (!isset($categoryColumns[$columnName])) {
                if (!$conn->query($alterSql)) {
                    error_log('Category column migration failed for ' . $columnName . ': ' . $conn->error);
                    throw new RuntimeException('Không thể cập nhật cấu trúc quản lý danh mục.');
                }
                $categoryColumns[$columnName] = ['Null' => $columnName === 'code' ? 'YES' : 'NO'];
            }
        }

        $invalidCategoryNames = $conn->query(
            "SELECT id
             FROM categories
             WHERE TRIM(name) = ''
             LIMIT 1"
        );
        if (!$invalidCategoryNames) {
            throw new RuntimeException('Không thể kiểm tra tên danh mục: ' . $conn->error);
        }
        if ($invalidCategoryNames->num_rows > 0) {
            throw new RuntimeException('Có danh mục chưa có tên hợp lệ. Hãy sửa tên trước khi tiếp tục.');
        }
        $duplicateNormalizedCategoryNames = $conn->query(
            "SELECT LOWER(TRIM(name)) AS normalized_name
             FROM categories
             GROUP BY LOWER(TRIM(name))
             HAVING COUNT(*) > 1
             LIMIT 1"
        );
        if (!$duplicateNormalizedCategoryNames) {
            throw new RuntimeException('Không thể kiểm tra tên danh mục trùng lặp: ' . $conn->error);
        }
        if ($duplicateNormalizedCategoryNames->num_rows > 0) {
            throw new RuntimeException('Có danh mục trùng tên sau khi bỏ khoảng trắng thừa. Hãy xử lý trước khi tiếp tục.');
        }
        if (!$conn->query('UPDATE categories SET name = TRIM(name) WHERE name <> TRIM(name)')) {
            error_log('Category name normalization failed: ' . $conn->error);
            throw new RuntimeException('Không thể chuẩn hóa tên danh mục.');
        }
        if (!$conn->query(
            "UPDATE categories
             SET code = CONCAT('DM-', LPAD(id, 6, '0'))
             WHERE code IS NULL OR TRIM(code) = ''"
        )) {
            error_log('Category code backfill failed: ' . $conn->error);
            throw new RuntimeException('Không thể tạo mã cho danh mục hiện có.');
        }
        $duplicateCategoryCodes = $conn->query(
            "SELECT code
             FROM categories
             GROUP BY code
             HAVING COUNT(*) > 1
             LIMIT 1"
        );
        if (!$duplicateCategoryCodes) {
            throw new RuntimeException('Không thể kiểm tra mã danh mục trùng lặp: ' . $conn->error);
        }
        if ($duplicateCategoryCodes->num_rows > 0) {
            throw new RuntimeException('Database đang có mã danh mục trùng nhau. Hãy xử lý trước khi tiếp tục.');
        }
        if (!$conn->query(
            "UPDATE categories
             SET deleted_at = COALESCE(deleted_at, NOW())
             WHERE is_deleted = 1 AND deleted_at IS NULL"
        ) || !$conn->query(
            "UPDATE categories
             SET deleted_at = NULL, deleted_by = NULL
             WHERE is_deleted = 0 AND (deleted_at IS NOT NULL OR deleted_by IS NOT NULL)"
        )) {
            error_log('Category soft-delete metadata normalization failed: ' . $conn->error);
            throw new RuntimeException('Không thể chuẩn hóa thông tin xóa mềm danh mục.');
        }

        $categoryCodeColumnResult = $conn->query("SHOW COLUMNS FROM categories LIKE 'code'");
        $categoryCodeColumn = $categoryCodeColumnResult ? $categoryCodeColumnResult->fetch_assoc() : null;
        if (!$categoryCodeColumnResult) {
            throw new RuntimeException('Không thể kiểm tra mã danh mục: ' . $conn->error);
        }
        if ($categoryCodeColumn && $categoryCodeColumn['Null'] === 'YES'
            && !$conn->query('ALTER TABLE categories MODIFY code VARCHAR(32) NOT NULL')) {
            error_log('Category code nullability migration failed: ' . $conn->error);
            throw new RuntimeException('Không thể bắt buộc mã danh mục.');
        }

        $categoryIndexes = [];
        $categoryIndexResult = $conn->query('SHOW INDEX FROM categories');
        if (!$categoryIndexResult) {
            throw new RuntimeException('Không thể kiểm tra chỉ mục danh mục: ' . $conn->error);
        }
        while ($categoryIndex = $categoryIndexResult->fetch_assoc()) {
            $categoryIndexes[$categoryIndex['Key_name']] = true;
        }
        if (!isset($categoryIndexes['uq_categories_code'])
            && !$conn->query('ALTER TABLE categories ADD UNIQUE INDEX uq_categories_code (code)')) {
            error_log('Category code unique index migration failed: ' . $conn->error);
            throw new RuntimeException('Không thể tạo ràng buộc mã danh mục duy nhất.');
        }
        if (!isset($categoryIndexes['idx_categories_public'])
            && !$conn->query('ALTER TABLE categories ADD INDEX idx_categories_public (is_deleted, status)')) {
            error_log('Category public index migration failed: ' . $conn->error);
            throw new RuntimeException('Không thể tạo chỉ mục trạng thái danh mục.');
        }

        $categoryChecks = [];
        $categoryChecksResult = $conn->query(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'categories'
               AND CONSTRAINT_TYPE = 'CHECK'"
        );
        if (!$categoryChecksResult) {
            throw new RuntimeException('Không thể kiểm tra ràng buộc danh mục: ' . $conn->error);
        }
        while ($categoryCheck = $categoryChecksResult->fetch_assoc()) {
            $categoryChecks[$categoryCheck['CONSTRAINT_NAME']] = true;
        }
        foreach ([
            'chk_categories_name_nonempty' => 'ALTER TABLE categories ADD CONSTRAINT chk_categories_name_nonempty CHECK (CHAR_LENGTH(TRIM(name)) > 0)',
            'chk_categories_code_nonempty' => 'ALTER TABLE categories ADD CONSTRAINT chk_categories_code_nonempty CHECK (CHAR_LENGTH(TRIM(code)) > 0)',
            'chk_categories_status' => "ALTER TABLE categories ADD CONSTRAINT chk_categories_status CHECK (status IN ('active', 'inactive'))",
            'chk_categories_is_deleted' => 'ALTER TABLE categories ADD CONSTRAINT chk_categories_is_deleted CHECK (is_deleted IN (0, 1))',
            'chk_categories_deleted_metadata' => 'ALTER TABLE categories ADD CONSTRAINT chk_categories_deleted_metadata CHECK ((is_deleted = 0 AND deleted_at IS NULL AND deleted_by IS NULL) OR (is_deleted = 1 AND deleted_at IS NOT NULL))',
        ] as $constraintName => $alterSql) {
            if (!isset($categoryChecks[$constraintName]) && !$conn->query($alterSql)) {
                error_log('Category check constraint migration failed for ' . $constraintName . ': ' . $conn->error);
                throw new RuntimeException('Không thể tạo ràng buộc dữ liệu danh mục.');
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
            'created_at' => 'ALTER TABLE products ADD COLUMN created_at DATETIME NULL DEFAULT NULL AFTER deleted_by',
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

        $productPricingColumns = [
            'sku' => 'ALTER TABLE products ADD COLUMN sku VARCHAR(100) NULL AFTER name',
            'promotion_ends_at' => 'ALTER TABLE products ADD COLUMN promotion_ends_at DATETIME NULL DEFAULT NULL AFTER price_original',
        ];
        foreach ($productPricingColumns as $columnName => $alterSql) {
            if (!isset($prodColumns[$columnName])) {
                if (!$conn->query($alterSql)) {
                    error_log('Product pricing column migration failed for ' . $columnName . ': ' . $conn->error);
                    throw new RuntimeException('Không thể cập nhật cấu trúc giá và mã sản phẩm.');
                }
                $prodColumns[$columnName] = true;
                $needUpdateView = true;
            }
        }
        foreach ([
            'price_sale' => 'ALTER TABLE products MODIFY COLUMN price_sale DECIMAL(12,2) NOT NULL',
            'price_original' => 'ALTER TABLE products MODIFY COLUMN price_original DECIMAL(12,2) NOT NULL DEFAULT 0.00',
        ] as $columnName => $alterSql) {
            $columnResult = $conn->query("SHOW COLUMNS FROM products LIKE '{$columnName}'");
            $column = $columnResult ? $columnResult->fetch_assoc() : null;
            if (!$column) {
                throw new RuntimeException('Không thể xác minh cột giá sản phẩm ' . $columnName . '.');
            }
            if (strtolower((string) $column['Type']) !== 'decimal(12,2)' && !$conn->query($alterSql)) {
                error_log('Product money type migration failed for ' . $columnName . ': ' . $conn->error);
                throw new RuntimeException('Không thể chuẩn hóa kiểu tiền sản phẩm.');
            }
        }

        // Remove legacy price triggers before normalizing data; they used to rewrite price_original.
        if (!$conn->query('DROP TRIGGER IF EXISTS trg_products_before_insert')
            || !$conn->query('DROP TRIGGER IF EXISTS trg_products_before_update')) {
            error_log('Legacy product price trigger removal failed: ' . $conn->error);
            throw new RuntimeException('Không thể chuẩn hóa quy tắc giá sản phẩm.');
        }
        if (!$conn->query(
            "UPDATE products
             SET sku = CONCAT('LEGACY-', LPAD(id, 10, '0'))
             WHERE sku IS NULL OR TRIM(sku) = ''"
        )) {
            error_log('Product SKU backfill failed: ' . $conn->error);
            throw new RuntimeException('Không thể tạo mã SKU cho sản phẩm hiện có.');
        }
        if (!$conn->query(
            'UPDATE products
             SET price_original = price_sale
             WHERE price_original <= 0 AND price_sale >= 0'
        )) {
            error_log('Product regular-price normalization failed: ' . $conn->error);
            throw new RuntimeException('Không thể chuẩn hóa giá sản phẩm hiện có.');
        }

        $duplicateSku = $conn->query(
            "SELECT sku
             FROM products
             GROUP BY sku
             HAVING COUNT(*) > 1
             LIMIT 1"
        );
        if (!$duplicateSku) {
            throw new RuntimeException('Không thể kiểm tra mã SKU trùng lặp: ' . $conn->error);
        }
        if ($duplicateSku->num_rows > 0) {
            throw new RuntimeException('Database đang có SKU trùng nhau. Hãy xử lý SKU trùng trước khi tiếp tục.');
        }

        $skuColumnResult = $conn->query("SHOW COLUMNS FROM products LIKE 'sku'");
        if (!$skuColumnResult) {
            throw new RuntimeException('Không thể kiểm tra cấu trúc mã SKU: ' . $conn->error);
        }
        $skuColumn = $skuColumnResult->fetch_assoc();
        if ($skuColumn && $skuColumn['Null'] === 'YES'
            && !$conn->query('ALTER TABLE products MODIFY sku VARCHAR(100) NOT NULL')) {
            error_log('Product SKU nullability migration failed: ' . $conn->error);
            throw new RuntimeException('Không thể bắt buộc SKU cho sản phẩm.');
        }

        $invalidProductPrices = $conn->query(
            'SELECT COUNT(*) AS total
             FROM products
             WHERE price_sale < 0 OR price_original < 0 OR price_sale > price_original'
        );
        if (!$invalidProductPrices) {
            throw new RuntimeException('Không thể kiểm tra giá sản phẩm: ' . $conn->error);
        }
        if ((int)($invalidProductPrices->fetch_assoc()['total'] ?? 0) > 0) {
            throw new RuntimeException('Có sản phẩm đang có giá bán không hợp lệ. Hãy kiểm tra dữ liệu giá trước khi tiếp tục.');
        }

        $productIndexes = [];
        $productIndexResult = $conn->query('SHOW INDEX FROM products');
        if (!$productIndexResult) {
            throw new RuntimeException('Không thể kiểm tra chỉ mục bảng sản phẩm: ' . $conn->error);
        }
        while ($productIndexRow = $productIndexResult->fetch_assoc()) {
            $productIndexes[$productIndexRow['Key_name']] = true;
        }
        if (!isset($productIndexes['uq_products_sku']) && !$conn->query('ALTER TABLE products ADD UNIQUE INDEX uq_products_sku (sku)')) {
            error_log('Product SKU unique index migration failed: ' . $conn->error);
            throw new RuntimeException('Không thể tạo ràng buộc SKU duy nhất.');
        }
        foreach ([
            'idx_products_status_deleted' => 'ALTER TABLE products ADD INDEX idx_products_status_deleted (is_deleted, status)',
            'idx_products_cat_deleted' => 'ALTER TABLE products ADD INDEX idx_products_cat_deleted (category_id, is_deleted, status)',
            'idx_products_deleted_at' => 'ALTER TABLE products ADD INDEX idx_products_deleted_at (is_deleted, deleted_at)',
            'idx_products_public_created' => 'ALTER TABLE products ADD INDEX idx_products_public_created (is_deleted, status, created_at, id)',
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
            'chk_products_sku_nonempty' => "ALTER TABLE products ADD CONSTRAINT chk_products_sku_nonempty CHECK (CHAR_LENGTH(TRIM(sku)) > 0)",
            'chk_products_name_nonempty' => "ALTER TABLE products ADD CONSTRAINT chk_products_name_nonempty CHECK (CHAR_LENGTH(TRIM(name)) > 0)",
            'chk_products_pricing' => 'ALTER TABLE products ADD CONSTRAINT chk_products_pricing CHECK (price_sale >= 0 AND price_original >= 0 AND price_sale <= price_original)',
            'chk_products_stock_nonnegative' => 'ALTER TABLE products ADD CONSTRAINT chk_products_stock_nonnegative CHECK (stock >= 0)',
            'chk_products_promotion_end' => 'ALTER TABLE products ADD CONSTRAINT chk_products_promotion_end CHECK (promotion_ends_at IS NULL OR price_sale < price_original)',
        ] as $constraintName => $alterSql) {
            if (!isset($productChecks[$constraintName]) && !$conn->query($alterSql)) {
                error_log('Product lifecycle check migration failed for ' . $constraintName . ': ' . $conn->error);
                throw new RuntimeException('Không thể tạo ràng buộc trạng thái sản phẩm.');
            }
        }

        $priceHistoryTableSql = "CREATE TABLE IF NOT EXISTS product_price_history (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            product_id int(10) unsigned NOT NULL,
            old_price decimal(12,2) DEFAULT NULL,
            new_price decimal(12,2) NOT NULL,
            old_regular_price decimal(12,2) DEFAULT NULL,
            new_regular_price decimal(12,2) NOT NULL,
            old_promotion_price decimal(12,2) DEFAULT NULL,
            new_promotion_price decimal(12,2) DEFAULT NULL,
            old_promotion_ends_at datetime DEFAULT NULL,
            new_promotion_ends_at datetime DEFAULT NULL,
            changed_at datetime NOT NULL DEFAULT current_timestamp(),
            changed_by varchar(100) NOT NULL,
            PRIMARY KEY (id),
            KEY idx_product_price_history_product_time (product_id, changed_at),
            CONSTRAINT fk_product_price_history_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$conn->query($priceHistoryTableSql)) {
            error_log('Product price history table setup failed: ' . $conn->error);
            throw new RuntimeException('Không thể khởi tạo lịch sử giá sản phẩm.');
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
                    p.sku,
                    p.name,
                    CASE
                        WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                        THEN p.price_original
                        ELSE p.price_sale
                    END AS price_sale,
                    p.price_original,
                    CASE
                        WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                        THEN NULL
                        WHEN p.price_sale < p.price_original
                        THEN p.price_sale
                        ELSE NULL
                    END AS promotion_price,
                    p.promotion_ends_at,
                    CASE
                        WHEN (
                            CASE
                                WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                                THEN p.price_original
                                ELSE p.price_sale
                            END
                        ) < p.price_original AND p.price_original > 0
                        THEN CONCAT(
                            '-',
                            ROUND(
                                (p.price_original - (
                                    CASE
                                        WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                                        THEN p.price_original
                                        ELSE p.price_sale
                                    END
                                )) / p.price_original * 100
                            ),
                            '%'
                        )
                        ELSE NULL
                    END AS discount,
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
                    c.status AS category_status,
                    c.is_deleted AS category_is_deleted,
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
                GROUP BY p.id, c.slug, c.name, c.status, c.is_deleted");
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

        $orderItemColumnsResult = $conn->query('SHOW COLUMNS FROM order_items');
        if (!$orderItemColumnsResult) {
            throw new RuntimeException('Không thể kiểm tra cấu trúc chi tiết đơn hàng.');
        }
        $orderItemColumns = [];
        while ($column = $orderItemColumnsResult->fetch_assoc()) {
            $orderItemColumns[$column['Field']] = $column;
        }
        $orderItemNeedsAmountBackfill = false;
        foreach (['original_price', 'discount_amount', 'line_total'] as $amountColumn) {
            if (!isset($orderItemColumns[$amountColumn]) || $orderItemColumns[$amountColumn]['Null'] === 'YES') {
                $orderItemNeedsAmountBackfill = true;
            }
        }
        $orderItemColumnMigrations = [
            'product_sku' => 'ALTER TABLE order_items ADD COLUMN product_sku VARCHAR(100) NULL DEFAULT NULL AFTER product_id',
            'attributes_snapshot' => "ALTER TABLE order_items ADD COLUMN attributes_snapshot VARCHAR(500) NOT NULL DEFAULT '' AFTER product_sku",
            'category_name_snapshot' => "ALTER TABLE order_items ADD COLUMN category_name_snapshot VARCHAR(255) NOT NULL DEFAULT '' AFTER attributes_snapshot",
            'original_price' => 'ALTER TABLE order_items ADD COLUMN original_price DECIMAL(12,2) NULL DEFAULT NULL AFTER price',
            'discount_amount' => 'ALTER TABLE order_items ADD COLUMN discount_amount DECIMAL(12,2) NULL DEFAULT NULL AFTER original_price',
            'line_total' => 'ALTER TABLE order_items ADD COLUMN line_total DECIMAL(12,2) NULL DEFAULT NULL AFTER discount_amount',
        ];
        foreach ($orderItemColumnMigrations as $columnName => $alterSql) {
            if (!isset($orderItemColumns[$columnName]) && !$conn->query($alterSql)) {
                error_log('Order-item snapshot migration failed for ' . $columnName . ': ' . $conn->error);
                throw new RuntimeException('Không thể cập nhật cấu trúc snapshot chi tiết đơn hàng.');
            }
        }

        if ($orderItemNeedsAmountBackfill) {
            $orderItemAmountBackfill = $conn->query(
                'UPDATE order_items
                 SET original_price = COALESCE(original_price, price),
                     discount_amount = COALESCE(discount_amount, 0.00),
                     line_total = COALESCE(line_total, price * quantity)
                 WHERE original_price IS NULL OR discount_amount IS NULL OR line_total IS NULL'
            );
            if (!$orderItemAmountBackfill) {
                error_log('Order-item amount backfill failed: ' . $conn->error);
                throw new RuntimeException('Không thể bảo toàn giá trị chi tiết đơn hàng cũ.');
            }
        }

        foreach ([
            'price' => 'ALTER TABLE order_items MODIFY COLUMN price DECIMAL(12,2) NOT NULL',
            'original_price' => 'ALTER TABLE order_items MODIFY COLUMN original_price DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'discount_amount' => 'ALTER TABLE order_items MODIFY COLUMN discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00',
            'line_total' => 'ALTER TABLE order_items MODIFY COLUMN line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00',
        ] as $columnName => $alterSql) {
            $columnResult = $conn->query("SHOW COLUMNS FROM order_items LIKE '{$columnName}'");
            $column = $columnResult ? $columnResult->fetch_assoc() : null;
            if (!$column) {
                throw new RuntimeException('Không thể xác minh cột tiền ' . $columnName . ' của chi tiết đơn hàng.');
            }
            $needsAmountTypeMigration = $column['Null'] === 'YES'
                || strtolower((string) $column['Type']) !== 'decimal(12,2)';
            if ($needsAmountTypeMigration && !$conn->query($alterSql)) {
                error_log('Order-item amount constraint migration failed for ' . $columnName . ': ' . $conn->error);
                throw new RuntimeException('Không thể bảo vệ giá trị tiền của chi tiết đơn hàng.');
            }
        }

        $orderItemAmountConstraint = $conn->query(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'order_items'
               AND CONSTRAINT_NAME = 'chk_order_items_snapshot_amounts'
               AND CONSTRAINT_TYPE = 'CHECK'"
        );
        if (!$orderItemAmountConstraint) {
            throw new RuntimeException('Không thể kiểm tra ràng buộc tiền chi tiết đơn hàng.');
        }
        if ($orderItemAmountConstraint->num_rows === 0 && !$conn->query(
            'ALTER TABLE order_items
             ADD CONSTRAINT chk_order_items_snapshot_amounts
             CHECK (
                 quantity > 0
                 AND price >= 0
                 AND original_price >= price
                 AND discount_amount >= 0
                 AND line_total >= 0
                 AND line_total = price * quantity
                 AND discount_amount = (original_price - price) * quantity
             )'
        )) {
            error_log('Order-item amount constraint setup failed: ' . $conn->error);
            throw new RuntimeException('Không thể bảo vệ tính nhất quán tiền chi tiết đơn hàng.');
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
            IF NEW.price_sale < NEW.price_original THEN
                SET NEW.discount = fn_format_discount(NEW.price_sale, NEW.price_original);
            ELSE
                SET NEW.discount = NULL;
            END IF;
        END");

        $conn->query("DROP TRIGGER IF EXISTS trg_products_before_update");
        $conn->query("CREATE TRIGGER trg_products_before_update BEFORE UPDATE ON products FOR EACH ROW
        BEGIN
            IF NEW.price_sale < NEW.price_original THEN
                SET NEW.discount = fn_format_discount(NEW.price_sale, NEW.price_original);
            ELSE
                SET NEW.discount = NULL;
            END IF;
        END");

        $this->ensureUserProfileColumns();

        return $ordersTable->num_rows > 0;
    }

    /** Đảm bảo bảng users có cột họ tên khách hàng (dùng cho trang hồ sơ). */
    private function ensureUserProfileColumns(): void
    {
        if (!$this->columnExists('users', 'full_name')
            && !$this->conn->query('ALTER TABLE users ADD COLUMN full_name VARCHAR(150) NULL DEFAULT NULL AFTER username')) {
            error_log('User full_name schema update failed: ' . $this->conn->error);
        }
    }

    // ─────────────────────────── Chat ───────────────────────────

    /**
     * Đảm bảo bảng chat_messages tồn tại và có đủ cột (tệp đính kèm, trạng thái đọc...).
     */
    public function ensureChatTable(): bool
    {
        if (self::$chatTableReady) {
            return true;
        }

        $createTableSql = "CREATE TABLE IF NOT EXISTS chat_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            username VARCHAR(100) NOT NULL,
            sender ENUM('customer', 'admin') NOT NULL DEFAULT 'customer',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text',
            message LONGTEXT NOT NULL,
            product_id INT UNSIGNED NULL,
            attachment_path VARCHAR(255) NULL,
            attachment_name VARCHAR(255) NULL,
            attachment_mime VARCHAR(127) NULL,
            attachment_size INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_chat_messages_user_created (user_id, created_at),
            CONSTRAINT fk_chat_messages_user
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

        $tableResult = $this->conn->query("SELECT COUNT(*) AS table_exists
                                     FROM information_schema.tables
                                     WHERE table_schema = DATABASE() AND table_name = 'chat_messages'");
        if (!$tableResult) {
            error_log('Unable to check chat_messages table: ' . $this->conn->error);
            return false;
        }
        $tableExists = (int) ($tableResult->fetch_assoc()['table_exists'] ?? 0) > 0;
        if (!$tableExists && !$this->conn->query($createTableSql)) {
            error_log('Unable to create chat_messages table: ' . $this->conn->error);
            return false;
        }

        $columnsResult = $this->conn->query('SHOW COLUMNS FROM chat_messages');
        if (!$columnsResult) {
            error_log('Unable to inspect chat_messages columns: ' . $this->conn->error);
            return false;
        }
        $columns = [];
        while ($column = $columnsResult->fetch_assoc()) {
            $columns[$column['Field']] = strtolower((string) $column['Type']);
        }

        $schemaUpdates = [];
        if (isset($columns['message']) && strpos($columns['message'], 'longtext') === false) {
            $schemaUpdates[] = 'ALTER TABLE chat_messages MODIFY COLUMN message LONGTEXT NOT NULL';
        }
        if (!isset($columns['message_type'])) {
            $schemaUpdates[] = "ALTER TABLE chat_messages ADD COLUMN message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text'";
        } elseif (strpos($columns['message_type'], "'file'") === false) {
            $schemaUpdates[] = "ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text', 'image', 'file') NOT NULL DEFAULT 'text'";
        }
        if (!isset($columns['is_read'])) {
            $schemaUpdates[] = 'ALTER TABLE chat_messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0';
            $schemaUpdates[] = "UPDATE chat_messages incoming
                                JOIN chat_messages answer
                                  ON answer.user_id = incoming.user_id
                                 AND answer.sender = 'admin'
                                 AND answer.id > incoming.id
                                SET incoming.is_read = 1
                                WHERE incoming.sender = 'customer'";
        }

        $attachmentColumns = [
            'product_id' => 'INT UNSIGNED NULL',
            'attachment_path' => 'VARCHAR(255) NULL',
            'attachment_name' => 'VARCHAR(255) NULL',
            'attachment_mime' => 'VARCHAR(127) NULL',
            'attachment_size' => 'INT UNSIGNED NULL',
        ];
        $addColumns = [];
        foreach ($attachmentColumns as $name => $definition) {
            if (!isset($columns[$name])) {
                $addColumns[] = 'ADD COLUMN ' . $name . ' ' . $definition;
            }
        }
        if ($addColumns) {
            $schemaUpdates[] = 'ALTER TABLE chat_messages ' . implode(', ', $addColumns);
        }

        foreach ($schemaUpdates as $sql) {
            if (!$this->conn->query($sql)) {
                error_log('Unable to update chat_messages schema: ' . $this->conn->error);
                return false;
            }
        }

        self::$chatTableReady = true;

        return true;
    }

    // ─────────────────────────── Đơn hàng ───────────────────────────

    /**
     * Đảm bảo bảng orders có cột payment_deadline_at và chỉ mục tra cứu đơn VietQR quá hạn.
     */
    public function ensureOrderPaymentDeadline(): void
    {
        if (self::$orderDeadlineReady) {
            return;
        }

        $column = $this->conn->query("SHOW COLUMNS FROM orders LIKE 'payment_deadline_at'");
        if (!$column) {
            throw new RuntimeException('Không thể kiểm tra cấu trúc thời hạn thanh toán.');
        }
        if ($column->num_rows === 0) {
            try {
                $this->conn->query(
                    'ALTER TABLE orders
                     ADD COLUMN payment_deadline_at DATETIME NULL DEFAULT NULL AFTER payment_status'
                );
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1060) {
                    throw $e;
                }
            }
        }

        $index = $this->conn->query("SHOW INDEX FROM orders WHERE Key_name = 'idx_orders_qr_payment_deadline'");
        if (!$index) {
            throw new RuntimeException('Không thể kiểm tra chỉ mục thời hạn thanh toán.');
        }
        if ($index->num_rows === 0) {
            try {
                $this->conn->query(
                    'ALTER TABLE orders
                     ADD INDEX idx_orders_qr_payment_deadline
                     (payment_method, payment_status, payment_deadline_at)'
                );
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() !== 1061) {
                    throw $e;
                }
            }
        }

        self::$orderDeadlineReady = true;
    }

    /** Đảm bảo bảng orders có cột email (dùng khi đặt hàng). */
    public function ensureOrderEmailColumn(): void
    {
        $column = $this->conn->query("SHOW COLUMNS FROM orders LIKE 'email'");
        if (!$column) {
            throw new RuntimeException('Không thể kiểm tra cấu trúc bảng đơn hàng.');
        }
        if ($column->num_rows === 0 && !$this->conn->query(
            "ALTER TABLE orders ADD COLUMN email VARCHAR(254) NOT NULL DEFAULT '' AFTER phone"
        )) {
            throw new RuntimeException('Không thể cập nhật cấu trúc lưu email đơn hàng.');
        }
    }

    // ─────────────────────────── Tiện ích ───────────────────────────

    private function tableExists(string $table): bool
    {
        $result = $this->conn->query("SHOW TABLES LIKE '" . $this->conn->real_escape_string($table) . "'");

        return $result && $result->num_rows > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->conn->query(
            'SHOW COLUMNS FROM `' . str_replace('`', '', $table) . "` LIKE '" . $this->conn->real_escape_string($column) . "'"
        );

        return $result && $result->num_rows > 0;
    }

    /** @return array<string, true> */
    private function columnNames(string $table): array
    {
        $columns = [];
        $result = $this->conn->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        if ($result) {
            while ($column = $result->fetch_assoc()) {
                $columns[$column['Field']] = true;
            }
        }

        return $columns;
    }
}
