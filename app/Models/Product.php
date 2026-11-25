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

    // ───────────── Đặt hàng: giá hiệu lực + snapshot (portOrders) ─────────────

    /**
     * Khóa sản phẩm (thuộc danh mục đang hoạt động) để đặt hàng, kèm SKU, giá gốc,
     * tên danh mục và giá hiệu lực (hết khuyến mãi => giá gốc).
     */
    public function lockForOrderWithPricing(int $productId): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.id, p.sku, p.name, p.price_original, c.name AS category_name,
                    CASE
                        WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                        THEN p.price_original
                        ELSE p.price_sale
                    END AS effective_price,
                    p.image, p.stock, p.status, p.is_deleted
             FROM products p
             INNER JOIN categories c
               ON c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0
             WHERE p.id = ?
             FOR UPDATE",
            [$productId],
            'i'
        );
    }

    // ───────────── Vòng đời sản phẩm, SKU, khuyến mãi, lịch sử giá (portCatalog) ─────────────

    /** Điều kiện (trên view_product_details) cho sản phẩm khách được thấy: sản phẩm & danh mục đang hoạt động. */
    public const VISIBLE_DETAIL_CONDITION = "is_deleted = 0 AND status = 'active'
              AND category_is_deleted = 0 AND category_status = 'active'";

    /** Các tiêu chí sắp xếp hợp lệ cho trang danh mục / tìm kiếm. */
    public const STOREFRONT_SORTS = [
        'best_selling' => 'sold_count DESC, id DESC',
        'popular' => 'wishlist_count DESC, id DESC',
        'suggest' => 'wishlist_count DESC, id DESC',
        'newest' => 'id DESC',
        'price_asc' => 'price_sale ASC, id DESC',
        'price_desc' => 'price_sale DESC, id DESC',
        'name_asc' => 'name ASC, id ASC',
    ];

    /** Lượt bán 30 ngày (đơn đã giao/hoàn thành và đã thanh toán) - JOIN cho các khối trang chủ. */
    private const HOME_SALES_JOIN = "LEFT JOIN (
        SELECT oi.product_id, SUM(oi.quantity) AS quantity_30d
        FROM order_items oi
        INNER JOIN orders o ON o.id = oi.order_id
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND o.created_at <= NOW()
          AND o.status IN ('Đã giao hàng', 'Đã giao', 'Hoàn thành')
          AND o.payment_status = 'Đã thanh toán'
        GROUP BY oi.product_id
    ) home_sales ON home_sales.product_id = p.id";

    private const HOME_ELIGIBLE = "p.is_deleted = 0 AND p.status = 'active'
        AND v.is_deleted = 0 AND v.status = 'active'
        AND v.category_is_deleted = 0 AND v.category_status = 'active'";

    /**
     * Giá bán hiệu lực hiện tại của sản phẩm (VIEW view_product_details: hết hạn khuyến mãi => giá gốc),
     * dạng chuỗi decimal như MySQL trả về; null nếu sản phẩm không tồn tại.
     */
    public function effectivePrice(int $productId): ?string
    {
        $price = $this->db->fetchValue('SELECT price_sale FROM view_product_details WHERE id = ? LIMIT 1', [$productId], 'i');

        return $price === null ? null : (string) $price;
    }

    /** Chi tiết sản phẩm khách được xem (sản phẩm & danh mục đang hoạt động, chưa xóa mềm). */
    public function findVisibleDetail(int $productId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM view_product_details WHERE id = ? AND ' . self::VISIBLE_DETAIL_CONDITION . ' LIMIT 1',
            [$productId],
            'i'
        );
    }

    /** Sản phẩm còn hiển thị cho khách trong danh sách ID. */
    public function findVisibleByIds(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));

        return $this->db->fetchAll(
            'SELECT * FROM view_product_details WHERE ' . self::VISIBLE_DETAIL_CONDITION . " AND id IN ({$placeholders})",
            array_values($productIds),
            str_repeat('i', count($productIds))
        );
    }

    /** Tất cả sản phẩm khách được thấy, mới nhất trước. */
    public function findAllVisible(): array
    {
        return $this->db->fetchAll('SELECT * FROM view_product_details WHERE ' . self::VISIBLE_DETAIL_CONDITION . ' ORDER BY id DESC');
    }

    /** Khối "Sản phẩm mới" trang chủ: 10 sản phẩm tạo gần nhất. */
    public function findHomeNewest(): array
    {
        return $this->fetchAllPrepared(
            'SELECT v.*, p.created_at AS product_created_at,
                    COALESCE(home_sales.quantity_30d, 0) AS home_sold_count
             FROM view_product_details v
             INNER JOIN products p ON p.id = v.id
             ' . self::HOME_SALES_JOIN . '
             WHERE ' . self::HOME_ELIGIBLE . '
             ORDER BY p.created_at DESC, p.id DESC
             LIMIT 10'
        );
    }

    /** Khối "Bán chạy" trang chủ: 10 sản phẩm bán nhiều nhất 30 ngày, loại trừ các ID cho trước. */
    public function findHomeBestSelling(array $excludeIds): array
    {
        [$excludeSql, $excludeIds] = $this->homeExclusion($excludeIds);

        return $this->fetchAllPrepared(
            'SELECT v.*, COALESCE(home_sales.quantity_30d, 0) AS home_sold_count
             FROM view_product_details v
             INNER JOIN products p ON p.id = v.id
             ' . self::HOME_SALES_JOIN . '
             WHERE ' . self::HOME_ELIGIBLE . '
               AND home_sales.quantity_30d > 0
               ' . $excludeSql . '
             ORDER BY home_sales.quantity_30d DESC, p.id DESC
             LIMIT 10',
            $excludeIds,
            str_repeat('i', count($excludeIds))
        );
    }

    /** Danh mục (đang hoạt động) khách mua nhiều nhất trong các đơn đã giao & đã thanh toán; null nếu chưa có. */
    public function findPreferredCategoryIdForCustomer(int $customerId): ?int
    {
        $rows = $this->fetchAllPrepared(
            "SELECT p.category_id, SUM(oi.quantity) AS purchased_quantity
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             INNER JOIN products p ON p.id = oi.product_id
             INNER JOIN categories c ON c.id = p.category_id
             WHERE o.user_id = ?
               AND o.status IN ('Đã giao hàng', 'Đã giao', 'Hoàn thành')
               AND o.payment_status = 'Đã thanh toán'
               AND c.status = 'active' AND c.is_deleted = 0
             GROUP BY p.category_id
             ORDER BY purchased_quantity DESC, p.category_id ASC
             LIMIT 1",
            [$customerId],
            'i'
        );

        return $rows === [] ? null : (int) $rows[0]['category_id'];
    }

    /** Khối "Gợi ý" trang chủ: sản phẩm có lượt bán 30 ngày hoặc lượt tim, có thể giới hạn theo danh mục. */
    public function findHomeRecommendations(?int $categoryId, array $excludeIds): array
    {
        [$excludeSql, $excludeIds] = $this->homeExclusion($excludeIds);
        $categoryClause = $categoryId === null ? '' : ' AND p.category_id = ?';
        $types = $categoryId === null ? '' : 'i';
        $params = $categoryId === null ? [] : [$categoryId];
        if ($excludeIds !== []) {
            $types .= str_repeat('i', count($excludeIds));
            array_push($params, ...$excludeIds);
        }

        return $this->fetchAllPrepared(
            'SELECT v.*, COALESCE(home_sales.quantity_30d, 0) AS home_sold_count
             FROM view_product_details v
             INNER JOIN products p ON p.id = v.id
             ' . self::HOME_SALES_JOIN . '
             WHERE ' . self::HOME_ELIGIBLE . '
               ' . $categoryClause . '
               AND (COALESCE(home_sales.quantity_30d, 0) > 0 OR v.wishlist_count > 0)
               ' . $excludeSql . '
             ORDER BY COALESCE(home_sales.quantity_30d, 0) DESC, v.wishlist_count DESC, p.id DESC
             LIMIT 10',
            $params,
            $types
        );
    }

    /** Đếm sản phẩm khớp bộ lọc trang danh mục / tìm kiếm (xem storefrontListingWhere). */
    public function countStorefrontListing(array $criteria): int
    {
        [$where, $params, $types] = $this->storefrontListingWhere($criteria);
        $rows = $this->fetchAllPrepared("SELECT COUNT(*) AS total FROM view_product_details WHERE {$where}", $params, $types);

        return (int) ($rows[0]['total'] ?? 0);
    }

    /** Một trang sản phẩm khớp bộ lọc; $sort là khóa trong STOREFRONT_SORTS hoặc 'relevance' (cần search_pattern). */
    public function findStorefrontListingPage(array $criteria, string $sort, int $limit, int $offset): array
    {
        [$where, $params, $types] = $this->storefrontListingWhere($criteria);
        $pattern = (string) ($criteria['search_pattern'] ?? '');
        if ($sort === 'relevance' && $pattern !== '') {
            $orderBy = "CASE
            WHEN name LIKE ? ESCAPE '!' THEN 0
            WHEN sku LIKE ? ESCAPE '!' THEN 1
            WHEN category_name LIKE ? ESCAPE '!' THEN 2
            ELSE 3
        END, sold_count DESC, id DESC";
            array_push($params, $pattern, $pattern, $pattern);
            $types .= 'sss';
        } else {
            $orderBy = self::STOREFRONT_SORTS[$sort] ?? self::STOREFRONT_SORTS['best_selling'];
        }
        array_push($params, $limit, $offset);
        $types .= 'ii';

        return $this->fetchAllPrepared(
            "SELECT * FROM view_product_details
         WHERE {$where}
         ORDER BY {$orderBy}
         LIMIT ? OFFSET ?",
            $params,
            $types
        );
    }

    /** Sản phẩm kèm trạng thái danh mục (kiểm tra trước khi thả tim); null nếu không có hoặc không có danh mục. */
    public function findWithCategoryStatus(int $productId): ?array
    {
        return $this->db->fetchOne(
            'SELECT p.id, p.name, p.status, p.is_deleted,
                    c.status AS category_status, c.is_deleted AS category_is_deleted
             FROM products p
             JOIN categories c ON c.id = p.category_id
             WHERE p.id = ?
             LIMIT 1',
            [$productId],
            'i'
        );
    }

    /** SKU đã được sản phẩm khác (id <> $excludeId) dùng chưa. */
    public function skuUsedByOther(string $sku, int $excludeId): bool
    {
        return $this->db->fetchOne('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1', [$sku, $excludeId], 'si') !== null;
    }

    /**
     * Thêm sản phẩm (kèm SKU, giá gốc, giá bán, hạn khuyến mãi) từ trang quản trị, trả về id.
     *
     * @param array $data sku, name, category_id, sale_price, regular_price, promotion_ends_at, stock, image,
     *                    description, is_featured, is_suggested, is_new, status
     */
    public function createWithPricing(array $data): int
    {
        return $this->db->insert(
            'INSERT INTO products (
                sku, name, category_id, price_sale, price_original, promotion_ends_at,
                stock, image, description, is_featured, is_suggested, is_new, status, is_deleted, created_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())',
            [
                $data['sku'], $data['name'], $data['category_id'], $data['sale_price'], $data['regular_price'],
                $data['promotion_ends_at'], $data['stock'], $data['image'], $data['description'],
                $data['is_featured'], $data['is_suggested'], $data['is_new'], $data['status'],
            ],
            'ssisssissiiis'
        );
    }

    /** Khóa (FOR UPDATE) sản phẩm chưa xóa mềm để sửa: sku, giá, hạn khuyến mãi, giá hiệu lực, ảnh. */
    public function lockForAdminEdit(int $productId): ?array
    {
        return $this->db->fetchOne(
            'SELECT sku, price_sale, price_original, promotion_ends_at,
                    CASE
                        WHEN promotion_ends_at IS NOT NULL AND promotion_ends_at <= NOW()
                        THEN price_original
                        ELSE price_sale
                    END AS effective_price,
                    image
             FROM products
             WHERE id = ? AND is_deleted = 0
             FOR UPDATE',
            [$productId],
            'i'
        );
    }

    /**
     * Cập nhật sản phẩm chưa xóa mềm từ trang quản trị (giá bán ghi bằng $salePrice).
     *
     * @param array $data sku, name, category_id, regular_price, promotion_ends_at, stock, image, description,
     *                    is_featured, is_suggested, is_new, status
     */
    public function updateWithPricing(int $productId, array $data, string $salePrice): int
    {
        return $this->db->execute(
            'UPDATE products
             SET sku = ?, name = ?, category_id = ?, price_sale = ?, price_original = ?,
                 promotion_ends_at = ?, stock = ?, image = ?, description = ?,
                 is_featured = ?, is_suggested = ?, is_new = ?, status = ?
             WHERE id = ? AND is_deleted = 0',
            [
                $data['sku'], $data['name'], $data['category_id'], $salePrice, $data['regular_price'],
                $data['promotion_ends_at'], $data['stock'], $data['image'], $data['description'],
                $data['is_featured'], $data['is_suggested'], $data['is_new'], $data['status'], $productId,
            ],
            'ssisssissiiisi'
        );
    }

    /** Ghi một dòng lịch sử thay đổi giá (bảng product_price_history). */
    public function insertPriceHistory(
        int $productId,
        ?string $oldPrice,
        string $newPrice,
        ?string $oldRegularPrice,
        string $newRegularPrice,
        ?string $oldPromotionPrice,
        ?string $newPromotionPrice,
        ?string $oldPromotionEndsAt,
        ?string $newPromotionEndsAt,
        string $changedBy
    ): void {
        $this->db->execute(
            'INSERT INTO product_price_history (
                product_id, old_price, new_price, old_regular_price, new_regular_price,
                old_promotion_price, new_promotion_price, old_promotion_ends_at,
                new_promotion_ends_at, changed_by
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $productId, $oldPrice, $newPrice, $oldRegularPrice, $newRegularPrice,
                $oldPromotionPrice, $newPromotionPrice, $oldPromotionEndsAt, $newPromotionEndsAt, $changedBy,
            ],
            'isssssssss'
        );
    }

    /** Xóa mềm sản phẩm đang hoạt động; trả về số dòng bị ảnh hưởng (1 = thành công). */
    public function softDeleteActive(int $productId, string $deletedBy): int
    {
        return $this->db->execute(
            'UPDATE products SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ? AND is_deleted = 0',
            [$deletedBy, $productId],
            'si'
        );
    }

    /** Khôi phục sản phẩm đang trong thùng rác; trả về số dòng bị ảnh hưởng (1 = thành công). */
    public function restoreDeleted(int $productId): int
    {
        return $this->db->execute(
            'UPDATE products SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ? AND is_deleted = 1',
            [$productId],
            'i'
        );
    }

    /**
     * Điều kiện WHERE cho trang danh mục / tìm kiếm.
     *
     * @param array{category_id?: ?int, search_pattern?: string, min_price?: ?int, max_price?: ?int,
     *              in_stock?: bool, has_discount?: bool, has_gift?: bool} $criteria
     * @return array{0: string, 1: array, 2: string} [where, params, types]
     */
    private function storefrontListingWhere(array $criteria): array
    {
        $where = self::VISIBLE_DETAIL_CONDITION;
        $params = [];
        $types = '';
        if (isset($criteria['category_id'])) {
            $where .= ' AND category_id = ?';
            $params[] = (int) $criteria['category_id'];
            $types .= 'i';
        }
        $pattern = (string) ($criteria['search_pattern'] ?? '');
        if ($pattern !== '') {
            $where .= " AND (
            name LIKE ? ESCAPE '!'
            OR sku LIKE ? ESCAPE '!'
            OR category_name LIKE ? ESCAPE '!'
            OR description LIKE ? ESCAPE '!'
        )";
            array_push($params, $pattern, $pattern, $pattern, $pattern);
            $types .= 'ssss';
        }
        if (isset($criteria['min_price'])) {
            $where .= ' AND price_sale >= ?';
            $params[] = (int) $criteria['min_price'];
            $types .= 'i';
        }
        if (isset($criteria['max_price'])) {
            $where .= ' AND price_sale <= ?';
            $params[] = (int) $criteria['max_price'];
            $types .= 'i';
        }
        if (!empty($criteria['in_stock'])) {
            $where .= ' AND stock > 0';
        }
        if (!empty($criteria['has_discount'])) {
            $where .= " AND (discount IS NOT NULL AND discount <> '' OR price_original > price_sale)";
        }
        if (!empty($criteria['has_gift'])) {
            $where .= " AND gift IS NOT NULL AND gift <> ''";
        }

        return [$where, $params, $types];
    }

    /** Mệnh đề loại trừ ID cho các khối trang chủ. */
    private function homeExclusion(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        $clause = $ids === [] ? '' : ' AND p.id NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';

        return [$clause, $ids];
    }

    /** Luôn chạy bằng prepared statement (kể cả khi không có tham số) để kiểu dữ liệu trả về giống bản gốc. */
    private function fetchAllPrepared(string $sql, array $params = [], string $types = ''): array
    {
        $stmt = $this->db->prepare($sql, $params, $types === '' ? null : $types);
        if (!$stmt->execute()) {
            $message = $stmt->error;
            $code = $stmt->errno;
            $stmt->close();
            throw new \App\Core\DatabaseException($message, $code, $sql);
        }
        $result = $stmt->get_result();
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }

    /** (id, name) của sản phẩm còn kinh doanh (sản phẩm & danh mục đang hoạt động) - dùng cho chia sẻ sản phẩm trong chat (portOrders). */
    public function findActiveIdAndName(int $productId): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.id, p.name
             FROM products p
             JOIN categories c ON c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0
             WHERE p.id = ? AND p.is_deleted = 0 AND p.status = 'active'
             LIMIT 1",
            [$productId],
            'i'
        );
    }

    // --- end of methods ---
}
