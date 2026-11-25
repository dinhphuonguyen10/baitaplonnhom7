<?php

namespace App\Models;

/**
 * Model SavedReport - báo cáo đã lưu của quản trị (bảng `admin_saved_reports`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class SavedReport extends BaseModel
{
    protected string $table = 'admin_saved_reports';

    /** Tạo bảng admin_saved_reports nếu chưa tồn tại. */
    public function createTableIfMissing(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS admin_saved_reports (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(250) NOT NULL,
    report_type enum('sales', 'customer') NOT NULL,
    created_by varchar(100) NOT NULL DEFAULT '',
    created_at datetime NOT NULL DEFAULT current_timestamp(),
    updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    config_json text NULL,
    PRIMARY KEY (id),
    KEY idx_admin_saved_reports_type (report_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Bổ sung cột config_json cho bảng cũ (nếu thiếu). */
    public function ensureConfigColumn(): void
    {
        $result = $this->db->connection()->query("SHOW COLUMNS FROM admin_saved_reports LIKE 'config_json'");
        if ($result && $result->num_rows === 0) {
            $this->db->query('ALTER TABLE admin_saved_reports ADD COLUMN config_json TEXT NULL');
        }
    }

    /** Danh sách báo cáo đã lưu (id, title, report_type) theo thứ tự tạo. */
    public function findAllForIndex(): array
    {
        return $this->db->fetchAll('SELECT id, title, report_type FROM admin_saved_reports ORDER BY created_at, id');
    }

    /** Một báo cáo đã lưu kèm cấu hình bộ lọc. */
    public function findWithConfig(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, title, report_type, config_json FROM admin_saved_reports WHERE id = ?',
            [$id],
            'i'
        );
    }

    /** Thêm báo cáo mới (không có cấu hình), trả về ID. */
    public function createReport(string $title, string $reportType, string $createdBy): int
    {
        return $this->db->insert(
            'INSERT INTO admin_saved_reports (title, report_type, created_by) VALUES (?, ?, ?)',
            [$title, $reportType, $createdBy],
            'sss'
        );
    }

    /** Thêm báo cáo mới kèm cấu hình bộ lọc, trả về ID. */
    public function createReportWithConfig(string $title, string $reportType, string $createdBy, string $configJson): int
    {
        return $this->db->insert(
            'INSERT INTO admin_saved_reports (title, report_type, created_by, config_json) VALUES (?, ?, ?, ?)',
            [$title, $reportType, $createdBy, $configJson],
            'ssss'
        );
    }

    /** Cập nhật tên và loại báo cáo. */
    public function updateTitleAndType(int $id, string $title, string $reportType): int
    {
        return $this->db->execute(
            'UPDATE admin_saved_reports SET title = ?, report_type = ? WHERE id = ?',
            [$title, $reportType, $id],
            'ssi'
        );
    }

    /** Lưu cấu hình bộ lọc cho báo cáo đúng loại. */
    public function updateConfig(int $id, string $reportType, string $configJson): int
    {
        return $this->db->execute(
            'UPDATE admin_saved_reports SET config_json = ? WHERE id = ? AND report_type = ?',
            [$configJson, $id, $reportType],
            'sis'
        );
    }

    // --- end of methods ---
}
