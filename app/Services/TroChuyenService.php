<?php

namespace App\Services;

use App\Core\Database;
use App\Database\QuanLyCauTruc;
use App\Exceptions\TroChuyenException;
use App\Models\TinNhan;
use App\Models\SanPham;
use App\Models\KhachHang;
use Throwable;

/**
 * Lớp TroChuyenService - nghiệp vụ chat giữa khách hàng và cửa hàng:
 * tải hội thoại, gửi tin nhắn văn bản, đánh dấu đã đọc, tin trả lời tự động.
 * Lỗi nghiệp vụ được ném ra dưới dạng TroChuyenException (kèm mã HTTP và thông báo tiếng Việt).
 */
class TroChuyenService
{
    /** Độ dài tối đa của một tin nhắn văn bản. */
    public const MAX_MESSAGE_LENGTH = 2000;

    public const NOT_READY_MESSAGE = 'Chưa thể chuẩn bị khu vực chat. Vui lòng thử lại sau.';
    public const INVALID_CONVERSATION = 'Cuộc trò chuyện không hợp lệ.';
    public const INVALID_MESSAGE = 'Tin nhắn cần có nội dung và không quá 2.000 ký tự.';

    private Database $db;
    private TinNhan $messages;
    private TuDongTraLoiService $autoReply;
    private MauTraLoiTuDongService $autoResponses;

    public function __construct(
        ?Database $db = null,
        ?TinNhan $messages = null,
        ?TuDongTraLoiService $autoReply = null,
        ?MauTraLoiTuDongService $autoResponses = null
    ) {
        $this->db = $db ?? Database::getInstance();
        $this->messages = $messages ?? new TinNhan($this->db);
        $this->autoReply = $autoReply ?? new TuDongTraLoiService($this->db);
        $this->autoResponses = $autoResponses ?? new MauTraLoiTuDongService();
    }

    /** Đảm bảo bảng chat_messages sẵn sàng. */
    public function ensureReady(): bool
    {
        return (new QuanLyCauTruc($this->db))->ensureChatTable();
    }

