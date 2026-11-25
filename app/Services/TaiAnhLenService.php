<?php

namespace App\Services;

/**
 * Lớp TaiAnhLenService - tiện ích xử lý file ảnh tải lên ($_FILES):
 * kiểm tra trạng thái upload, xác định MIME thật, lưu file vào thư mục đích.
 */
class TaiAnhLenService
{
    /** File có được tải lên thành công (error = UPLOAD_ERR_OK) hay không. */
    public function isUploaded(?array $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

    /** Đảm bảo thư mục tồn tại. */
    public function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }

    /** Phần mở rộng (chữ thường) theo tên file gốc. */
    public function extension(array $file): string
    {
        return strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    }

    /**
     * Xác định phần mở rộng dựa trên MIME thật của file (finfo), null nếu không thuộc danh sách cho phép.
     *
     * @param array<string, string> $allowedTypes [mime => extension]
     */
    public function extensionByMime(array $file, array $allowedTypes): ?string
    {
        $fileInfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
        $mime = $fileInfo ? finfo_file($fileInfo, $file['tmp_name']) : (string) ($file['type'] ?? '');
        if ($fileInfo) {
            finfo_close($fileInfo);
        }

        return isset($allowedTypes[$mime]) ? $allowedTypes[$mime] : null;
    }

    /** Di chuyển file tạm tới đường dẫn đích. */
    public function move(array $file, string $destination): bool
    {
        return move_uploaded_file($file['tmp_name'], $destination);
    }

    /** MIME ảnh thật đọc từ nội dung file (getimagesize); chuỗi rỗng nếu không phải ảnh. */
    public function detectImageMime(string $path): string
    {
        $imageInfo = @getimagesize($path);

        return is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
    }

    /**
     * Giữ chỗ một tên file ngẫu nhiên chưa tồn tại trong thư mục (tạo file rỗng bằng mode 'x'),
     * dạng {prefix}{32 ký tự hex}.{ext}; trả về tên file hoặc null sau 5 lần thử không được.
     */
    public function reserveUniqueFile(string $directory, string $prefix, string $extension): ?string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $fileName = $prefix . bin2hex(random_bytes(16)) . '.' . $extension;
            $reservation = @fopen($directory . $fileName, 'x');
            if ($reservation !== false) {
                fclose($reservation);

                return $fileName;
            }
        }

        return null;
    }
}
