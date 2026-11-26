<?php

namespace App\Services;

use App\Core\Database;
use App\Core\DatabaseException;
use App\Models\DanhGia;
use App\Models\AnhDanhGia;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Lớp DanhGiaService - nghiệp vụ đánh giá sản phẩm của khách hàng:
 *  - Kiểm tra ảnh tải lên (JPG/PNG/WEBP, tối đa 5 ảnh, 5 MB, 50 MP)
 *  - Gửi đánh giá mới (kèm ảnh) hoặc sửa đánh giá một lần
 *  - Tổng hợp số sao, phân trang danh sách đánh giá cho trang sản phẩm
 *
 * Lỗi hiển thị cho khách được ném dưới dạng DomainException.
 */
class DanhGiaService
{
    public const PAGE_SIZE = 10;
    public const MAX_IMAGES = 5;
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    public const UPLOAD_RELATIVE_DIR = 'assets/uploads/reviews';

    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
        'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
        'image/webp' => ['extension' => 'webp', 'type' => IMAGETYPE_WEBP],
    ];

    private Database $db;
    private DanhGia $reviews;
    private AnhDanhGia $images;
    private string $uploadDirectory;

    public function __construct(?Database $db = null, ?string $uploadDirectory = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->reviews = new DanhGia($this->db);
        $this->images = new AnhDanhGia($this->db);
        $this->uploadDirectory = $uploadDirectory ?? APP_ROOT . '/' . self::UPLOAD_RELATIVE_DIR;
    }

    // ─────────────────────────── Ảnh tải lên ───────────────────────────

    /**
     * Kiểm tra danh sách ảnh $_FILES['images'] (hoặc null nếu không gửi).
     *
     * @return array<int, array{temporary_path:string, extension:string}>
     */
    public function validateUploadedImages(?array $uploadedFiles): array
    {
        if ($uploadedFiles === null) {
            return [];
        }

        if (!isset($uploadedFiles['name'], $uploadedFiles['error'], $uploadedFiles['tmp_name'])
            || !is_array($uploadedFiles['name'])
            || !is_array($uploadedFiles['error'])
            || !is_array($uploadedFiles['tmp_name'])) {
            throw new DomainException('Danh sách ảnh tải lên không hợp lệ.');
        }

        $fileCount = count($uploadedFiles['name']);
        if ($fileCount > self::MAX_IMAGES) {
            throw new DomainException('Bạn chỉ có thể đính kèm tối đa 5 ảnh.');
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            error_log('Unable to initialize fileinfo for product review images.');
            throw new DomainException('Máy chủ chưa thể kiểm tra ảnh tải lên.');
        }

        $candidates = [];
        try {
            for ($fileIndex = 0; $fileIndex < $fileCount; $fileIndex++) {
                $uploadError = $uploadedFiles['error'][$fileIndex] ?? UPLOAD_ERR_NO_FILE;
                if ($uploadError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($uploadError !== UPLOAD_ERR_OK) {
                    throw new DomainException(
                        $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
                            ? 'Mỗi ảnh không được vượt quá 5 MB.'
                            : 'Không thể nhận ảnh tải lên. Vui lòng thử lại.'
                    );
                }

                $temporaryPath = $uploadedFiles['tmp_name'][$fileIndex] ?? '';
                if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                    throw new DomainException('Tệp ảnh tải lên không hợp lệ.');
                }
                $fileSize = filesize($temporaryPath);
                if ($fileSize === false || $fileSize < 1 || $fileSize > self::MAX_IMAGE_BYTES) {
                    throw new DomainException('Mỗi ảnh phải có dung lượng tối đa 5 MB.');
                }

                $detectedMime = finfo_file($fileInfo, $temporaryPath);
                $imageInfo = getimagesize($temporaryPath);
                if (!is_string($detectedMime)
                    || !isset(self::ALLOWED_IMAGE_TYPES[$detectedMime])
                    || $imageInfo === false
                    || ($imageInfo['mime'] ?? '') !== $detectedMime
                    || (int) $imageInfo[0] > 16000
                    || (int) $imageInfo[1] > 16000
                    || (int) $imageInfo[0] * (int) $imageInfo[1] > 50000000
                    || (int) ($imageInfo[2] ?? 0) !== self::ALLOWED_IMAGE_TYPES[$detectedMime]['type']) {
                    throw new DomainException('Chỉ nhận ảnh JPG, PNG hoặc WEBP hợp lệ (tối đa 50 MP).');
                }

                $candidates[] = [
                    'temporary_path' => $temporaryPath,
                    'extension' => self::ALLOWED_IMAGE_TYPES[$detectedMime]['extension'],
                ];
            }
        } finally {
            finfo_close($fileInfo);
        }

        return $candidates;
    }

    // ─────────────────────────── Gửi / sửa đánh giá ───────────────────────────

    /**
     * Gửi đánh giá mới hoặc sửa đánh giá (một lần). Trả về thông báo thành công.
     *
     * @param array<int, array{temporary_path:string, extension:string}> $imageCandidates
     */
    public function submit(
        int $userId,
        int $productId,
        string $action,
        int $rating,
        string $comment,
        $submittedReviewId,
        array $imageCandidates
    ): string {
        try { // xác minh khách đã mua sản phẩm
            $hasPurchased = $this->reviews->customerPurchasedProduct($userId, $productId);
        } catch (RuntimeException $exception) {
            error_log($exception->getMessage());
            throw new DomainException('Chưa thể xác minh đơn hàng. Vui lòng thử lại sau.');
        }
        if (!$hasPurchased) {  // khách chưa mua sản phẩm trong đơn đã giao thành công
            throw new DomainException('Bạn chỉ có thể đánh giá sản phẩm trong đơn hàng đã giao thành công.');
        }

        try {
            $existingReview = $this->reviews->findByProductAndUser($productId, $userId);
        } catch (Throwable $exception) {
            error_log('Không thể kiểm tra đánh giá đã tồn tại: ' . $exception->getMessage());
            throw new DomainException('Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
        }

        $storedComment = $comment === '' ? null : $comment; // lưu null nếu khách không gửi nội dung đánh giá

        if ($action === 'update') { // khách muốn sửa đánh giá (chỉ một lần)
            return $this->updateOnce($existingReview, $submittedReviewId, $userId, $productId, $rating, $storedComment, $imageCandidates);
        }

        if ($existingReview) { // khách đã gửi đánh giá trước đó, không cho gửi thêm
            throw new DomainException('Bạn đã gửi đánh giá cho sản phẩm này rồi.');
        }

        $this->create($userId, $productId, $rating, $storedComment, $imageCandidates); //ham tạo đánh giá mới, lưu vào CSDL và gửi tin cảm ơn

        return 'Cảm ơn bạn đã chia sẻ đánh giá về sản phẩm!';
    }
     // ─────────────────────────── Sửa đánh giá một lần ───────────────────────────
    private function updateOnce(
        ?array $existingReview,
        $submittedReviewId,
        int $userId,
        int $productId,
        int $rating,
        ?string $storedComment,
        array $imageCandidates
    ): string {
        if (!$existingReview
            || $submittedReviewId === false
            || (int) $existingReview['id'] !== $submittedReviewId) {
            throw new DomainException('Không tìm thấy đánh giá để chỉnh sửa.');
        }
        if ((int) $existingReview['edit_count'] >= 1) {
            throw new DomainException('Bạn đã sử dụng lượt chỉnh sửa đánh giá này.');
        }
        if ($imageCandidates !== []) {
            throw new DomainException('Ảnh đã gửi được giữ nguyên khi chỉnh sửa đánh giá.');
        }
        // Không đổi gì thì giữ lại lượt sửa và phản hồi của cửa hàng.
        $currentComment = trim(str_replace("\r\n", "\n", (string) ($existingReview['comment'] ?? '')));
        if ((int) $existingReview['rating'] === $rating && $currentComment === (string) $storedComment) {
            throw new DomainException('Bạn chưa thay đổi gì nên lượt chỉnh sửa vẫn được giữ nguyên.');
        }
         // Cập nhật đánh giá trong CSDL; nếu lỗi thì ném DomainException
        try { 
            $updated = $this->reviews->updateByCustomerOnce(
                (int) $submittedReviewId,
                $productId,
                $userId,
                $rating,
                $storedComment
            ) === 1;
        } catch (Throwable $exception) {
            error_log('Không thể cập nhật đánh giá: ' . $exception->getMessage());
            throw new DomainException('Chưa thể cập nhật đánh giá lúc này. Vui lòng thử lại sau.');
        }
        if (!$updated) {
            throw new DomainException('Đánh giá đã được chỉnh sửa hoặc không còn khả dụng.');
        }

        // Nội dung đã thay đổi nên phản hồi cũ của cửa hàng không còn phù hợp.
        try {
            $this->db->execute('DELETE FROM review_replies WHERE review_id = ?', [(int) $submittedReviewId], 'i');
        } catch (Throwable $exception) {
            error_log('Không thể xóa phản hồi cũ của đánh giá: ' . $exception->getMessage());
        }

        return 'Đã cập nhật đánh giá. Bạn không thể sửa lại lần nữa.';
    }
        
    // ─────────────────────────── Tạo đánh giá mới ───────────────────────────
    private function create(int $userId, int $productId, int $rating, ?string $storedComment, array $imageCandidates): void
    {
        $storedImagePaths = []; 
        try {
            if ($imageCandidates !== []) {  // khách gửi ảnh đánh giá, lưu vào thư mục uploads/reviews
                if (!is_dir($this->uploadDirectory) 
                    && !mkdir($this->uploadDirectory, 0750, true)
                    && !is_dir($this->uploadDirectory)) { 
                    throw new RuntimeException('Không thể tạo thư mục lưu ảnh đánh giá.'); // tạo thư mục nếu chưa tồn tại, quyền 0750
                }
                foreach ($imageCandidates as $candidate) { //tạo tên file ngẫu nhiên, lưu vào thư mục uploads/reviews, thêm vào mảng $storedImagePaths
                    $storedName = bin2hex(random_bytes(24)) . '.' . $candidate['extension'];
                    $storedPath = $this->uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
                    if (!move_uploaded_file($candidate['temporary_path'], $storedPath)) { // di chuyển ảnh từ thư mục tạm sang thư mục uploads/reviews
                        throw new RuntimeException('Không thể lưu ảnh đánh giá.');
                    }
                    $storedImagePaths[] = self::UPLOAD_RELATIVE_DIR . '/' . $storedName; //Đường dẫn này sẽ được sử dụng để lưu thông tin ảnh vào database.
                }
            }
               // Lưu đánh giá và ảnh vào CSDL trong một transaction; nếu lỗi thì rollback và xóa ảnh đã lưu.
            $this->db->beginTransaction(); 
            $reviewId = $this->reviews->createForCustomer($productId, $userId, $rating, $storedComment);
            foreach ($storedImagePaths as $sortOrder => $imagePath) {
                $this->images->createForReview($reviewId, $imagePath, $sortOrder);
            }
            $this->db->commit();
        } catch (Throwable $exception) { // nếu có lỗi thì rollback và xóa ảnh đã lưu
            $this->db->rollback();
            $this->removeStoredImages($storedImagePaths);
            if ($exception instanceof DatabaseException && (int) $exception->getCode() === 1062) {
                throw new DomainException('Bạn đã gửi đánh giá cho sản phẩm này rồi.');
            }

            error_log('Không thể lưu đánh giá sản phẩm: ' . $exception->getMessage());
            throw new DomainException('Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
        }

        // Chỉ gửi cảm ơn sau khi đánh giá đã lưu thành công; lỗi ở đây không được ảnh hưởng đánh giá.
        try {
            (new TuDongTraLoiService($this->db))->send($userId, 'review_thankyou');
        } catch (Throwable $exception) {
            error_log('Không thể gửi tin cảm ơn đánh giá: ' . $exception->getMessage());
        }
    }
    // Xóa các ảnh đã lưu trong thư mục uploads/reviews nếu có lỗi xảy ra.
    private function removeStoredImages(array $storedImagePaths): void
    {
        foreach ($storedImagePaths as $imagePath) {
            $storedFile = $this->uploadDirectory . DIRECTORY_SEPARATOR . basename($imagePath);
            if (is_file($storedFile)) {
                unlink($storedFile);
            }
        }
    }

    // ─────────────────────────── Khối đánh giá trên trang sản phẩm ───────────────────────────

    /**
     * Dữ liệu tổng hợp + một trang đánh giá của sản phẩm.
     *
     * @param mixed $requestedRating giá trị ?rating= (đã qua FILTER_VALIDATE_INT)
     * @param mixed $requestedPage giá trị ?page= (đã qua FILTER_VALIDATE_INT)
     */
    public function productSection(int $productId, ?int $customerId, $requestedRating, $requestedPage): array
    {
        $summary = $this->reviews->summaryForProduct($productId); //gọi Model lấy số liệu tổng hợp của sản phẩm.

        $reviewCount = (int) ($summary['review_count'] ?? 0);//tổng số đánh giá
        $averageRating = min(5.0, max(0.0, (float) ($summary['average_rating'] ?? 0)));
        $ratingCounts = [
            5 => (int) ($summary['five_star_count'] ?? 0),
            4 => (int) ($summary['four_star_count'] ?? 0),
            3 => (int) ($summary['three_star_count'] ?? 0),
            2 => (int) ($summary['two_star_count'] ?? 0),
            1 => (int) ($summary['one_star_count'] ?? 0),
        ]; // lấy điểm trung bình, kẹp trong khoảng 0 đến 5 để không bao giờ ra giá trị lạ.
         
        $reviewFilter = ($requestedRating !== false && $requestedRating !== null && $requestedRating >= 1 && $requestedRating <= 5)
            ? $requestedRating 
            : 0; 
        // 0 = không lọc theo số sao, 1-5 = lọc theo số sao tương ứng
        
        $matchingReviewCount = $reviewFilter > 0 ? $ratingCounts[$reviewFilter] : $reviewCount;
        $reviewPageCount = max(1, (int) ceil($matchingReviewCount / self::PAGE_SIZE));
        $currentReviewPage = ($requestedPage !== false && $requestedPage !== null && $requestedPage > 0)
            ? min($requestedPage, $reviewPageCount)
            : 1;
        $reviewOffset = ($currentReviewPage - 1) * self::PAGE_SIZE;

        $reviewRows = $this->reviews->pageForProduct($productId, $reviewFilter, self::PAGE_SIZE, $reviewOffset);
        $reviewImagesByReview = $this->images->pathsGroupedByReview(
            array_map(static fn(array $review): int => (int) $review['id'], $reviewRows)
        );

        $isReviewCustomer = $customerId !== null && $customerId > 0;
        $canReviewProduct = $isReviewCustomer && $this->reviews->customerPurchasedProduct($customerId, $productId);
        $ownReview = null;
        if ($isReviewCustomer && $canReviewProduct) {
            $ownReview = $this->reviews->findByProductAndUser($productId, $customerId);
        }
        $ownReviewVisible = false;
        if ($ownReview !== null) {
            foreach ($reviewRows as $reviewRow) {
                if ((int) $reviewRow['id'] === (int) $ownReview['id']) {
                    $ownReviewVisible = true;
                    break;
                }
            }
        }

        $paginationStart = max(1, $currentReviewPage - 2);
        $paginationEnd = min($reviewPageCount, $paginationStart + 4);
        $paginationStart = max(1, $paginationEnd - 4);
        // đảm bảo luôn hiển thị 5 trang nếu có thể, và không vượt quá số trang thực tế.
        return [
            'reviewProductId' => $productId,
            'reviewCount' => $reviewCount,
            'averageRating' => $averageRating,
            'roundedAverageStars' => min(5, max(0, (int) round($averageRating))),
            'ratingCounts' => $ratingCounts,
            'reviewFilter' => $reviewFilter,
            'reviewPageCount' => $reviewPageCount,
            'currentReviewPage' => $currentReviewPage,
            'reviewRows' => $reviewRows,
            'reviewImagesByReview' => $reviewImagesByReview,
            'isReviewCustomer' => $isReviewCustomer,
            'canReviewProduct' => $canReviewProduct,
            'hasReviewedProduct' => $ownReview !== null,
            'ownReview' => $ownReview,
            'ownReviewVisible' => $ownReviewVisible,
            'paginationStart' => $paginationStart,
            'paginationEnd' => $paginationEnd,
        ]; // trả về dữ liệu cho khối đánh giá trên trang sản phẩm. gom tất cả vào một mảng trả về cho Controller.
    }
}