    /** Chuyển giá trị bất kỳ thành số nguyên dương, null nếu không hợp lệ. */
    public static function positiveInt($value): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false || $filtered === null || $filtered < 1 ? null : (int) $filtered;
    }

    // ───────────── Phía quản trị ─────────────

    public function conversations(): array
    {
        try {
            return $this->messages->conversationsForAdmin();
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể tải danh sách hội thoại.', 500);
        }
    }

    public function adminMessages($userId): array
    {
        $userId = self::positiveInt($userId);
        if ($userId === null) {
            throw new TroChuyenException(self::INVALID_CONVERSATION, 400);
        }

        try {
            return $this->messages->latestChatViewForUser($userId, 200);
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể tải tin nhắn.', 500);
        }
    }

    public function markRead($userId, $lastMessageId): void
    {
        $userId = self::positiveInt($userId);
        $lastMessageId = self::positiveInt($lastMessageId);
        if ($userId === null || $lastMessageId === null) {
            throw new TroChuyenException(self::INVALID_CONVERSATION, 400);
        }

        try {
            $this->messages->markCustomerMessagesRead($userId, $lastMessageId);
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể cập nhật trạng thái đã đọc.', 500);
        }
    }

    /**
     * Gửi tin trả lời tự động theo cấu hình kiểu cũ (key => message).
     *
     * @return array|null tin nhắn vừa tạo
     */
    public function adminAutoReply($type, $userId, string $adminUsername): ?array
    {
        $userId = self::positiveInt($userId);
        if (!$type || $userId === null) {
            throw new TroChuyenException('Tham số không hợp lệ.', 400);
        }
        if ($this->customerUsername($userId) === null) {
            throw new TroChuyenException('Không tìm thấy khách hàng này.', 404);
        }

        $messageText = $this->autoResponses->legacyMessage((string) $type);
        if (!$messageText) {
            throw new TroChuyenException('Tin nhắn tự động không được cấu hình.', 400);
        }

        try {
            $messageId = $this->messages->createTextMessage((int) $userId, $adminUsername, 'admin', $messageText);
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể gửi tin tự động.', 500);
        }

        try {
            return $this->messages->findChatView($messageId);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Nhân viên gửi tin nhắn văn bản cho khách hàng.
     *
     * @return array|null tin nhắn vừa tạo
     */
    public function adminSend($userId, $message, string $adminUsername): ?array
    {
        $userId = self::positiveInt($userId);
        $message = trim(is_scalar($message) ? (string) $message : '');
        if ($userId === null) {
            throw new TroChuyenException(self::INVALID_CONVERSATION, 400);
        }
        if ($message === '' || mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            throw new TroChuyenException(self::INVALID_MESSAGE, 400);
        }

        // Xác minh khách hàng tồn tại trong users (bảng users chỉ chứa tài khoản khách hàng).
        if ($this->customerUsername($userId) === null) {
            throw new TroChuyenException('Không tìm thấy khách hàng này.', 404);
        }

        try {
            $messageId = $this->messages->createTextMessage($userId, $adminUsername, 'admin', $message);
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể gửi tin nhắn lúc này.', 500);
        }

        try {
            return $this->messages->findChatView($messageId);
        } catch (Throwable $e) {
            throw new TroChuyenException('Tin nhắn đã gửi nhưng không thể tải lại nội dung.', 500);
        }
    }

    /**
     * Tên đăng nhập của khách hàng, null nếu không tồn tại.
     */
    public function customerUsername(int $userId): ?string
    {
        try {
            $user = (new KhachHang($this->db))->find($userId);
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể kiểm tra khách hàng.', 500);
        }

        return $user === null ? null : (string) ($user['username'] ?? '');
    }

    // ───────────── Phía khách hàng ─────────────

    public function customerMessages(int $userId): array
    {
        return $this->messages->allCustomerChatViewForUser($userId);
    }

    /** Kiểm tra nội dung tin nhắn khách gửi, trả về nội dung đã trim. */
    public function validateCustomerMessage(array $data): string
    {
        $message = isset($data['message']) && is_scalar($data['message']) ? trim((string) $data['message']) : '';
        if ($message === '' || mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE_LENGTH) {
            throw new TroChuyenException(self::INVALID_MESSAGE, 400);
        }

        return $message;
    }

    /** Kiểm tra sản phẩm được chia sẻ kèm tin nhắn (nếu có), trả về ID hoặc null. */
    public function validateSharedProduct(array $data): ?int
    {
        if (!isset($data['product_id'])) {
            return null;
        }

        $productId = filter_var($data['product_id'], FILTER_VALIDATE_INT);
        if ($productId === false || $productId < 1) {
            throw new TroChuyenException('Sản phẩm được chia sẻ không hợp lệ.', 400);
        }

        try {
            $exists = (new SanPham($this->db))->findActiveIdAndName((int) $productId) !== null;
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể kiểm tra sản phẩm được chia sẻ.', 500);
        }
        if (!$exists) {
            throw new TroChuyenException('Sản phẩm không còn được kinh doanh hoặc không tìm thấy.', 404);
        }

        return (int) $productId;
    }

    /**
     * Lưu tin nhắn của khách hàng; trả về tin vừa tạo hoặc false nếu không lưu được.
     *
     * @return array|null|false
     */
    public function customerSend(int $userId, string $username, string $message, ?int $productId)
    {
        try {
            $messageId = $this->messages->createTextMessage($userId, $username, 'customer', $message, $productId);
        } catch (Throwable $e) {
            return false;
        }

        return $this->messages->findCustomerChatView($messageId);
    }

    /**
     * Gửi tin chăm sóc một lần mỗi ngày, sau tin nhắn văn bản đầu tiên của khách trong ngày.
     * Dùng khóa theo khách để hai tin gửi đồng thời không bị chào hai lần hoặc bị bỏ sót.
     */
    public function sendDailyGreetingIfFirstMessage(int $userId): void
    {
        $lockName = 'chat_greeting_' . $userId;
        $locked = false;
        try {
            $locked = $this->messages->acquireLock($lockName);
            if (!$locked) {
                return;
            }
            if ($this->messages->countCustomerMessagesToday($userId) < 1
                || $this->messages->hasBotReplyAfterFirstCustomerMessageToday($userId, TuDongTraLoiService::BOT_NAME)) {
                return;
            }
            $this->autoReply->send($userId, 'new_message');
        } catch (Throwable $e) {
            error_log('Daily greeting failed: ' . $e->getMessage());
        } finally {
            if ($locked) {
                try {
                    $this->messages->releaseLock($lockName);
                } catch (Throwable $e) {
                    // Khóa tự giải phóng khi kết nối đóng.
                }
            }
        }
    }
}
