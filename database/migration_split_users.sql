-- =====================================================================
-- SCRIPT MIGRATION: Tách bảng users thành users (khách hàng) + admins (nội bộ)
-- =====================================================================
-- CẢNH BÁO: Backup CSDL trước khi chạy script này!
-- Chạy trên database: baitaplon
-- =====================================================================

USE baitaplon;

-- =====================================================================
-- BƯỚC 1: Tạo bảng admins (tài khoản nội bộ: staff, manager)
-- =====================================================================
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('staff', 'manager') NOT NULL DEFAULT 'staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- BƯỚC 2: Di chuyển tài khoản nội bộ sang bảng admins
-- (manager, staff — bỏ qua 'admin' cũ → chuyển thành 'manager')
-- =====================================================================
INSERT INTO admins (username, password, role, created_at)
SELECT
    username,
    password,
    CASE role
        WHEN 'admin'    THEN 'manager'
        WHEN 'manager'  THEN 'manager'
        WHEN 'staff'    THEN 'staff'
    END AS role,
    created_at
FROM users
WHERE role IN ('admin', 'manager', 'staff')
ON DUPLICATE KEY UPDATE
    password   = VALUES(password),
    role       = VALUES(role),
    created_at = VALUES(created_at);

-- =====================================================================
-- BƯỚC 3: Xóa cột role khỏi bảng users, bỏ UNIQUE KEY phone+role cũ
-- Giữ lại dữ liệu khách hàng (role='customer')
-- =====================================================================

-- Xóa các bản ghi nhân viên/manager khỏi bảng users (đã migrate sang admins)
-- LƯU Ý: Chỉ xóa sau khi xác nhận admins đã có đầy đủ dữ liệu
DELETE FROM users WHERE role IN ('admin', 'manager', 'staff');

-- Xóa constraint/index cũ liên quan đến role
SET @idx := (
    SELECT INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_phone_role'
    LIMIT 1
);
SET @sql := IF(@idx IS NOT NULL,
    CONCAT('ALTER TABLE users DROP INDEX ', @idx),
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Xóa cột role nếu tồn tại
SET @col := (
    SELECT COLUMN_NAME FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'role'
    LIMIT 1
);
SET @sql2 := IF(@col IS NOT NULL,
    'ALTER TABLE users DROP COLUMN role',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Thêm UNIQUE KEY cho phone (nếu chưa có)
SET @phoneIdx := (
    SELECT INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'uq_users_phone'
    LIMIT 1
);
SET @sql3 := IF(@phoneIdx IS NULL,
    'ALTER TABLE users ADD UNIQUE KEY uq_users_phone (phone)',
    'SELECT 1'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- =====================================================================
-- BƯỚC 4: Xác nhận kết quả
-- =====================================================================
SELECT 'users (khách hàng)' AS bảng, COUNT(*) AS số_lượng FROM users
UNION ALL
SELECT 'admins (nội bộ)', COUNT(*) FROM admins;
