<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\ReviewReply;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lớp ReviewAdminService - nghiệp vụ trang quản trị đánh giá:
 * danh sách đánh giá có lọc, thống kê số sao và phản hồi của cửa hàng.
 */
class ReviewAdminService
{
    public const MAX_REPLY_LENGTH = 2000;

    private Review $reviews;
    private ReviewReply $replies;
    private ReviewImage $images;
    private Product $products;

    public function __construct(
        ?Review $reviews = null,
        ?ReviewReply $replies = null,
        ?ReviewImage $images = null,
        ?Product $products = null
    ) {
        $this->reviews = $reviews ?? new Review();
        $this->replies = $replies ?? new ReviewReply();
        $this->images = $images ?? new ReviewImage();
        $this->products = $products ?? new Product();
    }

    /**
     * Gửi (hoặc sửa) phản hồi cho đánh giá. Trả về thông báo thành công.
     *
     * @throws InvalidArgumentException|RuntimeException với thông báo tiếng Việt
     */
    public function reply(int $reviewId, string $reply, string $adminName): string
    {
        $reply = trim($reply);
        if ($reply === '') {
            throw new InvalidArgumentException('Vui lòng nhập nội dung phản hồi.');
        }
        if (mb_strlen($reply, 'UTF-8') > self::MAX_REPLY_LENGTH) {
            throw new InvalidArgumentException('Nội dung phản hồi không được vượt quá 2.000 ký tự.');
        }

        try {
            $this->replies->upsertForReview($reviewId, $adminName, $reply);
        } catch (Throwable $e) {
            error_log('Admin review reply failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể gửi phản hồi lúc này.', 0, $e);
        }

        return 'Đã gửi phản hồi cho đánh giá.';
    }

    /** Danh sách sản phẩm (id, name) cho bộ lọc. */
    public function productOptions(): array
    {
        return $this->products->findAllIdNameOrderedByName();
    }

    /**
     * Danh sách đánh giá theo bộ lọc.
     *
     * @return array{reviews: array, reviewImagesByReview: array, reviewsError: string}
     */
    public function listing(int $productFilter, int $ratingFilter, string $search): array
    {
        $reviews = [];
        $reviewImagesByReview = [];
        $reviewsError = '';

        try {
            foreach ($this->reviews->findForAdmin($productFilter, $ratingFilter, $search) as $row) {
                $row['verified'] = $this->reviews->customerPurchasedProduct((int) $row['user_id'], (int) $row['product_id']);
                $reviews[] = $row;
            }
        } catch (Throwable $exception) {
            error_log('Admin review list failed: ' . $exception->getMessage());
            $reviews = [];
            $reviewsError = 'Không thể tải danh sách đánh giá.';
        }

        try {
            $reviewImagesByReview = $this->images->pathsGroupedByReview(array_map(
                static fn(array $review): int => (int) $review['id'],
                $reviews
            ));
        } catch (Throwable $exception) {
            error_log('Admin review image list failed: ' . $exception->getMessage());
            $reviewsError = 'Không thể tải ảnh đánh giá.';
        }

        return [
            'reviews' => $reviews,
            'reviewImagesByReview' => $reviewImagesByReview,
            'reviewsError' => $reviewsError,
        ];
    }

    /**
     * Thống kê toàn bộ đánh giá.
     *
     * @return array{totalReviews: int, averageRating: float, reviewCountByRating: array<int, int>}
     */
    public function summary(): array
    {
        $totalReviews = 0;
        $ratingSum = 0;
        $reviewCountByRating = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($this->reviews->countByRatingForAdmin() as $rating => $total) {
            if ($rating >= 1 && $rating <= 5) {
                $reviewCountByRating[$rating] = $total;
                $ratingSum += $rating * $total;
                $totalReviews += $total;
            }
        }

        return [
            'totalReviews' => $totalReviews,
            'averageRating' => $totalReviews > 0 ? $ratingSum / $totalReviews : 0.0,
            'reviewCountByRating' => $reviewCountByRating,
        ];
    }
}
