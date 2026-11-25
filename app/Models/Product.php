<?php

namespace App\Models;

/**
 * Model Product - sản phẩm trang sức (bảng `products`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Product extends BaseModel
{
    protected string $table = 'products';

    // ───────────── Tồn kho khi đặt / hủy đơn (agentB) ─────────────

    /** Khóa dòng sản phẩm để kiểm tra tồn kho khi đặt hàng. */
    public function lockForOrder(int $productId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, price_sale, image, stock, status, is_deleted FROM products WHERE id = ? FOR UPDATE',
            [$productId],
            'i'
        );
    }

    /** Trừ tồn kho nếu còn đủ. Trả về số dòng bị ảnh hưởng (1 = thành công). */
    public function decrementStock(int $productId, int $quantity): int
    {
        return $this->db->execute(
            'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
            [$quantity, $productId, $quantity],
            'iii'
        );
    }

    /** Cộng lại tồn kho (hoàn kho khi hủy đơn). Trả về số dòng bị ảnh hưởng. */
    public function incrementStock(int $productId, int $quantity): int
    {
        return $this->db->execute(
            'UPDATE products SET stock = stock + ? WHERE id = ?',
            [$quantity, $productId],
            'ii'
        );
    }

    /** Điều kiện chung: chỉ sản phẩm đang kinh doanh và chưa bị xóa mềm. */
    private const STOREFRONT_VISIBLE = "is_deleted = 0 AND status = 'active'";

    /**
     * Danh sách sản phẩm cho các khối trang chủ (view_product_details).
     * $section: featured (bán chạy), suggest (nhiều tim), new/latest (mới nhất), còn lại = tất cả.
     */
    public function findStorefrontSection(string $section): array
    {
        $visible = self::STOREFRONT_VISIBLE;
        if ($section === 'featured') {
            return $this->db->fetchAll("SELECT * FROM view_product_details WHERE {$visible} ORDER BY sold_count DESC, id DESC LIMIT 10");
        }
        if ($section === 'suggest') {
            return $this->db->fetchAll("SELECT * FROM view_product_details WHERE {$visible} ORDER BY wishlist_count DESC, id DESC LIMIT 10");
        }
        if ($section === 'new' || $section === 'latest') {
            return $this->db->fetchAll("SELECT * FROM view_product_details WHERE {$visible} ORDER BY id DESC LIMIT 10");
        }

        return $this->db->fetchAll("SELECT * FROM view_product_details WHERE {$visible} ORDER BY id DESC");
    }

    /**
     * Sản phẩm của một danh mục (theo slug, tên hoặc ID) kèm sắp xếp và bộ lọc.
     *
     * @param array{min_price?: ?float, max_price?: ?float, in_stock?: bool, has_discount?: bool, has_gift?: bool} $filters
     */
    public function findStorefrontByCategory(string $category, string $sort = '', array $filters = []): array
    {
        $orderBy = 'sold_count DESC, id DESC';
        if ($sort === 'popular' || $sort === 'suggest') {
            $orderBy = 'wishlist_count DESC, id DESC';
        } elseif ($sort === 'newest') {
            $orderBy = 'id DESC';
        } elseif ($sort === 'price_asc') {
            $orderBy = 'price_sale ASC, id DESC';
        } elseif ($sort === 'price_desc') {
            $orderBy = 'price_sale DESC, id DESC';
        }

        $where = self::STOREFRONT_VISIBLE . ' AND (category = ? OR category_name = ? OR CAST(category_id AS CHAR) = ?)';
        $params = [$category, $category, $category];
        $types = 'sss';

        if (isset($filters['min_price'])) {
            $where .= ' AND price_sale >= ?';
            $params[] = (float) $filters['min_price'];
            $types .= 'd';
        }
        if (isset($filters['max_price'])) {
            $where .= ' AND price_sale <= ?';
            $params[] = (float) $filters['max_price'];
            $types .= 'd';
        }
        if (!empty($filters['in_stock'])) {
            $where .= ' AND stock > 0';
        }
        if (!empty($filters['has_discount'])) {
            $where .= " AND (discount IS NOT NULL AND discount != '' OR price_original > price_sale)";
        }
        if (!empty($filters['has_gift'])) {
            $where .= " AND gift IS NOT NULL AND gift != ''";
        }

        return $this->db->fetchAll("SELECT * FROM view_product_details WHERE {$where} ORDER BY {$orderBy}", $params, $types);
    }

    /** Chi tiết một sản phẩm từ view_product_details (chưa lọc trạng thái). */
    public function findDetailFromView(int $productId): ?array
    {
        return $this->db->fetchOne('SELECT * FROM view_product_details WHERE id = ? LIMIT 1', [$productId], 'i');
    }

    /** Lấy id và tên sản phẩm (kiểm tra tồn tại). */
    public function findIdAndName(int $productId): ?array
    {
        return $this->db->fetchOne('SELECT id, name FROM products WHERE id = ? LIMIT 1', [$productId], 'i');
    }

    // ───────────── Quản trị sản phẩm (agentD2) ─────────────

    /** Đếm sản phẩm theo cờ xóa mềm (0 = đang hoạt động, 1 = thùng rác). */
    public function countByDeletedFlag(int $isDeleted): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) AS cnt FROM products WHERE is_deleted = ?', [$isDeleted], 'i');
    }

    /** Danh sách sản phẩm từ VIEW view_product_details cho trang quản trị (đang hoạt động hoặc thùng rác). */
    public function findDetailsForAdmin(bool $trash): array
    {
        if ($trash) {
            return $this->db->fetchAll('SELECT * FROM view_product_details WHERE is_deleted = 1 ORDER BY deleted_at DESC, id DESC');
        }

        return $this->db->fetchAll('SELECT * FROM view_product_details WHERE is_deleted = 0 ORDER BY id DESC');
    }

    /**
     * Thêm sản phẩm từ trang quản trị.
     *
     * @param array $data name, category_id, price_sale, stock, image, description, is_featured, is_suggested, is_new, status
     */
    public function createFromAdmin(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO products (name, category_id, price_sale, stock, image, description, is_featured, is_suggested, is_new, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)',
            [
                $data['name'], $data['category_id'], $data['price_sale'], $data['stock'], $data['image'],
                $data['description'], $data['is_featured'], $data['is_suggested'], $data['is_new'], $data['status'],
            ],
            'sidissiiis'
        );
    }

    /**
     * Cập nhật sản phẩm từ trang quản trị; $image = null nghĩa là giữ nguyên ảnh cũ.
     *
     * @param array $data name, category_id, price_sale, price_original, stock, description, is_featured, is_suggested, is_new, status
     */
    public function updateFromAdmin(int $id, array $data, ?string $image): int
    {
        $params = [$data['name'], $data['category_id'], $data['price_sale'], $data['price_original'], $data['stock']];
        $types = 'siddi';
        $imageSql = '';
        if ($image !== null) {
            $imageSql = ' image = ?,';
            $params[] = $image;
            $types .= 's';
        }
        array_push($params, $data['description'], $data['is_featured'], $data['is_suggested'], $data['is_new'], $data['status'], $id);
        $types .= 'siiisi';

        return $this->db->execute(
            "UPDATE products SET name = ?, category_id = ?, price_sale = ?, price_original = ?, stock = ?,{$imageSql} description = ?, is_featured = ?, is_suggested = ?, is_new = ?, status = ? WHERE id = ?",
            $params,
            $types
        );
    }

    /** Xóa mềm sản phẩm (chuyển vào thùng rác). */
    public function softDeleteByAdmin(int $id, string $deletedBy): int
    {
        return $this->db->execute(
            'UPDATE products SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?',
            [$deletedBy, $id],
            'si'
        );
    }

    /** Khôi phục sản phẩm từ thùng rác. */
    public function restoreFromTrash(int $id): int
    {
        return $this->db->execute(
            'UPDATE products SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?',
            [$id],
            'i'
        );
    }

    /** Số dòng chi tiết đơn hàng có chứa sản phẩm. */
    public function countOrderItemsForProduct(int $id): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) AS order_count FROM order_items WHERE product_id = ?', [$id], 'i');
    }

    /**
     * Xóa vĩnh viễn sản phẩm đang ở thùng rác cùng dữ liệu phụ trợ (giỏ hàng, yêu thích, đánh giá)
     * trong một transaction.
     */
    public function forceDeleteFromTrash(int $id): void
    {
        $this->db->transaction(static function ($db) use ($id): void {
            $db->execute('DELETE FROM cart_items WHERE product_id = ?', [$id], 'i');
            $db->execute('DELETE FROM wishlists WHERE product_id = ?', [$id], 'i');
            $db->execute('DELETE FROM reviews WHERE product_id = ?', [$id], 'i');
            $db->execute('DELETE FROM products WHERE id = ? AND is_deleted = 1', [$id], 'i');
        });
    }

    /** Danh sách sản phẩm (id, name) sắp xếp theo tên - dùng cho bộ lọc đánh giá. */
    public function findAllIdNameOrderedByName(): array
    {
        return $this->db->fetchAll('SELECT id, name FROM products ORDER BY name ASC');
    }

    // --- end of methods ---
}
