<?php

namespace App\Helpers;

/**
 * Lớp KiemTraTaiKhoan - quy tắc kiểm tra dữ liệu tài khoản khách hàng
 * (cùng quy tắc với đăng ký / quên mật khẩu).
 */
final class KiemTraTaiKhoan
{
    /** Bỏ khoảng trắng, dấu chấm, gạch, ngoặc; đổi +84xxx thành 0xxx. */
    public static function normalizePhone(string $phone): string
    {
        $phone = (string) preg_replace('/[\s().-]/', '', $phone);
        if (strncmp($phone, '+84', 3) === 0) {
            $phone = '0' . substr($phone, 3);
        }

        return $phone;
    }

    /** Số điện thoại Việt Nam: 10 chữ số, bắt đầu bằng 0. */
    public static function isValidPhone(string $phone): bool
    {
        return preg_match('/^0[0-9]{9}$/', $phone) === 1;
    }

    /** Bỏ khoảng trắng thừa trong họ tên ("  nguyễn   văn a " => "nguyễn văn a"). */
    public static function normalizeFullName(string $fullName): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $fullName));
    }

    /** Họ tên 2-150 ký tự, chỉ gồm chữ cái (kể cả tiếng Việt có dấu) và khoảng trắng. */
    public static function isValidFullName(string $fullName): bool
    {
        $length = mb_strlen($fullName, 'UTF-8');

        return $length >= 2 && $length <= 150 && preg_match('/^[\p{L}\p{M} ]+$/u', $fullName) === 1;
    }

    /** Tối thiểu 8 ký tự, có chữ thường, chữ in hoa, số và ký tự đặc biệt. */
    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^a-zA-Z0-9]/', $password);
    }
}
