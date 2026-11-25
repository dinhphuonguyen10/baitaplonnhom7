<?php

namespace App\Controllers\Client;

use App\Content\ChuDeThongTin;
use App\Core\Request;
use App\Services\DanhSachSanPhamService;

/**
 * Controller trang thông tin (client/thong-tin.php?topic=...): trả về 404 khi chủ đề không tồn tại.
 */
class ThongTinController extends BaseClientController
{
    /** Liên kết tới trang danh mục => slug danh mục cần còn hoạt động thì mới hiển thị. */
    private const CATEGORY_ACTION_SLUGS = [
        '/client/bong_tai.php' => 'bong-tai',
        '/client/day_chuyen.php' => 'day-chuyen',
        '/client/nhan.php' => 'nhan',
        '/client/vong_tay.php' => 'vong-tay',
        '/client/danh_muc.php?slug=set-qua-tang' => 'set-qua-tang',
    ];

    private DanhSachSanPhamService $catalog;

    public function __construct(?DanhSachSanPhamService $catalog = null)
    {
        $this->catalog = $catalog ?? new DanhSachSanPhamService();
    }

    protected function show(): array
    {
        $topicKey = Request::query('topic');
        $topic = is_string($topicKey) ? ChuDeThongTin::find($topicKey) : null;
        http_response_code($topic === null ? 404 : 200);

        return [
            'topic' => $topic,
            'topicActions' => $this->availableActions($topic['actions'] ?? []),
            'pageTitle' => ($topic['title'] ?? 'Không tìm thấy nội dung') . ' - ÉCRIN JEWELRY',
            'bodyClass' => 'info-page',
        ];
    }

    /** Ẩn các liên kết tới danh mục đã ngừng hoạt động / bị xóa mềm. */
    private function availableActions(array $actions): array
    {
        if ($actions === []) {
            return [];
        }
        $navCategories = $this->catalog->navigationCategories();

        return array_values(array_filter($actions, function (array $action) use ($navCategories): bool {
            $requiredSlug = self::CATEGORY_ACTION_SLUGS[$action['href']] ?? null;

            return $requiredSlug === null || $this->catalog->isActiveCategorySlug($requiredSlug, $navCategories);
        }));
    }

    /** Action: hiển thị View client/info. */
    public function index(): void
    {
        $this->render('client/thong_tin', $this->show());
    }
}