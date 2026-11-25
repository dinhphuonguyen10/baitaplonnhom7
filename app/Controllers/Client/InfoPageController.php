<?php

namespace App\Controllers\Client;

use App\Content\InfoTopics;
use App\Core\Request;

/**
 * Controller trang thông tin (client/thong-tin.php?topic=...): trả về 404 khi chủ đề không tồn tại.
 */
class InfoPageController extends BaseClientController
{
    protected function show(): array
    {
        $topicKey = Request::query('topic');
        $topic = is_string($topicKey) ? InfoTopics::find($topicKey) : null;
        http_response_code($topic === null ? 404 : 200);

        return [
            'topic' => $topic,
            'pageTitle' => ($topic['title'] ?? 'Không tìm thấy nội dung') . ' - ÉCRIN JEWELRY',
            'bodyClass' => 'info-page',
        ];
    }

    /** Action: hiển thị View client/info. */
    public function index(): void
    {
        $this->render('client/info', $this->show());
    }
}
