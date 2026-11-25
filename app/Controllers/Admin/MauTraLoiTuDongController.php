<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\Url;
use App\Services\MauTraLoiTuDongService;
use InvalidArgumentException;

/**
 * Lớp MauTraLoiTuDongController - quản lý mẫu tin nhắn tự động:
 *  - index(): danh sách mẫu (admin/mau_tra_loi_tu_dong.php)
 *  - edit():  cấu hình một mẫu (admin/sua_mau_tra_loi_tu_dong.php)
 */
class MauTraLoiTuDongController extends BaseAdminController
{
    /** Màu nhãn theo loại mẫu tin. */
    private const TYPE_COLORS = [
        'Chào mừng'    => ['bg' => '#E6F4FF', 'text' => '#0958D9', 'dot' => '#1890FF'],
        'Hành trình đơn'=> ['bg' => '#F9F0FF', 'text' => '#531DAB', 'dot' => '#722ED1'],
        'Chăm sóc'     => ['bg' => '#F6FFED', 'text' => '#237804', 'dot' => '#52C41A'],
        'Tương tác'    => ['bg' => '#FFF7E6', 'text' => '#AD4E00', 'dot' => '#FA8C16'],
    ];

    private const DEFAULT_COLOR = ['bg' => '#F0F0F0', 'text' => '#333', 'dot' => '#999'];

    private MauTraLoiTuDongService $service;

    public function __construct(?MauTraLoiTuDongService $service = null)
    {
        parent::__construct();
        $this->service = $service ?? new MauTraLoiTuDongService();
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
                    Response::redirect(Url::base() . '/admin/sua_mau_tra_loi_tu_dong.php?id=' . rawurlencode($templateId) . '&saved=1');
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
            'variants' => MauTraLoiTuDongService::variants($template),
            'active' => ($template['status'] ?? '') === 'active',
        ];
    }

    private function layout(): array
    {
        $data = $this->layoutData('mau_tra_loi_tu_dong.php');
        if ($data['adminUsername'] === '') {
            $data['adminUsername'] = 'Nhân viên ÉCRIN';
        }

        return $data;
    }

    /** Action: hiển thị View admin/auto_responses. */
    public function index(): void
    {
        $this->render('admin/mau_tra_loi_tu_dong', $this->indexData());
    }

    /** Action: hiển thị View admin/auto_response_edit. */
    public function edit(): void
    {
        $this->render('admin/sua_mau_tra_loi_tu_dong', $this->editData());
    }
}
