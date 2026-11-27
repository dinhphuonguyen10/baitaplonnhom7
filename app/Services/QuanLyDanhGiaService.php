<?php

namespace App\Services;

use App\Models\SanPham;
use App\Models\DanhGia;
use App\Models\AnhDanhGia;
use App\Models\PhanHoiDanhGia;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lớp QuanLyDanhGiaService - nghiệp vụ trang quản trị đánh giá:
 * danh sách đánh giá có lọc, thống kê số sao và phản hồi của cửa hàng.
 */
class QuanLyDanhGiaService
{
    public const MAX_REPLY_LENGTH = 2000;

    private DanhGia $reviews;
    private PhanHoiDanhGia $replies;
    private AnhDanhGia $images;
    private SanPham $products;

    public function __construct(
        ?DanhGia $reviews = null,
        ?PhanHoiDanhGia $replies = null,
        ?AnhDanhGia $images = null,
        ?SanPham $products = null
    ) {
        $this->reviews = $reviews ?? new DanhGia();
        $this->replies = $replies ?? new PhanHoiDanhGia();
        $this->images = $images ?? new AnhDanhGia();
        $this->products = $products ?? new SanPham();
    }

    /**
     * Gửi (hoặc sửa) phản hồi cho đánh giá. Trả về thông báo thành công.
     *
     * @throws InvalidArgumentException|RuntimeException với thông báo tiếng Việt
     */
    public function reply(int $reviewId, string $reply, string $adminName): string
    {
        // Form gửi xuống dòng dạng CRLF; quy về LF để đếm ký tự giống trình duyệt.
        $reply = trim(str_replace("\r\n", "\n", $reply));
        if ($reply === '') {
            throw new InvalidArgumentException('Vui lòng nhập nội dung phản hồi.');
        }
        if (mb_strlen($reply, 'UTF-8') > self::MAX_REPLY_LENGTH) {
            throw new InvalidArgumentException('Nội dung phản hồi không được vượt quá 2.000 ký tự.');
        }

        try {
            $exists = $this->reviews->find($reviewId) !== null;
        } catch (Throwable $e) {
            error_log('Admin review lookup failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể gửi phản hồi lúc này.', 0, $e);
        }
        if (!$exists) {
            throw new InvalidArgumentException('Không tìm thấy đánh giá để phản hồi.');
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
    public function listing(int $productFilter, int $ratingFilter, string $search, string $replyStatus = ''): array
    {
        $reviews = [];
        $reviewImagesByReview = [];
        $reviewsError = '';

        try {
            $reviews = $this->reviews->findForAdmin($productFilter, $ratingFilter, $search, $replyStatus);
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
     * @return array{totalReviews: int, averageRating: float, reviewCountByRating: array<int, int>, pendingReplyCount: int}
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
            'pendingReplyCount' => $this->reviews->countPendingReplyForAdmin(),
        ];
    }
}
