<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Url;
use App\Services\BannerService;
use App\Services\ReviewAdminService;
use InvalidArgumentException;
use RuntimeException;

/**
 * Controller trang admin/reviews.php - xem đánh giá của khách và phản hồi (Post/Redirect/Get).
 */
class ReviewController extends BaseAdminController
{
    private const CSRF_KEY = 'reviews_csrf';
    private const NOTICE_KEY = 'review_notice';

    private ReviewAdminService $reviews;
    private BannerService $banners;

    public function __construct(?Database $db = null, ?ReviewAdminService $reviews = null, ?BannerService $banners = null)
    {
        parent::__construct($db);
        $this->reviews = $reviews ?? new ReviewAdminService();
        $this->banners = $banners ?? new BannerService();
    }

    /** Xử lý POST (chuyển hướng) hoặc trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $layout = $this->layoutData('reviews.php');
        $csrfToken = $this->csrfToken(self::CSRF_KEY);

        if (Request::isPost()) {
            Session::set(self::NOTICE_KEY, $this->processPost());
            $this->redirectTo('/admin/reviews.php');
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

        return array_merge(
            $layout,
            [
                'csrfToken' => $csrfToken,
                'notice' => $notice,
                'productFilter' => $productFilter,
                'ratingFilter' => $ratingFilter,
                'search' => $search,
                'products' => $this->reviews->productOptions(),
                'fallbackImageUrl' => $this->banners->logoUrl($layout['appBaseUrl']),
            ],
            $this->reviews->listing($productFilter, $ratingFilter, $search),
            $this->reviews->summary()
        );
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
        $this->render('admin/reviews', $this->handle());
    }
}
