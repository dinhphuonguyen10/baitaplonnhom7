<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Lớp MauTraLoiTuDongService - đọc/ghi các mẫu tin nhắn tự động lưu trong
 * config/auto_responses.json (cấu trúc {"templates": [...]}).
 * Việc GỬI tin tự động do TuDongTraLoiService đảm nhiệm, dùng chung file cấu hình này.
 */
class MauTraLoiTuDongService
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
        // Form gửi xuống dòng dạng CRLF; quy về LF để đếm ký tự giống trình duyệt.
        $messages = array_slice(array_map(static fn($v) => trim(str_replace("\r\n", "\n", (string) $v)), $messages), 0, 2);
        $messages = array_pad($messages, 2, '');
        $message = $messages[0];
        if ($title === '' || $message === '') {
            throw new InvalidArgumentException('Vui lòng nhập tiêu đề và nội dung tin nhắn thứ nhất.');
        }

        foreach ($messages as $content) {
            if (mb_strlen($content, 'UTF-8') > TroChuyenService::MAX_MESSAGE_LENGTH) {
                throw new InvalidArgumentException('Mỗi nội dung tin nhắn tối đa 2.000 ký tự.');
            }
        }

        $template['title'] = mb_substr($title, 0, 100, 'UTF-8');
        $template['trigger_label'] = mb_substr($trigger, 0, 200, 'UTF-8');
        $template['message'] = $message;
        $template['messages'] = $messages;
        $template = $this->withStatus($template, $active);

        return $template;
    }

    /** Gán trạng thái và mốc thời gian cập nhật/kích hoạt cho mẫu. */
    private function withStatus(array $template, bool $active): array
    {
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d H:i:s');
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

    /**
     * Bật/tắt một mẫu trên bản cấu hình mới nhất trong file.
     *
     * @return bool|null null nếu không có mẫu, false nếu không ghi được file
     */
    public function setStatus(string $templateId, bool $active): ?bool
    {
        $config = $this->loadConfig();
        $index = $this->findTemplateIndex($config, $templateId);
        if ($index === null) {
            return null;
        }
        $config['templates'][$index] = $this->withStatus($config['templates'][$index], $active);

        return $this->saveConfig($config);
    }

    /** Ghi cấu hình (có khóa file), trả về true nếu thành công. */
    public function saveConfig(array $config): bool
    {
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false && @file_put_contents($this->configPath, $json . PHP_EOL, LOCK_EX) !== false;
    }

    /** Hai biến thể nội dung của mẫu (để hiển thị trong trình soạn). */
    public static function variants(?array $template): array
    {
        $variants = $template ? (array) ($template['messages'] ?? [$template['message'] ?? '', '']) : ['', ''];

        return array_pad(array_slice($variants, 0, 2), 2, '');
    }
}
