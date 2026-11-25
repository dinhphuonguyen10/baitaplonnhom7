<?php

namespace App\Models;

/**
 * Model BaoCaoDaXuat - nhật ký báo cáo đã xuất của quản trị (bảng `admin_exported_reports`):
 * tên báo cáo, loại, cấu hình bộ lọc (config_json) để có thể sửa và xuất lại.
 */
class BaoCaoDaXuat extends BaseModel
{
    protected string $table = 'admin_exported_reports';

    /** Tạo bảng admin_exported_reports nếu chưa tồn tại. */
    public function createTableIfMissing(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS admin_exported_reports (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(250) NOT NULL,
    report_type enum('sales', 'customer') NOT NULL,
    config_json text NOT NULL,
    snapshot_json longtext NULL,
    file_format varchar(10) NOT NULL DEFAULT 'xlsx',
    exported_by varchar(100) NOT NULL DEFAULT '',
    export_count int(10) unsigned NOT NULL DEFAULT 1,
    created_at datetime NOT NULL DEFAULT current_timestamp(),
    last_exported_at datetime NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id),
    KEY idx_admin_exported_reports_last (last_exported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    /** Bổ sung cột snapshot_json (dữ liệu tại thời điểm xuất) cho bảng cũ nếu thiếu. */
    public function ensureSnapshotColumn(): void
    {
        $result = $this->db->connection()->query("SHOW COLUMNS FROM admin_exported_reports LIKE 'snapshot_json'");
        if ($result && $result->num_rows === 0) {
            $this->db->query('ALTER TABLE admin_exported_reports ADD COLUMN snapshot_json LONGTEXT NULL AFTER config_json');
        }
    }

    /** Danh sách báo cáo đã xuất, lần xuất gần nhất trước. */
    public function findAllRecent(): array
    {
        return $this->db->fetchAll(
            'SELECT id, name, report_type, config_json, file_format, export_count, created_at, last_exported_at
               FROM admin_exported_reports ORDER BY last_exported_at DESC, id DESC'
        );
    }

    /** Ghi nhận một lần xuất mới, trả về ID. */
    public function createEntry(string $name, string $reportType, string $configJson, string $format, string $exportedBy, ?string $snapshotJson = null): int
    {
        return $this->db->insert(
            'INSERT INTO admin_exported_reports (name, report_type, config_json, snapshot_json, file_format, exported_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $reportType, $configJson, $snapshotJson, $format, $exportedBy],
            'ssssss'
        );
    }

    /** Ghi nhận xuất lại: tăng số lần và cập nhật thời điểm xuất gần nhất. */
    public function touchExport(int $id, string $format): int
    {
        return $this->db->execute(
            'UPDATE admin_exported_reports SET export_count = export_count + 1, last_exported_at = NOW(), file_format = ? WHERE id = ?',
            [$format, $id],
            'si'
        );
    }

    /** Xóa một dòng khỏi danh sách báo cáo đã xuất. */
    public function deleteEntry(int $id): int
    {
        return $this->db->execute('DELETE FROM admin_exported_reports WHERE id = ?', [$id], 'i');
    }
}
