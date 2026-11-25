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

    /** Danh mục (id, name, slug) theo id tăng dần - dùng cho menu điều hướng và trang chủ. */
    public function findAllForNavigation(): array
    {
        return $this->db->fetchAll('SELECT id, name, slug FROM categories ORDER BY id ASC');
    }

    /** Tìm danh mục theo slug, tên hoặc ID (dạng chuỗi). */
    public function findBySlugNameOrId(string $key): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, slug FROM categories WHERE slug = ? OR name = ? OR CAST(id AS CHAR) = ? LIMIT 1',
            [$key, $key, $key],
            'sss'
        );
    }

    // --- end of methods ---
}
