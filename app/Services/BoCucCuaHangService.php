<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Session;
use App\Core\Url;
use App\Models\YeuThich;

/**
 * Lớp BoCucCuaHangService - dựng dữ liệu chung cho layout cửa hàng (app/Views/layouts/dau_trang.php):
 * tài khoản đang đăng nhập, menu danh mục, số sản phẩm yêu thích, logo.
 */
class BoCucCuaHangService
{
    /** Các trang thuộc mục "Danh mục sản phẩm" trên menu. */
    public const CATEGORY_PAGES = ['bong_tai.php', 'day_chuyen.php', 'nhan.php', 'vong_tay.php', 'danh_muc.php', 'san_pham.php'];

    /** Slug danh mục có trang riêng. */
    public const CATEGORY_PAGE_MAP = [
        'bong-tai' => '/client/bong_tai.php',
        'day-chuyen' => '/client/day_chuyen.php',
        'nhan' => '/client/nhan.php',
        'vong-tay' => '/client/vong_tay.php',
        'set-qua-tang' => '/client/danh_muc.php?slug=set-qua-tang',
    ];

    private DanhSachSanPhamService $catalog;
    private YeuThich $wishlists;
    private AnhBiaTrangChuService $banners;

    public function __construct(
        ?DanhSachSanPhamService $catalog = null,
        ?YeuThich $wishlists = null,
        ?AnhBiaTrangChuService $banners = null
    ) {
        $this->catalog = $catalog ?? new DanhSachSanPhamService();
        $this->wishlists = $wishlists ?? new YeuThich();
        $this->banners = $banners ?? new AnhBiaTrangChuService();
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
                    : $appBaseUrl . '/client/danh_muc.php?slug=' . urlencode($category['slug']),
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
