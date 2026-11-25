<?php

namespace App\Controllers\Admin;

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

        return $data;
    }

    /** Action: hiển thị View admin/chat. */
    public function index(): void
    {
        $this->render('admin/chat', $this->handle());
    }
}
