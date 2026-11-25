<?php

namespace App\Models;

/**
 * Model TinNhan - tin nhắn chat giữa khách và cửa hàng (bảng `chat_messages`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class TinNhan extends BaseModel
{
    protected string $table = 'chat_messages';

    /** Các cột trả về cho giao diện chat (kèm thông tin sản phẩm được chia sẻ). */
    private const CHAT_VIEW_COLUMNS = 'cm.id, cm.username, cm.sender, cm.message_type, cm.message, cm.product_id, p.name AS product_name, p.image AS product_image, CASE WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW() THEN p.price_original ELSE p.price_sale END AS product_price, cm.attachment_name, cm.attachment_mime, cm.attachment_size, cm.created_at';

    /** Danh sách hội thoại cho trang quản trị (mới nhất trước, kèm số tin chưa đọc). */
    public function conversationsForAdmin(): array
    {
        return $this->db->fetchAll("SELECT cm.user_id,
                       COALESCE(NULLIF(u.username, ''), MAX(cm.username), 'Khách hàng') AS username,
                       (SELECT latest.message FROM chat_messages latest WHERE latest.user_id = cm.user_id ORDER BY latest.id DESC LIMIT 1) AS last_message,
                       (SELECT latest.created_at FROM chat_messages latest WHERE latest.user_id = cm.user_id ORDER BY latest.id DESC LIMIT 1) AS last_at,
                       (SELECT COUNT(*) FROM chat_messages incoming
                        WHERE incoming.user_id = cm.user_id AND incoming.sender = 'customer'
                          AND incoming.is_read = 0) AS unread
                FROM chat_messages cm
                LEFT JOIN users u ON u.id = cm.user_id
                GROUP BY cm.user_id, u.username
                ORDER BY MAX(cm.id) DESC");
    }

    /** $limit tin nhắn gần nhất của một khách hàng, sắp xếp cũ -> mới. */
    public function latestChatViewForUser(int $userId, int $limit = 200): array
    {
        $rows = $this->db->fetchAll(
            'SELECT ' . self::CHAT_VIEW_COLUMNS . ' FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id WHERE cm.user_id = ? ORDER BY cm.id DESC LIMIT ?',
            [$userId, $limit],
            'ii'
        );

        return array_reverse($rows);
    }

    /** Toàn bộ tin nhắn của khách hàng theo thời gian tạo tăng dần. */
    public function allChatViewForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT ' . self::CHAT_VIEW_COLUMNS . ' FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id WHERE cm.user_id = ? ORDER BY cm.created_at ASC, cm.id ASC',
            [$userId],
            'i'
        );
    }

    /** Một tin nhắn (dạng hiển thị chat, kèm sản phẩm) theo ID. */
    public function findChatView(int $messageId): ?array
    {
        return $this->db->fetchOne(
            'SELECT ' . self::CHAT_VIEW_COLUMNS . ' FROM chat_messages cm LEFT JOIN products p ON p.id = cm.product_id WHERE cm.id = ? LIMIT 1',
            [$messageId],
            'i'
        );
    }

    /** Một tin nhắn tệp đính kèm (không kèm sản phẩm) theo ID. */
    public function findAttachmentView(int $messageId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, username, sender, message_type, message, attachment_name, attachment_mime, attachment_size, created_at FROM chat_messages WHERE id = ? LIMIT 1',
            [$messageId],
            'i'
        );
    }

    /** Thêm tin nhắn văn bản, trả về ID mới. */
    public function createTextMessage(int $userId, string $username, string $sender, string $message, ?int $productId = null): int
    {
        return $this->db->insert(
            "INSERT INTO chat_messages (user_id, username, sender, message_type, message, product_id) VALUES (?, ?, ?, 'text', ?, ?)",
            [$userId, $username, $sender, $message, $productId],
            'isssi'
        );
    }

    /** Thêm tin nhắn có tệp đính kèm, trả về ID mới. */
    public function createAttachmentMessage(
        int $userId,
        string $username,
        string $sender,
        string $messageType,
        string $message,
        string $storedName,
        string $originalName,
        string $mime,
        int $size
    ): int {
        return $this->db->insert(
            'INSERT INTO chat_messages (user_id, username, sender, message_type, message, attachment_path, attachment_name, attachment_mime, attachment_size)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $username, $sender, $messageType, $message, $storedName, $originalName, $mime, $size],
            'isssssssi'
        );
    }

    /** Đánh dấu đã đọc các tin của khách có ID <= $lastMessageId. */
    public function markCustomerMessagesRead(int $userId, int $lastMessageId): int
    {
        return $this->db->execute(
            "UPDATE chat_messages
                                SET is_read = 1
                                WHERE user_id = ? AND sender = 'customer'
                                  AND is_read = 0 AND id <= ?",
            [$userId, $lastMessageId],
            'ii'
        );
    }

    /** Số tin nhắn văn bản khách hàng đã gửi trong ngày hôm nay (không tính ảnh). */
    public function countCustomerMessagesToday(int $userId): int
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) AS cnt FROM chat_messages WHERE user_id = ? AND sender = 'customer' AND message_type = 'text' AND DATE(created_at) = CURDATE()",
            [$userId],
            'i'
        );
    }

    /** Đã có tin của $botName gửi cho khách sau tin văn bản đầu tiên của khách trong hôm nay hay chưa. */
    public function hasBotReplyAfterFirstCustomerMessageToday(int $userId, string $botName): bool
    {
        return (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM chat_messages
             WHERE user_id = ? AND sender = 'admin' AND username = ?
               AND id > (SELECT MIN(id) FROM chat_messages
                         WHERE user_id = ? AND sender = 'customer' AND message_type = 'text' AND DATE(created_at) = CURDATE())",
            [$userId, $botName, $userId],
            'isi'
        ) > 0;
    }

    /** Lấy khóa tên $name (tối đa $timeout giây); false nếu không lấy được. */
    public function acquireLock(string $name, int $timeout = 3): bool
    {
        return (int) $this->db->fetchValue('SELECT GET_LOCK(?, ?)', [$name, $timeout], 'si') === 1;
    }

    public function releaseLock(string $name): void
    {
        $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [$name], 's');
    }

    /**
     * Thông tin tệp đính kèm của tin nhắn; nếu truyền $userId thì chỉ lấy tin thuộc khách hàng đó.
     */
    public function findAttachmentFile(int $messageId, ?int $userId = null): ?array
    {
        if ($userId !== null) {
            return $this->db->fetchOne(
                "SELECT message_type, attachment_path, attachment_name, attachment_mime
                            FROM chat_messages
                            WHERE id = ? AND user_id = ? AND message_type IN ('image', 'file') LIMIT 1",
                [$messageId, $userId],
                'ii'
            );
        }

        return $this->db->fetchOne(
            "SELECT message_type, attachment_path, attachment_name, attachment_mime
                            FROM chat_messages
                            WHERE id = ? AND message_type IN ('image', 'file') LIMIT 1",
            [$messageId],
            'i'
        );
    }

    // ───────────── Phía khách: chỉ kèm sản phẩm còn kinh doanh (portOrders) ─────────────

    /** LEFT JOIN sản phẩm còn kinh doanh (sản phẩm và danh mục đang hoạt động, chưa xóa mềm). */
    private const ACTIVE_PRODUCT_JOIN = "LEFT JOIN products p ON p.id = cm.product_id AND p.is_deleted = 0 AND p.status = 'active' AND EXISTS (SELECT 1 FROM categories c WHERE c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0)";

    /** Toàn bộ tin nhắn của khách (cũ -> mới), chỉ kèm thông tin sản phẩm còn kinh doanh. */
    public function allCustomerChatViewForUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT ' . self::CHAT_VIEW_COLUMNS . ' FROM chat_messages cm ' . self::ACTIVE_PRODUCT_JOIN . ' WHERE cm.user_id = ? ORDER BY cm.created_at ASC, cm.id ASC',
            [$userId],
            'i'
        );
    }

    /** Một tin nhắn theo ID cho phía khách, chỉ kèm thông tin sản phẩm còn kinh doanh. */
    public function findCustomerChatView(int $messageId): ?array
    {
        return $this->db->fetchOne(
            'SELECT ' . self::CHAT_VIEW_COLUMNS . ' FROM chat_messages cm ' . self::ACTIVE_PRODUCT_JOIN . ' WHERE cm.id = ? LIMIT 1',
            [$messageId],
            'i'
        );
    }

    // --- end of methods ---
}
