<?php

namespace App\Core;

use RuntimeException;

/**
 * Ngoại lệ phát sinh khi truy vấn CSDL thất bại.
 */
class DatabaseException extends RuntimeException
{
    private string $sql;

    public function __construct(string $message, int $code = 0, string $sql = '')
    {
        parent::__construct($message, $code);
        $this->sql = $sql;
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
