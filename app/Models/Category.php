<?php

namespace App\Models;

/**
 * Model Category - danh mục sản phẩm (bảng `categories`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Category extends BaseModel
{
    protected string $table = 'categories';

    // ───────────── Quản trị danh mục (agentD2) ─────────────

    /** Danh sách danh mục (id, name) theo thứ tự id tăng dần - dùng cho trang quản trị/dropdown. */
    public function findAllIdNameForAdmin(): array
    {
        return $this->db->fetchAll('SELECT id, name FROM categories ORDER BY id ASC');
    }

    /** Tên danh mục đã tồn tại chưa (có thể loại trừ một id khi sửa). */
    public function nameExistsForAdmin(string $name, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            return $this->db->fetchOne('SELECT id FROM categories WHERE name = ? AND id != ? LIMIT 1', [$name, $excludeId], 'si') !== null;
        }

        return $this->db->fetchOne('SELECT id FROM categories WHERE name = ? LIMIT 1', [$name], 's') !== null;
    }

    /** Slug đã được dùng chưa. */
    public function slugExistsForAdmin(string $slug): bool
    {
        return $this->db->fetchOne('SELECT id FROM categories WHERE slug = ? LIMIT 1', [$slug], 's') !== null;
    }

    /** Thêm danh mục mới, trả về id. */
    public function createWithSlug(string $name, string $slug): int
    {
        return $this->db->insert('INSERT INTO categories (name, slug) VALUES (?, ?)', [$name, $slug], 'ss');
    }

    /** Cập nhật tên + slug của danh mục. */
    public function updateNameAndSlug(int $id, string $name, string $slug): int
    {
        return $this->db->execute('UPDATE categories SET name = ?, slug = ? WHERE id = ?', [$name, $slug, $id], 'ssi');
    }

    /** Số sản phẩm thuộc danh mục (kể cả sản phẩm đã xóa mềm). */
    public function countProductsInCategory(int $id): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) AS total FROM products WHERE category_id = ?', [$id], 'i');
    }

    // ───────────── Báo cáo thống kê (agentE) ─────────────

    /** Danh sách danh mục (id, name) sắp xếp theo tên - dùng cho bộ lọc báo cáo. */
    public function findAllIdNameOrderedByName(): array
    {
        return $this->db->fetchAll('SELECT id, name FROM categories ORDER BY name');
    }

    // ───────────── Giao diện cửa hàng (agentA) ─────────────

    /** Danh mục đang hoạt động, chưa xóa mềm (id, name, slug, image) theo id tăng dần - menu điều hướng và trang chủ. */
    public function findAllForNavigation(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, slug, image
             FROM categories
             WHERE status = 'active' AND is_deleted = 0
             ORDER BY id ASC"
        );
    }

    /** Tìm danh mục đang hoạt động (chưa xóa mềm) theo slug, tên hoặc ID (dạng chuỗi). */
    public function findBySlugNameOrId(string $key): ?array
    {
        return $this->db->fetchOne(
            "SELECT id, name, slug
             FROM categories
             WHERE status = 'active' AND is_deleted = 0
               AND (slug = ? OR name = ? OR CAST(id AS CHAR) = ?)
             LIMIT 1",
            [$key, $key, $key],
            'sss'
        );
    }

    // ───────────── Vòng đời danh mục: mã, mô tả, ảnh, trạng thái, xóa mềm (portCatalog) ─────────────

    /** Toàn bộ danh mục (kể cả đã xóa mềm) kèm số sản phẩm (tính cả sản phẩm đã xóa mềm) cho trang quản trị. */
    public function findAllWithProductCountForAdmin(): array
    {
        return $this->db->fetchAll(
            'SELECT c.id, c.code, c.name, c.slug, c.description, c.image, c.status,
                    c.is_deleted, c.created_at, c.updated_at,
                    (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
             FROM categories c
             ORDER BY c.is_deleted ASC, c.id ASC'
        );
    }

    /** Danh mục đang hoạt động, chưa xóa mềm (id, name) - dropdown chọn danh mục của sản phẩm. */
    public function findActiveIdNameForAdmin(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name
             FROM categories
             WHERE status = 'active' AND is_deleted = 0
             ORDER BY id ASC"
        );
    }

    /** Khóa (FOR UPDATE) danh mục theo cờ xóa mềm; trả về id, name, code, status hoặc null. Gọi trong transaction. */
    public function lockByDeletedFlag(int $id, int $isDeleted): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, code, status FROM categories WHERE id = ? AND is_deleted = ? FOR UPDATE',
            [$id, $isDeleted],
            'ii'
        );
    }

    /** Khóa (FOR UPDATE) danh mục đang hoạt động và chưa xóa mềm; true nếu tồn tại. Gọi trong transaction. */
    public function lockActive(int $id): bool
    {
        return $this->db->fetchOne(
            "SELECT id FROM categories WHERE id = ? AND status = 'active' AND is_deleted = 0 FOR UPDATE",
            [$id],
            'i'
        ) !== null;
    }

    /** Slug đã được danh mục khác (id <> $excludeId) dùng chưa. */
    public function slugUsedByOther(string $slug, int $excludeId): bool
    {
        return $this->db->fetchOne('SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $excludeId], 'si') !== null;
    }

    /** Thêm danh mục đầy đủ thông tin, trả về id. */
    public function createWithDetails(string $code, string $name, string $slug, string $description, ?string $image, string $status): int
    {
        return $this->db->insert(
            'INSERT INTO categories (code, name, slug, description, image, status) VALUES (?, ?, ?, ?, ?, ?)',
            [$code, $name, $slug, $description, $image, $status],
            'ssssss'
        );
    }

    /** Cập nhật thông tin danh mục chưa bị xóa mềm. */
    public function updateDetails(int $id, string $name, string $slug, string $description, ?string $image, string $status): int
    {
        return $this->db->execute(
            'UPDATE categories
             SET name = ?, slug = ?, description = ?, image = ?, status = ?, updated_at = NOW()
             WHERE id = ? AND is_deleted = 0',
            [$name, $slug, $description, $image, $status, $id],
            'sssssi'
        );
    }

    /** Xóa mềm danh mục. */
    public function softDelete(int $id, string $deletedBy): int
    {
        return $this->db->execute(
            'UPDATE categories
             SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_at = NOW()
             WHERE id = ? AND is_deleted = 0',
            [$deletedBy, $id],
            'si'
        );
    }

    /** Khôi phục danh mục đã xóa mềm (giữ nguyên trạng thái hoạt động). */
    public function restoreDeleted(int $id): int
    {
        return $this->db->execute(
            'UPDATE categories
             SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL, updated_at = NOW()
             WHERE id = ? AND is_deleted = 1',
            [$id],
            'i'
        );
    }

    // --- end of methods ---
}
