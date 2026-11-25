<?php

namespace App\Controllers\Admin;

use App\Core\Url;
use App\Services\BannerService;

/**
 * Lớp ChatController (quản trị) - trang admin/chat.php: giao diện trò chuyện với khách hàng.
 * Dữ liệu hội thoại được tải qua API api/chat/admin_chat.php bằng JavaScript.
 */
class ChatController extends BaseAdminController
{
    /** Dữ liệu hiển thị cho trang chat. */
    protected function handle(): array
    {
        $data = $this->layoutData('chat.php');
        if ($data['adminUsername'] === '') {
            $data['adminUsername'] = 'Nhân viên ÉCRIN';
        }
        // Logo dự phòng cho ảnh đại diện, đồng bộ với cấu hình logo.
        $data['chatLogoUrl'] = (new BannerService())->logoUrl(Url::base());

        return $data;
    }

    /** Action: hiển thị View admin/chat. */
    public function index(): void
    {
        $this->render('admin/chat', $this->handle());
    }
}
