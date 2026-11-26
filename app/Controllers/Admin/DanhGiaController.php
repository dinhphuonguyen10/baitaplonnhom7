<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Services\AnhBiaService;
use App\Services\QuanLyDanhGiaService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controller trang admin/danh_gia.php - xem đánh giá của khách và phản hồi (Post/Redirect/Get).
 */
class DanhGiaController extends BaseAdminController
{
    private const CSRF_KEY = 'reviews_csrf';
    private const NOTICE_KEY = 'review_notice';

    private QuanLyDanhGiaService $reviews;
    private AnhBiaService $banners;

    public function __construct(?Database $db = null, ?QuanLyDanhGiaService $reviews = null, ?AnhBiaService $banners = null)
    {
        parent::__construct($db);
        $this->reviews = $reviews ?? new QuanLyDanhGiaService();
        $this->banners = $banners ?? new AnhBiaService();
    }

    /** Xử lý POST (chuyển hướng) hoặc trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $layout = $this->layoutData('danh_gia.php');
        $csrfToken = $this->csrfToken(self::CSRF_KEY);

        if (Request::isPost()) {
            $result = $this->processPost();
            if ($result['type'] === 'error') {
                // Giữ nội dung đang gõ để mở lại hộp thoại phản hồi.
                $reply = Request::post('reply', '');
                $result['old'] = [
                    'review_id' => (int) Request::post('review_id', 0),
                    'reply' => is_string($reply) ? $reply : '',
                ];
            }
            Session::set(self::NOTICE_KEY, $result);
            // Quay lại đúng bộ lọc đang xem (form phản hồi gửi về URL hiện tại).
            $filters = array_filter([
                'product_id' => (int) Request::query('product_id', 0),
                'rating' => (int) Request::query('rating', 0),
                'q' => trim((string) Request::query('q', '')),
                'status' => $this->replyStatus(),
            ]);
            $this->redirectTo('/admin/danh_gia.php' . ($filters === [] ? '' : '?' . http_build_query($filters)));
        }

        $notice = null;
        $storedNotice = Session::get(self::NOTICE_KEY);
        if (is_array($storedNotice)) {
            $notice = $storedNotice;
            Session::remove(self::NOTICE_KEY);
        }

        $productFilter = (int) Request::query('product_id', 0);
        $ratingFilter = (int) Request::query('rating', 0);
        $search = trim((string) Request::query('q', ''));
        $replyStatus = $this->replyStatus();

        return array_merge(
            $layout,
            [
                'csrfToken' => $csrfToken,
                'notice' => $notice,
                'productFilter' => $productFilter,
                'ratingFilter' => $ratingFilter,
                'search' => $search,
                'replyStatus' => $replyStatus,
                'products' => $this->reviews->productOptions(),
                'fallbackImageUrl' => $this->banners->logoUrl($layout['appBaseUrl']),
            ],
            $this->reviews->listing($productFilter, $ratingFilter, $search, $replyStatus),
            $this->reviews->summary()
        );
    }

    /** Bộ lọc tình trạng phản hồi trên URL: 'pending', 'replied' hoặc '' (tất cả). */
    private function replyStatus(): string
    {
        $status = Request::query('status', '');

        return in_array($status, ['pending', 'replied'], true) ? $status : '';
    }

    /** @return array{type: string, message: string} */
    private function processPost(): array
    {
        $submittedToken = Request::post('csrf_token', '');
        $action = Request::post('action', '');
        $reviewId = filter_var(Request::post('review_id'), FILTER_VALIDATE_INT);

        if (!is_string($submittedToken) || !$this->verifyCsrf($submittedToken, self::CSRF_KEY)) {
            return ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.'];
        }
        if ($reviewId === false || $reviewId < 1) {
            return ['type' => 'error', 'message' => 'Mã đánh giá không hợp lệ.'];
        }
        if ($action !== 'reply') {
            return ['type' => 'error', 'message' => 'Thao tác không được hỗ trợ.'];
        }

        $adminName = (string) (Session::get('admin_username') ?? Session::get('username') ?? 'Quản trị viên');
        try {
            return ['type' => 'success', 'message' => $this->reviews->reply((int) $reviewId, (string) Request::post('reply', ''), $adminName)];
        } catch (InvalidArgumentException | RuntimeException $e) {
            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }

    /** Action: hiển thị View admin/reviews. */
    public function index(): void
    {
        $this->render('admin/danh_gia', $this->handle());
    }
}
