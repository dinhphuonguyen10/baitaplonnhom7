<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Lớp AutoResponseService - đọc/ghi các mẫu tin nhắn tự động lưu trong
 * config/auto_responses.json (cấu trúc {"templates": [...]}).
 * Việc GỬI tin tự động do AutoReplyService đảm nhiệm, dùng chung file cấu hình này.
 */
class AutoResponseService
{
    private string $configPath;

    public function __construct(?string $configPath = null)
    {
        $this->configPath = $configPath ?? APP_ROOT . '/config/auto_responses.json';
    }

    public function configPath(): string
    {
        return $this->configPath;
    }

    /** Đọc file cấu hình thô (null nếu không có / không hợp lệ). */
    private function readRaw()
    {
        if (!is_file($this->configPath)) {
            return null;
        }

        return json_decode((string) file_get_contents($this->configPath), true);
    }

    /** Cấu hình dạng {"templates": [...]}; trả về danh sách rỗng nếu file không hợp lệ. */
    public function loadConfig(): array
    {
        $loaded = $this->readRaw();
        if (is_array($loaded) && isset($loaded['templates']) && is_array($loaded['templates'])) {
            return $loaded;
        }

        return ['templates' => []];
    }

    /** Vị trí của mẫu có id = $templateId trong danh sách, null nếu không có. */
    public function findTemplateIndex(array $config, string $templateId): ?int
    {
        foreach ($config['templates'] as $index => $item) {
            if ((string) ($item['id'] ?? '') === $templateId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Áp dụng dữ liệu form chỉnh sửa vào mẫu; ném InvalidArgumentException nếu thiếu dữ liệu.
     */
    public function applyChanges(array $template, string $title, string $trigger, array $messages, bool $active): array
    {
        $messages = array_slice(array_map(static fn($v) => trim((string) $v), $messages), 0, 2);
        $messages = array_pad($messages, 2, '');
        $message = $messages[0];
        if ($title === '' || $message === '') {
            throw new InvalidArgumentException('Vui lòng nhập tiêu đề và nội dung tin nhắn thứ nhất.');
        }

        $now = date('Y-m-d H:i:s');
        $template['title'] = mb_substr($title, 0, 100, 'UTF-8');
        $template['trigger_label'] = mb_substr($trigger, 0, 200, 'UTF-8');
        $template['message'] = $message;
        $template['messages'] = $messages;
        $template['status'] = $active ? 'active' : 'inactive';
        $template['updated_at'] = $now;
        if ($active && empty($template['activated_at'])) {
            $template['activated_at'] = $now;
        }
        if (!$active) {
            $template['activated_at'] = null;
        }

        return $template;
    }

    /** Ghi cấu hình (có khóa file), trả về true nếu thành công. */
    public function saveConfig(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false && file_put_contents($this->configPath, $json . PHP_EOL, LOCK_EX) !== false;
    }

    /** Ghi đè toàn bộ cấu hình từ trang danh sách mẫu (API save_auto_responses). */
    public function saveRaw(array $responses): void
    {
        file_put_contents($this->configPath, json_encode($responses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Nội dung tin tự động theo cấu trúc cũ (key => message), rỗng nếu không có. */
    public function legacyMessage(string $type): string
    {
        // Đọc được cả định dạng mới (templates[]) lẫn định dạng cũ (key => message).
        return (new AutoReplyService())->resolveMessage($type);
    }

    /** Hai biến thể nội dung của mẫu (để hiển thị trong trình soạn). */
    public static function variants(?array $template): array
    {
        $variants = $template ? (array) ($template['messages'] ?? [$template['message'] ?? '', '']) : ['', ''];

        return array_pad(array_slice($variants, 0, 2), 2, '');
    }
}
