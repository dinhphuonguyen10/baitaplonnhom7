<?php

namespace App\Controllers\Client;

use App\Services\DanhSachSanPhamService;
use App\Services\AnhBiaTrangChuService;

/**
 * Controller trang chủ cửa hàng (index.php): banner trình chiếu và các ô danh mục.
 * Danh sách sản phẩm của các khối trang chủ do JavaScript tải qua api/catalog/lay_san_pham.php.
 */
class TrangChuController extends BaseClientController
{
    /** Ảnh đại diện cho từng danh mục trên trang chủ. */
    private const CATEGORY_ICONS = \App\Services\DanhMucService::DEFAULT_IMAGES;

    /** Danh mục có trang riêng (đường dẫn tương đối từ trang chủ). */
    private const CATEGORY_LINKS = [
        'bong-tai' => 'client/bong_tai.php',
        'day-chuyen' => 'client/day_chuyen.php',
        'nhan' => 'client/nhan.php',
        'vong-tay' => 'client/vong_tay.php',
    ];

    private AnhBiaTrangChuService $banners;
    private DanhSachSanPhamService $catalog;

    public function __construct(?AnhBiaTrangChuService $banners = null, ?DanhSachSanPhamService $catalog = null)
    {
        $this->banners = $banners ?? new AnhBiaTrangChuService();
        $this->catalog = $catalog ?? new DanhSachSanPhamService();
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
    protected function categoryTiles(array $navCategories, string $appBaseUrl): array
    {
        $tiles = [];
        foreach ($this->catalog->visibleCategories($navCategories) as $category) {
            $slug = $category['slug'];
            // Ưu tiên ảnh danh mục do quản trị đặt (URL tuyệt đối hoặc đường dẫn trong assets).
            $categoryImage = trim((string) ($category['image'] ?? ''));
            $image = $categoryImage !== ''
                ? (preg_match('~^https?://~i', $categoryImage) ? $categoryImage : $appBaseUrl . '/' . ltrim($categoryImage, '/'))
                : (self::CATEGORY_ICONS[$slug] ?? 'assets/banner/logo.png');
            $tiles[] = [
                'name' => $category['name'],
                'image' => $image,
                'link' => self::CATEGORY_LINKS[$slug] ?? ('client/danh_muc.php?slug=' . urlencode($slug)),
            ];
        }

        return $tiles;
    }

    /** Action: hiển thị trang chủ (View client/home). */
    public function index(): void
    {
        $data = $this->shareLayoutData($this->handle());
        $data['homeCategories'] = $this->categoryTiles($data['navCategories'], (string) $data['appBaseUrl']);
        \App\Core\View::render('client/trang_chu', $data);
    }
}
