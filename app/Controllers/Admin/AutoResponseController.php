<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\AutoResponseService;
use InvalidArgumentException;

/**
 * Lớp AutoResponseController - quản lý mẫu tin nhắn tự động:
 *  - index(): danh sách mẫu (admin/auto_responses.php)
 *  - edit():  cấu hình một mẫu (admin/auto_response_edit.php)
 */
class AutoResponseController extends BaseAdminController
{
    /** Màu nhãn theo loại mẫu tin. */
    private const TYPE_COLORS = [
        'Chào mừng'    => ['bg' => '#E6F4FF', 'text' => '#0958D9', 'dot' => '#1890FF'],
        'Hành trình đơn'=> ['bg' => '#F9F0FF', 'text' => '#531DAB', 'dot' => '#722ED1'],
        'Chăm sóc'     => ['bg' => '#F6FFED', 'text' => '#237804', 'dot' => '#52C41A'],
        'Tương tác'    => ['bg' => '#FFF7E6', 'text' => '#AD4E00', 'dot' => '#FA8C16'],
    ];

    private const DEFAULT_COLOR = ['bg' => '#F0F0F0', 'text' => '#333', 'dot' => '#999'];

    private AutoResponseService $service;

    public function __construct(?AutoResponseService $service = null)
    {
        parent::__construct();
        $this->service = $service ?? new AutoResponseService();
    }

    /** Dữ liệu trang danh sách mẫu. */
    protected function indexData(): array
    {
        $config = $this->service->loadConfig();

        $rows = [];
        foreach ($config['templates'] as $idx => $tpl) {
            $type = $tpl['type'] ?? 'Chào mừng';
            $rows[$idx] = [
                'tpl' => $tpl,
                'type' => $type,
                'color' => self::TYPE_COLORS[$type] ?? self::DEFAULT_COLOR,
                'isActive' => ($tpl['status'] ?? '') === 'active',
                'updatedAt' => !empty($tpl['updated_at']) ? date('d/m/Y H:i', strtotime((string) $tpl['updated_at'])) : '---',
            ];
        }

        return $this->layout() + [
            'config' => $config,
            'typeColors' => self::TYPE_COLORS,
            'templateRows' => $rows,
        ];
    }

    /** Xử lý trang cấu hình một mẫu (lưu POST rồi chuyển hướng) và trả về dữ liệu hiển thị. */
    protected function editData(): array
    {
        $config = $this->service->loadConfig();
        $templateId = (string) Request::query('id', Request::post('id', ''));
        $templateIndex = $this->service->findTemplateIndex($config, $templateId);
        $template = null;
        if ($templateIndex === null) {
            http_response_code(404);
        } else {
            $template = $config['templates'][$templateIndex];
        }

        $error = '';
        if (Request::isPost() && $template !== null) {
            try {
                $updated = $this->service->applyChanges(
                    $template,
                    trim((string) Request::post('title', '')),
                    trim((string) Request::post('trigger_label', '')),
                    (array) Request::post('messages', []),
                    (string) Request::post('active', '0') === '1'
                );
                $template = $updated;
                $config['templates'][$templateIndex] = $updated;
                if ($this->service->saveConfig($config)) {
                    Response::redirect(Url::base() . '/admin/auto_response_edit.php?id=' . rawurlencode($templateId) . '&saved=1');
                }
                $error = 'Không thể lưu mẫu lúc này. Kiểm tra quyền ghi của config/auto_responses.json.';
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->layout() + [
            'templateId' => $templateId,
            'template' => $template,
            'error' => $error,
            'saved' => !empty(Request::query('saved')),
            'variants' => AutoResponseService::variants($template),
            'active' => ($template['status'] ?? '') === 'active',
        ];
    }

    private function layout(): array
    {
        $data = $this->layoutData('auto_responses.php');
        if ($data['adminUsername'] === '') {
            $data['adminUsername'] = 'Nhân viên ÉCRIN';
        }

        return $data;
    }

    /** Action: hiển thị View admin/auto_responses. */
    public function index(): void
    {
        $this->render('admin/auto_responses', $this->indexData());
    }

    /** Action: hiển thị View admin/auto_response_edit. */
    public function edit(): void
    {
        $this->render('admin/auto_response_edit', $this->editData());
    }
}
