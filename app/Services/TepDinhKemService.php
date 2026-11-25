<?php

namespace App\Services;

use App\Core\Database;
use App\Exceptions\TroChuyenException;
use App\Models\TinNhan;
use Throwable;

/**
 * Lớp TepDinhKemService - xử lý tệp đính kèm trong chat:
 * kiểm tra ảnh tải lên, lưu vào thư mục riêng storage/chat, tạo tin nhắn tệp
 * và tìm/stream tệp khi người dùng tải về.
 */
class TepDinhKemService
{
    /** Dung lượng tối đa của một tệp: 100 MB. */
    public const MAX_SIZE = 104857600;

    private const ALLOWED_TYPES = [
        'jpg' => ['mime' => ['image/jpeg'], 'type' => 'image'],
        'jpeg' => ['mime' => ['image/jpeg'], 'type' => 'image'],
        'png' => ['mime' => ['image/png'], 'type' => 'image'],
        'gif' => ['mime' => ['image/gif'], 'type' => 'image'],
        'webp' => ['mime' => ['image/webp'], 'type' => 'image'],
    ];

    private const INLINE_IMAGE_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const TOO_LARGE = 'Ảnh vượt quá giới hạn tải lên của máy chủ.';

    private TinNhan $messages;
    private string $directory;

    public function __construct(?Database $db = null, ?TinNhan $messages = null, ?string $directory = null)
    {
        $this->messages = $messages ?? new TinNhan($db ?? Database::getInstance());
        $this->directory = $directory ?? APP_ROOT . '/storage/chat';
    }

    /** Thư mục lưu tệp đính kèm chat. */
    public function directory(): string
    {
        return $this->directory;
    }

    // ───────────── Tải lên ─────────────

    /** Ném lỗi 413 nếu kích thước request vượt post_max_size của PHP. */
    public function assertWithinPostLimit(int $contentLength): void
    {
        $postMaxSize = trim((string) ini_get('post_max_size'));
        $postMaxUnit = strtolower(substr($postMaxSize, -1));
        $postMaxBytes = (int) $postMaxSize;
        if ($postMaxUnit === 'g') {
            $postMaxBytes *= 1024 * 1024 * 1024;
        } elseif ($postMaxUnit === 'm') {
            $postMaxBytes *= 1024 * 1024;
        } elseif ($postMaxUnit === 'k') {
            $postMaxBytes *= 1024;
        }

        if ($postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            throw new TroChuyenException(self::TOO_LARGE, 413);
        }
    }

    /**
     * Kiểm tra tệp tải lên ($_FILES['file']).
     *
     * @return array{tmp: string, name: string, extension: string, mime: string, size: int, type: string}
     */
    public function validateUpload(?array $upload): array
    {
        if (!is_array($upload)
            || !isset($upload['error'], $upload['tmp_name'], $upload['name'], $upload['size'])
            || !is_int($upload['error'])
            || !is_string($upload['tmp_name'])
            || !is_string($upload['name'])
            || !is_numeric($upload['size'])) {
            throw new TroChuyenException('Vui lòng chọn một tệp để gửi.', 400);
        }
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $uploadError = (int) $upload['error'];
            $message = $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
                ? self::TOO_LARGE
                : 'Không thể nhận ảnh tải lên. Vui lòng thử lại.';
            throw new TroChuyenException($message, 400);
        }

