<?php

namespace App\Controllers\Admin;

/**
 * Lớp TroChuyenController (quản trị) - trang admin/tro_chuyen.php: giao diện trò chuyện với khách hàng.
 * Dữ liệu hội thoại được tải qua API api/chat/tro_chuyen_quan_tri.php bằng JavaScript.
 */
class TroChuyenController extends BaseAdminController
{
    /** Dữ liệu hiển thị cho trang chat. */
    protected function handle(): array
    {
        $data = $this->layoutData('tro_chuyen.php');
        if ($data['adminUsername'] === '') {
            $data['adminUsername'] = 'Nhân viên ÉCRIN';
        }

        return $data;
    }

    /** Action: hiển thị View admin/chat. */
    public function index(): void
    {
        $this->render('admin/tro_chuyen', $this->handle());
    }
}
