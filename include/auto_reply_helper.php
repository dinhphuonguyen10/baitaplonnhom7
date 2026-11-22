<?php
/**
 * sendAutoReply - Gửi tin tự động từ hệ thống khi khách nhắn tin hoặc đặt hàng.
 * Hỗ trợ cả cấu trúc JSON cũ (key => message) và mới (templates array).
 *
 * @param mysqli $conn   Database connection
 * @param int    $userId  User ID of the customer
 * @param string $type    Template ID, e.g. 'new_message' | 'order_thankyou'
 */
function sendAutoReply(mysqli $conn, int $userId, string $type): void
{
    $configPath = __DIR__ . '/../config/auto_responses.json';
    if (!file_exists($configPath)) return;

    $config = json_decode(file_get_contents($configPath), true);
    if (!is_array($config)) return;

    $messageText = '';

    // New structure: { "templates": [ { "id": "...", "message": "...", "status": "active" } ] }
    if (isset($config['templates']) && is_array($config['templates'])) {
        foreach ($config['templates'] as $tpl) {
            if (($tpl['id'] ?? '') === $type && ($tpl['status'] ?? '') === 'active') {
                $variants = array_values(array_filter((array) ($tpl['messages'] ?? []), static fn($value) => trim((string) $value) !== ''));
                $messageText = trim((string) ($variants ? $variants[array_rand($variants)] : ($tpl['message'] ?? '')));
                break;
            }
        }
    } else {
        // Legacy flat structure: { "new_message": "...", "order_thankyou": "..." }
        $messageText = trim((string) ($config[$type] ?? ''));
    }

    if ($messageText === '') return;

    // Đảm bảo bảng chat tồn tại
    $conn->query("CREATE TABLE IF NOT EXISTS chat_messages (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      INT UNSIGNED NOT NULL,
        username     VARCHAR(255) NOT NULL DEFAULT '',
        sender       ENUM('customer','admin') NOT NULL,
        message_type ENUM('text','image','file') NOT NULL DEFAULT 'text',
        message      TEXT NOT NULL DEFAULT '',
        product_id   INT UNSIGNED DEFAULT NULL,
        attachment_name VARCHAR(255) DEFAULT NULL,
        attachment_mime VARCHAR(127) DEFAULT NULL,
        attachment_size INT UNSIGNED DEFAULT NULL,
        is_read      TINYINT(1) NOT NULL DEFAULT 0,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $botName = 'ÉCRIN Jewelry';
    $stmt = $conn->prepare(
        "INSERT INTO chat_messages (user_id, username, sender, message_type, message)
         VALUES (?, ?, 'admin', 'text', ?)"
    );
    if (!$stmt) return;
    $stmt->bind_param('iss', $userId, $botName, $messageText);
    $stmt->execute();
    $stmt->close();
}
