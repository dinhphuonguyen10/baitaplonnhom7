<?php

namespace App\Models;

/**
 * Model ReviewImage - ảnh đính kèm đánh giá (bảng `review_images`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class ReviewImage extends BaseModel
{
    protected string $table = 'review_images';

    // ───────────── Ảnh đánh giá phía khách hàng (agentB) ─────────────

    /**
     * Đường dẫn ảnh theo từng đánh giá.
     *
     * @param int[] $reviewIds
     * @return array<int, string[]> review_id => danh sách image_path
     */
    public function pathsGroupedByReview(array $reviewIds): array
    {
        if ($reviewIds === []) {
            return [];
        }

        $reviewIds = array_map('intval', array_values($reviewIds));
        $placeholders = implode(',', array_fill(0, count($reviewIds), '?'));
        $rows = $this->db->fetchAll(
            "SELECT review_id, image_path FROM review_images
         WHERE review_id IN ({$placeholders})
         ORDER BY review_id, sort_order, id",
            $reviewIds,
            str_repeat('i', count($reviewIds))
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['review_id']][] = (string) $row['image_path'];
        }

        return $grouped;
    }

    /** Thêm ảnh cho đánh giá. */
    public function createForReview(int $reviewId, string $imagePath, int $sortOrder): int
    {
        return $this->db->insert(
            'INSERT INTO review_images (review_id, image_path, sort_order) VALUES (?, ?, ?)',
            [$reviewId, $imagePath, $sortOrder],
            'isi'
        );
    }

    // --- end of methods ---
}
