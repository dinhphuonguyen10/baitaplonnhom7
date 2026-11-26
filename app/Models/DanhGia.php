<?php

namespace App\Models;

/**
 * Model DanhGia - đánh giá sản phẩm (bảng `reviews`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class DanhGia extends BaseModel
{
    protected string $table = 'reviews';

    // ───────────── Đánh giá phía khách hàng (agentB) ─────────────

    /** Khách đã mua sản phẩm trong một đơn đã giao thành công hay chưa. */
    public function customerPurchasedProduct(int $userId, int $productId): bool
    {
        return $this->db->fetchValue(
            "SELECT 1
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
     WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('Đã giao hàng', 'Đã giao', 'Hoàn thành')
             LIMIT 1",
            [$userId, $productId],
            'ii'
        ) !== null;
    }

    /** Đánh giá của khách cho sản phẩm (id, rating, comment, edit_count) hoặc null. */
    public function findByProductAndUser(int $productId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, rating, comment, edit_count FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1',
            [$productId, $userId],
            'ii'
        );
    }

    /** Khách sửa đánh giá (chỉ một lần). Trả về số dòng bị ảnh hưởng. */
    public function updateByCustomerOnce(int $reviewId, int $productId, int $userId, int $rating, ?string $comment): int
    {
        return $this->db->execute(
            'UPDATE reviews
             SET rating = ?, comment = ?, edit_count = edit_count + 1
             WHERE id = ? AND product_id = ? AND user_id = ? AND edit_count = 0',
            [$rating, $comment, $reviewId, $productId, $userId],
            'isiii'
        );
    }

    /** Thêm đánh giá mới, trả về ID. */
    public function createForCustomer(int $productId, int $userId, int $rating, ?string $comment): int
    {
        return $this->db->insert(
            'INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)',
            [$productId, $userId, $rating, $comment],
            'iiis'
        );
    }

    /** Tổng hợp số sao các đánh giá của sản phẩm. */
    public function summaryForProduct(int $productId): array // hàm đếm
    {
        return $this->db->fetchOne(
            'SELECT
        COUNT(*) AS review_count,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COALESCE(SUM(r.rating = 5), 0) AS five_star_count,
        COALESCE(SUM(r.rating = 4), 0) AS four_star_count,
        COALESCE(SUM(r.rating = 3), 0) AS three_star_count,
        COALESCE(SUM(r.rating = 2), 0) AS two_star_count,
        COALESCE(SUM(r.rating = 1), 0) AS one_star_count
     FROM reviews r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ?
       AND r.rating BETWEEN 1 AND 5',
            [$productId],
            'i'
        ) ?? [];
    }

    /** Một trang đánh giá của sản phẩm (kèm phản hồi admin), lọc theo số sao nếu > 0. */
    public function pageForProduct(int $productId, int $ratingFilter, int $limit, int $offset): array
    {
        $sql = 'SELECT r.id, r.rating, r.comment, r.created_at, u.username,
                         rr.reply AS admin_reply, rr.admin_username AS reply_admin, rr.replied_at
                  FROM reviews r
                  INNER JOIN users u ON u.id = r.user_id
                  LEFT JOIN review_replies rr ON rr.review_id = r.id
                  WHERE r.product_id = ?
                    AND r.rating BETWEEN 1 AND 5';
        $params = [$productId];
        if ($ratingFilter > 0) {
            $sql .= ' AND r.rating = ?';
            $params[] = $ratingFilter;
        }
        $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params, str_repeat('i', count($params)));
    }
      
    // ───────────── Quản trị đánh giá (agentD2) ─────────────

    /**
     * Danh sách đánh giá cho trang quản trị kèm sản phẩm, khách hàng và phản hồi của cửa hàng.
     * Lọc theo sản phẩm (>0), số sao (1-5), từ khóa (khách hàng / sản phẩm / nội dung / phản hồi)
     * và tình trạng phản hồi ('pending' = chưa phản hồi, 'replied' = đã phản hồi).
     */
    public function findForAdmin(int $productId, int $rating, string $search, string $replyStatus = ''): array
    {
        $where = [];
        $types = '';
        $params = [];

        if ($productId > 0) {
            $where[] = 'r.product_id = ?';
            $types .= 'i';
            $params[] = $productId;
        }
        if ($rating >= 1 && $rating <= 5) {
            $where[] = 'r.rating = ?';
            $types .= 'i';
            $params[] = $rating;
        }
        if ($search !== '') {
            $where[] = '(u.username LIKE ? OR p.name LIKE ? OR r.comment LIKE ? OR rr.reply LIKE ?)';
            $types .= 'ssss';
            // % và _ khách gõ là ký tự thường, không phải ký tự đại diện của LIKE.
            $like = '%' . addcslashes($search, '\\%_') . '%';
            array_push($params, $like, $like, $like, $like);
        }

        if ($replyStatus === 'pending') {
            $where[] = 'rr.review_id IS NULL';
        } elseif ($replyStatus === 'replied') {
            $where[] = 'rr.review_id IS NOT NULL';
        }

        $sql = "SELECT r.id, r.rating, r.comment, r.created_at,
               r.product_id, p.name AS product_name, p.image AS product_image,
               r.user_id, u.username, rr.reply AS admin_reply,
               rr.admin_username AS reply_admin, rr.replied_at
        FROM reviews r
        INNER JOIN users u ON u.id = r.user_id
        INNER JOIN products p ON p.id = r.product_id
        LEFT JOIN review_replies rr ON rr.review_id = r.id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY r.created_at DESC, r.id DESC';

        return $this->db->fetchAll($sql, $params, $params === [] ? null : $types);
    }

    /** Số đánh giá chưa được phản hồi (khớp với bộ lọc "Chưa phản hồi" của trang quản trị). */
    public function countPendingReplyForAdmin(): int
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*)
             FROM reviews r
             INNER JOIN users u ON u.id = r.user_id
             INNER JOIN products p ON p.id = r.product_id
             LEFT JOIN review_replies rr ON rr.review_id = r.id
             WHERE rr.review_id IS NULL'
        );
    }

    /** Số lượng đánh giá theo từng mức sao: [rating => total]. */
    public function countByRatingForAdmin(): array
    {
        $counts = [];
        foreach ($this->db->fetchAll('SELECT r.rating, COUNT(*) AS total FROM reviews r GROUP BY r.rating') as $row) {
            $counts[(int) $row['rating']] = (int) $row['total'];
        }

        return $counts;
    }

    // --- end of methods ---
}
