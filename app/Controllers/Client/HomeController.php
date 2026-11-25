<?php

namespace App\Controllers\Client;

use App\Services\ProductCatalogService;
use App\Services\StorefrontBannerService;

/**
 * Controller trang chủ cửa hàng (index.php): banner trình chiếu và các ô danh mục.
 * Danh sách sản phẩm của các khối trang chủ do JavaScript tải qua api/catalog/get_products.php.
 */
class HomeController extends BaseClientController
{
    /** Ảnh đại diện cho từng danh mục trên trang chủ. */
    private const CATEGORY_ICONS = [
        'bong-tai' => 'assets/bôngtai/bong1.jpg',
        'day-chuyen' => 'assets/dây chuyền/dâychuyen/day1.jpg',
        'nhan' => 'assets/nhẫn/nhan1.png',
        'vong-tay' => 'assets/vòng tay/vong1.png',
        'set-qua-tang' => 'assets/quà/set1.jpg',
    ];

    /** Danh mục có trang riêng (đường dẫn tương đối từ trang chủ). */
    private const CATEGORY_LINKS = [
        'bong-tai' => 'client/bong_tai.php',
        'day-chuyen' => 'client/day_chuyen.php',
        'nhan' => 'client/nhan.php',
        'vong-tay' => 'client/vong_tay.php',
    ];

    private StorefrontBannerService $banners;
    private ProductCatalogService $catalog;

    public function __construct(?StorefrontBannerService $banners = null, ?ProductCatalogService $catalog = null)
    {
        $this->banners = $banners ?? new StorefrontBannerService();
        $this->catalog = $catalog ?? new ProductCatalogService();
    }

    /** Dữ liệu cho view trang chủ (trước khi nạp header). */
    protected function handle(): array
    {
        return [
            'pageTitle' => 'ÉCRIN JEWELRY - Trang chủ',
            'bodyClass' => 'home-page',
            'banners' => $this->banners->homeBanners(),
        ];
    }

    /**
     * Ô danh mục trên trang chủ từ danh sách danh mục của menu (header đã tải).
     *
     * @return array<int, array{name: string, image: string, link: string}>
     */
    protected function categoryTiles(array $navCategories): array
    {
        $tiles = [];
        foreach ($this->catalog->visibleCategories($navCategories) as $category) {
            $slug = $category['slug'];
            $tiles[] = [
                'name' => $category['name'],
                'image' => self::CATEGORY_ICONS[$slug] ?? 'assets/banner/logo.png',
                'link' => self::CATEGORY_LINKS[$slug] ?? ('client/category.php?slug=' . urlencode($slug)),
            ];
        }

        return $tiles;
    }

    /** Action: hiển thị trang chủ (View client/home). */
    public function index(): void
    {
        $data = $this->shareLayoutData($this->handle());
        $data['homeCategories'] = $this->categoryTiles($data['navCategories']);
        \App\Core\View::render('client/home', $data);
    }
}
