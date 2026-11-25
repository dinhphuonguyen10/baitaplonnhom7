<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Core\Url;
use App\Models\Wishlist;

/**
 * Lớp StorefrontLayoutService - dựng dữ liệu chung cho layout cửa hàng (app/Views/layouts/header.php):
 * tài khoản đang đăng nhập, menu danh mục, số sản phẩm yêu thích, logo.
 */
class StorefrontLayoutService
{
    /** Các trang thuộc mục "Danh mục sản phẩm" trên menu. */
    public const CATEGORY_PAGES = ['bong_tai.php', 'day_chuyen.php', 'nhan.php', 'vong_tay.php', 'category.php', 'product.php'];

    /** Slug danh mục có trang riêng. */
    public const CATEGORY_PAGE_MAP = [
        'bong-tai' => '/client/bong_tai.php',
        'day-chuyen' => '/client/day_chuyen.php',
        'nhan' => '/client/nhan.php',
        'vong-tay' => '/client/vong_tay.php',
        'set-qua-tang' => '/client/category.php?slug=set-qua-tang',
    ];

    private ProductCatalogService $catalog;
    private Wishlist $wishlists;
    private StorefrontBannerService $banners;

    public function __construct(
        ?ProductCatalogService $catalog = null,
        ?Wishlist $wishlists = null,
        ?StorefrontBannerService $banners = null
    ) {
        $this->catalog = $catalog ?? new ProductCatalogService();
        $this->wishlists = $wishlists ?? new Wishlist();
        $this->banners = $banners ?? new StorefrontBannerService();
    }

    /**
     * Dữ liệu cho layout header của cửa hàng.
     *
     * @param mixed $bodyClass giá trị $bodyClass do trang gọi đặt trước (nếu có)
     */
    public function header($bodyClass = null): array
    {
        $appBaseUrl = Url::base();
        $customerUsername = Auth::customerUsername();
        $adminUsernameForClient = Auth::isAdmin() && Session::has('admin_username')
            ? (string) Session::get('admin_username')
            : null;
        $isCustomerLoggedIn = $customerUsername !== null && $customerUsername !== '';

        $navCategories = $this->catalog->navigationCategories();
        $navCategoryLinks = [];
        $visibleNavCategories = $this->catalog->visibleCategories($navCategories);
        foreach ($visibleNavCategories as $category) {
            $navCategoryLinks[] = [
                'href' => isset(self::CATEGORY_PAGE_MAP[$category['slug']])
                    ? $appBaseUrl . self::CATEGORY_PAGE_MAP[$category['slug']]
                    : $appBaseUrl . '/client/category.php?slug=' . urlencode($category['slug']),
                'name' => $category['name'],
            ];
        }

        $customerId = Auth::customerId();

        return [
            'appBaseUrl' => $appBaseUrl,
            'navCurrentPage' => Url::currentPage(),
            'navCategoryPages' => self::CATEGORY_PAGES,
            'bodyClass' => is_string($bodyClass) ? $bodyClass : '',
            'customerUsername' => $customerUsername,
            'adminUsernameForClient' => $adminUsernameForClient,
            'accountUsername' => $customerUsername ?? $adminUsernameForClient,
            'accountRole' => $customerUsername !== null ? 'customer' : ($adminUsernameForClient !== null ? 'admin' : null),
            'isCustomerLoggedIn' => $isCustomerLoggedIn,
            'navCategories' => $navCategories,
            'navCategoryLinks' => $navCategoryLinks,
            // Danh mục đang hoạt động, bỏ slug nội bộ - dùng cho ô chọn danh mục ở bộ lọc / tìm kiếm.
            'visibleNavCategories' => $visibleNavCategories,
            'initialWishlistCount' => $isCustomerLoggedIn && $customerId !== null ? $this->wishlists->countForUser($customerId) : 0,
            'headerLogoUrl' => $this->banners->logoUrl($appBaseUrl),
        ];
    }
}