        $temporaryPath = $upload['tmp_name'];
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
            throw new TroChuyenException('Tệp tải lên không hợp lệ.', 400);
        }
        $fileSize = filesize($temporaryPath);
        if ($fileSize === false || $fileSize < 1 || $fileSize > self::MAX_SIZE) {
            throw new TroChuyenException('Ảnh phải có dung lượng từ 1 byte đến 100 MB.', 400);
        }

        $originalName = $this->sanitizeName($upload['name']);
        if ($originalName === '' || $originalName === '.' || $originalName === '..') {
            throw new TroChuyenException('Tên tệp không hợp lệ.', 400);
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension])) {
            throw new TroChuyenException('Chỉ nhận ảnh JPG, PNG, GIF hoặc WEBP.', 415);
        }

        $detectedMime = $this->detectMime($temporaryPath);
        if (!is_string($detectedMime) || !in_array($detectedMime, self::ALLOWED_TYPES[$extension]['mime'], true)) {
            throw new TroChuyenException('Nội dung tệp không khớp với phần mở rộng được phép.', 415);
        }

        if (self::ALLOWED_TYPES[$extension]['type'] === 'image') {
            $imageInfo = getimagesize($temporaryPath);
            if ($imageInfo === false
                || ($imageInfo['mime'] ?? '') !== $detectedMime
                || (int) $imageInfo[0] > 16000
                || (int) $imageInfo[1] > 16000
                || (int) $imageInfo[0] * (int) $imageInfo[1] > 50000000) {
                throw new TroChuyenException('Ảnh không hợp lệ hoặc vượt quá 50 MP.', 415);
            }
        }

        return [
            'tmp' => $temporaryPath,
            'name' => $originalName,
            'extension' => $extension,
            'mime' => $detectedMime,
            'size' => (int) $fileSize,
            'type' => self::ALLOWED_TYPES[$extension]['type'],
        ];
    }

    /**
     * Lưu tệp đã kiểm tra vào storage/chat và tạo tin nhắn tệp.
     *
     * @return array|null tin nhắn vừa tạo
     */
    public function storeAndCreateMessage(array $file, int $userId, string $senderUsername, string $sender): ?array
    {
        $directory = $this->directory;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            error_log('Unable to create private chat attachment directory.');
            throw new TroChuyenException('Không thể lưu tệp lúc này.', 500);
        }

        $storedName = bin2hex(random_bytes(24)) . '.' . $file['extension'];
        $storedPath = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (!move_uploaded_file($file['tmp'], $storedPath)) {
            throw new TroChuyenException('Không thể lưu tệp lúc này.', 500);
        }

        try {
            $messageId = $this->messages->createAttachmentMessage(
                $userId,
                $senderUsername,
                $sender,
                $file['type'],
                'Ảnh: ' . $file['name'],
                $storedName,
                $file['name'],
                $file['mime'],
                $file['size']
            );
        } catch (Throwable $e) {
            unlink($storedPath);
            throw new TroChuyenException('Không thể lưu tin nhắn tệp.', 500);
        }

        try {
            return $this->messages->findAttachmentView($messageId);
        } catch (Throwable $e) {
            throw new TroChuyenException('Tệp đã gửi nhưng không thể tải lại tin nhắn.', 500);
        }
    }

    // ───────────── Tải về ─────────────

    /**
     * Tìm tệp đính kèm cho người xem. $customerId != null: chỉ tệp trong hội thoại của khách đó.
     *
     * @return array{path: string, content_type: string, disposition: string, name: string}
     */
    public function resolveDownload($messageId, ?int $customerId): array
    {
        $messageId = TroChuyenService::positiveInt($messageId);
        if ($messageId === null) {
            throw new TroChuyenException('Tệp không hợp lệ.', 400);
        }

        try {
            $attachment = $this->messages->findAttachmentFile($messageId, $customerId);
        } catch (Throwable $e) {
            throw new TroChuyenException('Không thể tải tệp lúc này.', 500);
        }
        if (!$attachment) {
            throw new TroChuyenException('Không tìm thấy tệp.', 404);
        }

        $storedName = (string) $attachment['attachment_path'];
        $storageDirectory = realpath($this->directory);
        if ($storageDirectory === false || $storedName === '' || basename($storedName) !== $storedName) {
            throw new TroChuyenException('Không tìm thấy tệp.', 404);
        }
        $resolvedPath = realpath($storageDirectory . DIRECTORY_SEPARATOR . $storedName);
        if ($resolvedPath === false || dirname($resolvedPath) !== $storageDirectory || !is_file($resolvedPath) || !is_readable($resolvedPath)) {
            throw new TroChuyenException('Không tìm thấy tệp.', 404);
        }

        $isImage = $attachment['message_type'] === 'image'
            && in_array($attachment['attachment_mime'], self::INLINE_IMAGE_MIMES, true);

        return [
            'path' => $resolvedPath,
            'content_type' => $isImage ? (string) $attachment['attachment_mime'] : 'application/octet-stream',
            'disposition' => $isImage ? 'inline' : 'attachment',
            'name' => (string) $attachment['attachment_name'],
        ];
    }

    /** Gửi tệp về trình duyệt với các header an toàn. */
    public function stream(array $download): void
    {
        header('Content-Type: ' . $download['content_type']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: ' . $download['disposition'] . "; filename=\"attachment\"; filename*=UTF-8''" . rawurlencode($download['name']));
        header('Content-Length: ' . (string) filesize($download['path']));
        readfile($download['path']);
    }

    // ───────────── Tiện ích ─────────────

    private function sanitizeName(string $name): string
    {
        if (!mb_check_encoding($name, 'UTF-8')) {
            $name = 'tep-tai-len';
        }
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return trim(mb_substr($name, 0, 180, 'UTF-8'));
    }

    private function detectMime(string $path)
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            error_log('Unable to initialize fileinfo for chat attachment validation.');
            throw new TroChuyenException('Máy chủ chưa thể kiểm tra loại tệp.', 500);
        }
        $detectedMime = finfo_file($fileInfo, $path);
        finfo_close($fileInfo);

        return $detectedMime;
    }
}
