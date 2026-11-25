<?php

namespace App\Services;

use App\Core\Database;
use App\Database\QuanLyCauTruc;
use Throwable;

/**
 * Lớp TuDongTraLoiService - gửi tin nhắn tự động từ hệ thống (khi khách nhắn tin, đặt hàng...)
 * dựa trên các mẫu trong config/auto_responses.json.
 */
class TuDongTraLoiService
{
    public const BOT_NAME = 'ÉCRIN Jewelry';

    private Database $db;
    private string $configPath;

    public function __construct(?Database $db = null, ?string $configPath = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->configPath = $configPath ?? APP_ROOT . '/config/auto_responses.json';
    }

    /**
     * Chọn nội dung tin nhắn cho mẫu $type (ngẫu nhiên giữa các biến thể), rỗng nếu mẫu tắt/không có.
     * Hỗ trợ cả cấu trúc JSON cũ (key => message) và mới (templates array).
     */
    public function resolveMessage(string $type): string
    {
        if (!is_file($this->configPath)) {
            return '';
        }

        $config = json_decode((string) file_get_contents($this->configPath), true);
        if (!is_array($config)) {
            return '';
        }

        if (isset($config['templates']) && is_array($config['templates'])) {
            foreach ($config['templates'] as $template) {
                if (($template['id'] ?? '') === $type && ($template['status'] ?? '') === 'active') {
                    $variants = array_values(array_filter(
                        (array) ($template['messages'] ?? []),
                        static fn($value) => trim((string) $value) !== ''
                    ));

                    return trim((string) ($variants ? $variants[array_rand($variants)] : ($template['message'] ?? '')));
                }
            }

            return '';
        }

        return trim((string) ($config[$type] ?? ''));
    }

    /** Gửi tin nhắn tự động cho khách hàng $userId theo mẫu $type. */
    public function send(int $userId, string $type): void
    {
        $message = $this->resolveMessage($type);
        if ($message === '') {
            return;
        }

        if (!(new QuanLyCauTruc($this->db))->ensureChatTable()) {
            return;
        }

        try {
            $this->db->execute(
                "INSERT INTO chat_messages (user_id, username, sender, message_type, message)
                 VALUES (?, ?, 'admin', 'text', ?)",
                [$userId, self::BOT_NAME, $message],
                'iss'
            );
        } catch (Throwable $e) {
            error_log('Auto reply failed: ' . $e->getMessage());
        }
    }
}
