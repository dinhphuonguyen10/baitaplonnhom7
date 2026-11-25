<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Services\YeuThichService;

/**
 * Controller trang sản phẩm yêu thích (client/yeu_thich.php) - phân trang 15 sản phẩm/trang.
 */
class DanhSachYeuThichController extends BaseClientController
{
    private YeuThichService $wishlist;

    public function __construct(?YeuThichService $wishlist = null)
    {
        $this->wishlist = $wishlist ?? new YeuThichService();
    }

    protected function indexData(): array
    {
        $page = Request::query('page');
        $currentPage = $page !== null ? max(1, (int) (is_scalar($page) ? $page : 0)) : 1;
        $userId = Auth::customerId() ?? 0;

        return array_merge([
            'pageTitle' => 'Sản phẩm yêu thích - ÉCRIN JEWELRY',
            'userId' => $userId,
            'escape' => static fn($value): string => View::e($value),
            'itemsPerPage' => YeuThichService::ITEMS_PER_PAGE,
            'currentPage' => $currentPage,
        ], $this->wishlist->page($userId, $currentPage));
    }

    /** Action: hiển thị View client/wishlist. */
    public function index(): void
    {
        $this->render('client/yeu_thich', $this->indexData());
    }
}
