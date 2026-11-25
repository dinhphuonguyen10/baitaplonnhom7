<?php

namespace App\Models;

/**
 * Model ReviewReply - phản hồi của quản trị viên cho đánh giá (bảng `review_replies`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class ReviewReply extends BaseModel
{
    protected string $table = 'review_replies';
    protected string $primaryKey = 'review_id';

    // ───────────── Quản trị đánh giá (agentD2) ─────────────

    /** Thêm hoặc cập nhật phản hồi của cửa hàng cho một đánh giá (mỗi đánh giá một phản hồi). */
    public function upsertForReview(int $reviewId, string $adminUsername, string $reply): int
    {
        return $this->db->execute(
            'INSERT INTO review_replies (review_id, admin_username, reply) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE admin_username = VALUES(admin_username), reply = VALUES(reply), replied_at = CURRENT_TIMESTAMP',
            [$reviewId, $adminUsername, $reply],
            'iss'
        );
    }

    // --- end of methods ---
}
