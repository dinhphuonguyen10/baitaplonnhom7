<?php

namespace App\Helpers;

/**
 * Lớp ReviewerNameFormatter - ẩn danh tên khách trong danh sách đánh giá (VD: "nguyenmai" => "n****i").
 */
final class ReviewerNameFormatter
{
    public static function mask(string $username): string
    {
        $username = trim($username);
        if ($username === '') {
            return 'Khách hàng';
        }

        $length = mb_strlen($username, 'UTF-8');
        if ($length <= 2) {
            return mb_substr($username, 0, 1, 'UTF-8') . '***';
        }

        return mb_substr($username, 0, 1, 'UTF-8')
            . str_repeat('*', min(4, $length - 2))
            . mb_substr($username, -1, 1, 'UTF-8');
    }
}
