<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Services\WishlistService;

/**
 * Controller trang sản phẩm yêu thích (client/wishlist.php) - phân trang 15 sản phẩm/trang.
 */
class WishlistPageController extends BaseClientController
{
    private WishlistService $wishlist;

    public function __construct(?WishlistService $wishlist = null)
    {
        $this->wishlist = $wishlist ?? new WishlistService();
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
            'itemsPerPage' => WishlistService::ITEMS_PER_PAGE,
            'currentPage' => $currentPage,
        ], $this->wishlist->page($userId, $currentPage));
    }

    /** Action: hiển thị View client/wishlist. */
    public function index(): void
    {
        $this->render('client/wishlist', $this->indexData());
    }
}
