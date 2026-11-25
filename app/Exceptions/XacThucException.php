<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ngoại lệ nghiệp vụ của chức năng xác thực khách hàng (đăng nhập, đăng ký, quên mật khẩu).
 * Mã lỗi (code) chính là HTTP status cần trả về cho client.
 */
class XacThucException extends RuntimeException
{
    public function __construct(string $message, int $httpStatus = 400)
    {
        parent::__construct($message, $httpStatus);
    }

    public function httpStatus(): int
    {
        return (int) $this->getCode();
    }
}
