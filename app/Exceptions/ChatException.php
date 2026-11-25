<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lớp ChatException - lỗi nghiệp vụ của chức năng chat, mang theo mã trạng thái HTTP
 * cần trả về cho người dùng (400, 404, 413, 415, 500...).
 */
class ChatException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message, $httpStatus);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
