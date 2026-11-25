<?php

namespace App\Controllers\Client;

use App\Core\Request;
use App\Core\Url;
use App\Services\DanhSachSanPhamService;

/**
 * Controller các trang danh mục sản phẩm:
 *  - Trang cố định: bong_tai.php, day_chuyen.php, nhan.php, vong_tay.php
 *  - Trang động: danh_muc.php?slug=...
 * Sản phẩm trong trang do JavaScript tải qua api/catalog/lay_san_pham.php (data-db-category).
 */
class DanhMucSanPhamController extends BaseClientController
{
    private DanhSachSanPhamService $catalog;

    public function __construct(?DanhSachSanPhamService $catalog = null)
    {
        $this->catalog = $catalog ?? new DanhSachSanPhamService();
    }

    /** Dữ liệu cho trang danh mục cố định. */
    protected function fixedData(string $title, string $key): array
    {
        return $this->viewData($title, $key);
    }

    /** Dữ liệu cho danh_muc.php: tìm danh mục theo slug / tên / ID trên query string. */
    protected function bySlugData(): array
    {
        $slug = Request::string('slug', '', 'query');
        $title = 'Danh mục sản phẩm';
        $key = $slug;

        $category = $this->catalog->findCategory($slug);
        if ($category) {
            $title = $category['name'];
            $key = $category['slug'];
        }

        return $this->viewData($title, $key);
    }

    private function viewData(string $title, string $key): array
    {
        return [
            'categoryTitle' => $title,
            'categoryKey' => $key,
            'pageTitle' => $title . ' - ÉCRIN JEWELRY',
        ];
    }

    /**
     * Danh mục đã ngừng hoạt động / bị xóa mềm (hoặc slug không tồn tại) thì quay về trang chủ.
     * Slug rỗng (danh_muc.php không có ?slug) vẫn hiển thị trang danh mục chung.
     */
    private function renderCategoryPage(array $data): void
    {
        $key = (string) $data['categoryKey'];
        if ($key !== '' && !$this->catalog->isActiveCategorySlug($key, $this->catalog->navigationCategories())) {
            $this->redirect(Url::to('/index.php'));
        }

        $this->render('client/danh_muc', $data);
    }

    /** Action: hiển thị View client/category. */
    public function fixed(string $title, string $key): void
    {
        $this->renderCategoryPage($this->fixedData($title, $key));
    }

    /** Action: hiển thị View client/category. */
    public function bySlug(): void
    {
        $this->renderCategoryPage($this->bySlugData());
    }
}
