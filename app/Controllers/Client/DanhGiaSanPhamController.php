<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DanhGiaService;
use DomainException;

/**
 * Lớp DanhGiaSanPhamController - xử lý gửi / sửa đánh giá (POST client/danhgia.php),
 * sau đó chuyển hướng về trang sản phẩm kèm thông báo.
 * Khối đánh giá trên trang sản phẩm do ChiTietSanPhamController chuẩn bị dữ liệu.
 */
class DanhGiaSanPhamController extends BaseClientController
{
    public const CSRF_KEY = 'product_review_csrf';
    public const FLASH_KEY = 'product_review_flash';

    private DanhGiaService $service;

    public function __construct(?DanhGiaService $service = null)
    {
        $this->service = $service ?? new DanhGiaService();
    }

    /** Action POST client/danhgia.php: gửi / sửa đánh giá (chỉ chấp nhận POST). */
    public function submit(): void
    {
        if (Request::method() !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Phương thức không được hỗ trợ.');
        }

        // Khi request vượt post_max_size, PHP bỏ hết $_POST nên mã sản phẩm được gửi kèm trên URL.
        $productIdInput = Request::post('product_id', Request::query('product_id'));
        $productId = is_scalar($productIdInput) ? filter_var($productIdInput, FILTER_VALIDATE_INT) : false;
        $productId = ($productId !== false && $productId !== null && $productId > 0) ? $productId : 0;

        if ($_POST === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            $this->redirectWithMessage($productId, 'error', 'Ảnh tải lên quá lớn. Mỗi ảnh tối đa 5 MB, tối đa 5 ảnh.');
        }

        try {
            $message = $this->processSubmission($productId);
            $this->redirectWithMessage($productId, 'success', $message);
        } catch (DomainException $e) {
            $this->redirectWithMessage($productId, 'error', $e->getMessage(), $this->submittedInput());
        }
    }

    /** Số sao và nội dung khách vừa nhập, để điền lại form khi gửi lỗi (ảnh phải chọn lại). */
    private function submittedInput(): array
    {
        $rating = Request::post('rating');
        $comment = Request::post('comment');

        return [
            'action' => Request::post('action') === 'update' ? 'update' : 'create',
            'rating' => is_scalar($rating) ? (int) $rating : 0,
            'comment' => is_string($comment) ? mb_substr($comment, 0, 2000, 'UTF-8') : '',
        ];
    }

    /** Kiểm tra dữ liệu form và gửi cho DanhGiaService; trả về thông báo thành công. */
    private function processSubmission(int $productId): string
    {
        $reviewAction = Request::post('action', 'create');
        if (!is_string($reviewAction) || !in_array($reviewAction, ['create', 'update'], true)) {
            throw new DomainException('Thao tác đánh giá không hợp lệ.');
        }

        $userId = Auth::customerId();
        if ($userId === null || $userId <= 0) {
            throw new DomainException('Vui lòng đăng nhập bằng tài khoản khách hàng để gửi đánh giá.');
        }

        $postedToken = Request::post('csrf_token', '');
        if (!Session::verifyCsrf(is_string($postedToken) ? $postedToken : null, self::CSRF_KEY)) {
            throw new DomainException('Phiên gửi đánh giá không hợp lệ. Vui lòng tải lại trang và thử lại.');
        }

        $ratingInput = Request::post('rating');
        $rating = is_scalar($ratingInput) ? filter_var($ratingInput, FILTER_VALIDATE_INT) : false;
        if ($productId <= 0 || $rating === false || $rating === null || $rating < 1 || $rating > 5) {
            throw new DomainException('Thông tin đánh giá không hợp lệ.');
        }

        $comment = Request::post('comment', '');
        if (!is_string($comment)) {
            throw new DomainException('Nội dung đánh giá không hợp lệ.');
        }
        // Quy xuống dòng CRLF của form về LF để đếm ký tự giống trình duyệt.
        $comment = trim(str_replace("\r\n", "\n", $comment));
        if (mb_strlen($comment, 'UTF-8') > 2000) {
            throw new DomainException('Nội dung đánh giá không được vượt quá 2.000 ký tự.');
        }

        $imageCandidates = $this->service->validateUploadedImages(Request::file('images'));

        return $this->service->submit(
            $userId,
            $productId,
            $reviewAction,
            $rating,
            $comment,
            filter_var(Request::post('review_id'), FILTER_VALIDATE_INT),
            $imageCandidates
        );
    }

    private function redirectWithMessage(int $productId, string $type, string $message, array $old = []): void
    {
        Session::set(self::FLASH_KEY, ['type' => $type, 'message' => $message, 'old' => $old]);
        if ($productId <= 0) {
            Response::redirect('../index.php');
        }

        Response::redirect('san_pham.php?id=' . $productId . '#product-reviews');
    }

}
