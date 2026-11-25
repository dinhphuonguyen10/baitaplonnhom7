<?php

namespace App\Database;

use App\Core\Database;
use mysqli;
use mysqli_sql_exception;
use RuntimeException;

/**
 * Lớp SchemaManager - tự đồng bộ cấu trúc CSDL (bảng, cột, VIEW, FUNCTION, TRIGGER)
 * cho các database được tạo từ file SQL cũ. Chạy một lần khi khởi tạo kết nối.
 */
class SchemaManager
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

    /** Chạy toàn bộ các bước đồng bộ cấu trúc chung. */
    public function migrate(): void
    {
        $this->ensureReviewTables();
        $this->ensureStoreTable();
        $this->ensureProductColumns();
        $this->ensureWishlistTable();
        $this->ensureAddressColumns();
        $this->ensureSqlFunctions();
        $this->ensureProductSoftDelete();
        $this->ensureProductTriggers();
    }

    // ─────────────────────────── Đánh giá ───────────────────────────

    private function ensureReviewTables(): void
    {
        // Lưu phản hồi của quản trị viên riêng để không thay đổi nội dung đánh giá của khách hàng.
        $sql = "CREATE TABLE IF NOT EXISTS review_replies (
            review_id int(10) unsigned NOT NULL,
            admin_username varchar(100) NOT NULL,
            reply text NOT NULL,
            replied_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (review_id),
            CONSTRAINT fk_review_replies_review FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if (!$this->conn->query($sql)) {
            error_log('Review replies schema setup failed: ' . $this->conn->error);
        }

        if (!$this->tableExists('review_images')) {
            $sql = "CREATE TABLE review_images (
                id int(10) unsigned NOT NULL AUTO_INCREMENT,
                review_id int(10) unsigned NOT NULL,
                image_path varchar(500) NOT NULL,
                sort_order tinyint(3) unsigned NOT NULL DEFAULT 0,
                PRIMARY KEY (id),
                KEY idx_review_images_review (review_id),
                CONSTRAINT fk_review_images_review FOREIGN KEY (review_id) REFERENCES reviews (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!$this->conn->query($sql)) {
                error_log('Review images schema setup failed: ' . $this->conn->error);
            }
        }

        if (!$this->columnExists('reviews', 'edit_count')
            && !$this->conn->query('ALTER TABLE reviews ADD COLUMN edit_count TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER comment')) {
            error_log('Review edit counter schema setup failed: ' . $this->conn->error);
        }
    }

    // ─────────────────────────── Cửa hàng ───────────────────────────

    private function ensureStoreTable(): void
    {
        if (!$this->tableExists('stores')) {
            return;
        }

        $storeColumns = $this->columnNames('stores');
        $schemaChanged = false;
        $missingColumns = [
            'phone' => "ALTER TABLE stores ADD COLUMN phone VARCHAR(30) NOT NULL DEFAULT '' AFTER image",
            'hours' => "ALTER TABLE stores ADD COLUMN hours VARCHAR(100) NOT NULL DEFAULT '08:00 - 22:00' AFTER phone",
            'status' => "ALTER TABLE stores ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' AFTER hours",
        ];
        foreach ($missingColumns as $column => $alterSql) {
            if (!isset($storeColumns[$column]) && $this->conn->query($alterSql)) {
                $schemaChanged = true;
            }
        }

        // Khôi phục dữ liệu cửa hàng mặc định khi database chưa có dữ liệu.
        $countResult = $this->conn->query('SELECT COUNT(*) AS total FROM stores');
        $storeCount = $countResult ? (int) $countResult->fetch_assoc()['total'] : 0;
        if (!$schemaChanged && $storeCount !== 0) {
            return;
        }

        $defaultStores = [
            [1, 'ÉCRIN JEWELRY Cầu Giấy', '123 Xuân Thủy, Dịch Vọng, Cầu Giấy, Hà Nội', '024.1234.5678'],
            [2, 'ÉCRIN JEWELRY Hoàn Kiếm', '45 Hàng Đào, Hoàn Kiếm, Hà Nội', '024.8888.9999'],
            [3, 'ÉCRIN JEWELRY Quận 1', '89 Nguyễn Huệ, Phường Bến Nghé, Quận 1, TP. HCM', '028.1111.2222'],
            [4, 'ÉCRIN JEWELRY Quận 10', '256 Sư Vạn Hạnh, Phường 12, Quận 10, TP. HCM', '028.4444.5555'],
            [5, 'ÉCRIN JEWELRY Đà Nẵng', '12 Lê Duẩn, Hải Châu, Đà Nẵng', '0236.777.888'],
        ];
        $stmt = $this->conn->prepare(
            "INSERT INTO stores (id, name, address, phone, hours, status)
             VALUES (?, ?, ?, ?, '08:00 - 22:00', 'active')
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), address = VALUES(address), phone = VALUES(phone),
                hours = VALUES(hours), status = VALUES(status)"
        );
        if (!$stmt) {
            return;
        }
        foreach ($defaultStores as [$id, $name, $address, $phone]) {
            $stmt->bind_param('isss', $id, $name, $address, $phone);
            $stmt->execute();
        }
        $stmt->close();
    }

    // ─────────────────────────── Sản phẩm ───────────────────────────

    private function ensureProductColumns(): void
    {
        $columns = $this->columnNames('products');
        $alters = [
            'description' => 'ALTER TABLE products ADD COLUMN description TEXT NULL AFTER category_id',
            'is_featured' => 'ALTER TABLE products ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER description',
            'is_suggested' => 'ALTER TABLE products ADD COLUMN is_suggested TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured',
            'is_new' => 'ALTER TABLE products ADD COLUMN is_new TINYINT(1) NOT NULL DEFAULT 0 AFTER is_suggested',
        ];
        foreach ($alters as $column => $sql) {
            if (!isset($columns[$column])) {
                $this->conn->query($sql);
            }
        }

        // Xóa bỏ các view cũ không còn dùng để tránh nhầm lẫn
        $this->conn->query('DROP VIEW IF EXISTS view_featured_products');
        $this->conn->query('DROP VIEW IF EXISTS view_suggested_products');
        $this->conn->query('DROP VIEW IF EXISTS view_new_products');

        // VIEW view_product_details tính toán số liệu hoàn toàn bằng SQL
        $this->conn->query($this->productDetailsViewSql(false));
    }

    private function ensureProductSoftDelete(): void
    {
        // Cột Soft Delete và trạng thái sản phẩm (status, is_deleted, deleted_at, deleted_by)
        if (!$this->columnExists('products', 'status')) {
            $this->conn->query("ALTER TABLE products ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER is_new");
            $this->conn->query('ALTER TABLE products ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
            $this->conn->query('ALTER TABLE products ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER is_deleted');
            $this->conn->query('ALTER TABLE products ADD COLUMN deleted_by VARCHAR(100) NULL DEFAULT NULL AFTER deleted_at');
            $this->conn->query('ALTER TABLE products ADD INDEX idx_products_status_deleted (is_deleted, status)');
            $this->conn->query('ALTER TABLE products ADD INDEX idx_products_cat_deleted (category_id, is_deleted, status)');
            $this->conn->query("ALTER TABLE products ADD CONSTRAINT chk_products_status CHECK (status IN ('active', 'inactive', 'hidden'))");
            $this->conn->query('ALTER TABLE products ADD CONSTRAINT chk_products_is_deleted CHECK (is_deleted IN (0, 1))');
        }

        // Đảm bảo VIEW luôn có đầy đủ cột status, is_deleted, deleted_at, deleted_by
        if (!$this->columnExists('view_product_details', 'is_deleted')) {
            $this->conn->query($this->productDetailsViewSql(true));
        }
    }

    private function productDetailsViewSql(bool $withSoftDelete): string
    {
        $softDeleteColumns = $withSoftDelete
            ? "p.status,
            p.is_deleted,
            p.deleted_at,
            p.deleted_by,"
            : '';

        return "CREATE OR REPLACE VIEW view_product_details AS
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
            {$softDeleteColumns}
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
        GROUP BY p.id, c.slug, c.name";
    }

    private function ensureProductTriggers(): void
    {
        $this->conn->query('DROP TRIGGER IF EXISTS trg_products_before_insert');
        $this->conn->query("CREATE TRIGGER trg_products_before_insert BEFORE INSERT ON products FOR EACH ROW
BEGIN
    IF NEW.price_original > 0 AND NEW.price_sale < NEW.price_original THEN
        SET NEW.discount = fn_format_discount(NEW.price_sale, NEW.price_original);
    ELSE
        SET NEW.price_original = 0.00;
        SET NEW.discount = NULL;
    END IF;
END");

        $this->conn->query('DROP TRIGGER IF EXISTS trg_products_before_update');
        $this->conn->query("CREATE TRIGGER trg_products_before_update BEFORE UPDATE ON products FOR EACH ROW
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
    }

    // ─────────────────────────── Yêu thích / địa chỉ ───────────────────────────

    private function ensureWishlistTable(): void
    {
        if ($this->tableExists('wishlists')) {
            return;
        }

        $this->conn->query("CREATE TABLE IF NOT EXISTS wishlists (
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

    private function ensureAddressColumns(): void
    {
        // Giữ vị trí bản đồ cho địa chỉ đã lưu ở các database tạo trước khi hỗ trợ bản đồ.
        if (!$this->tableExists('addresses')) {
            return;
        }

        $columns = $this->columnNames('addresses');
        foreach ([
            'latitude' => 'ALTER TABLE addresses ADD COLUMN latitude DECIMAL(10,7) NULL AFTER is_default',
            'longitude' => 'ALTER TABLE addresses ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude',
        ] as $column => $sql) {
            if (!isset($columns[$column]) && !$this->conn->query($sql)) {
                error_log('Address map schema update failed: ' . $this->conn->error);
            }
        }
    }

    // ─────────────────────────── SQL Function ───────────────────────────

    private function ensureSqlFunctions(): void
    {
        // Tính toán và định dạng phần trăm giảm giá
        $this->conn->query('DROP FUNCTION IF EXISTS fn_format_discount');
        $this->conn->query("CREATE FUNCTION fn_format_discount(sale DECIMAL(12,2), orig DECIMAL(12,2))
RETURNS VARCHAR(50) DETERMINISTIC
BEGIN
    IF orig > 0 AND sale < orig THEN
        RETURN CONCAT('-', ROUND((1 - (sale / orig)) * 100), '%');
    END IF;
    RETURN NULL;
END");

        // Lọc thời gian đơn hàng theo phong cách sàn TMĐT
        $this->conn->query('DROP FUNCTION IF EXISTS fn_order_time_condition');
        $this->conn->query("CREATE FUNCTION fn_order_time_condition(order_date DATETIME, filter_time VARCHAR(20))
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
