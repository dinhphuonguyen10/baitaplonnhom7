<?php

namespace App\Models;

/**
 * Model User - tài khoản khách hàng (bảng `users`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class User extends BaseModel
{
    protected string $table = 'users';

    /** Lấy thông tin đăng nhập của khách hàng theo tên tài khoản (id, username, password). */
    public function findCredentialsByUsername(string $username): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, username, password FROM users WHERE username = ? LIMIT 1',
            [$username],
            's'
        );
    }

    /** Cập nhật mật khẩu (đã băm) cho khách hàng. */
    public function updatePasswordHash(int $id, string $passwordHash): int
    {
        return $this->db->execute('UPDATE users SET password = ? WHERE id = ?', [$passwordHash, $id], 'si');
    }

    /** Kiểm tra số điện thoại đã được dùng bởi một tài khoản khách hàng chưa. */
    public function phoneRegistered(string $phone): bool
    {
        return $this->db->fetchOne('SELECT id FROM users WHERE phone = ? LIMIT 1', [$phone], 's') !== null;
    }

    /** Tìm ID khách hàng khớp cả tên tài khoản và số điện thoại (dùng cho quên mật khẩu). */
    public function findIdByUsernameAndPhone(string $username, string $phone): ?int
    {
        $id = $this->db->fetchValue(
            'SELECT id FROM users WHERE username = ? AND phone = ? LIMIT 1',
            [$username, $phone],
            'ss'
        );

        return $id === null ? null : (int) $id;
    }

    /** Tạo tài khoản khách hàng mới (mật khẩu đã băm), trả về ID. */
    public function createCustomer(string $username, string $passwordHash, string $phone): int
    {
        return $this->db->insert(
            'INSERT INTO users (username, password, phone) VALUES (?, ?, ?)',
            [$username, $passwordHash, $phone],
            'sss'
        );
    }

    /** Thông tin hiển thị trên trang hồ sơ khách hàng (không gồm mật khẩu). */
    public function findProfile(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, username, full_name, phone, created_at FROM users WHERE id = ? LIMIT 1',
            [$id],
            'i'
        );
    }

    /** Mật khẩu (đã băm) hiện tại của khách hàng, dùng để xác nhận trước khi đổi. */
    public function findPasswordHash(int $id): ?string
    {
        $hash = $this->db->fetchValue('SELECT password FROM users WHERE id = ? LIMIT 1', [$id], 'i');

        return $hash === null ? null : (string) $hash;
    }

    /** Số điện thoại đã được tài khoản khác (khác $exceptUserId) sử dụng chưa. */
    public function phoneUsedByOtherUser(string $phone, int $exceptUserId): bool
    {
        return $this->db->fetchOne(
            'SELECT id FROM users WHERE phone = ? AND id <> ? LIMIT 1',
            [$phone, $exceptUserId],
            'si'
        ) !== null;
    }

    /** Cập nhật số điện thoại của khách hàng. */
    public function updatePhone(int $id, string $phone): int
    {
        return $this->db->execute('UPDATE users SET phone = ? WHERE id = ?', [$phone, $id], 'si');
    }

    /** Cập nhật họ tên và số điện thoại của khách hàng. */
    public function updateProfileInfo(int $id, string $fullName, string $phone): int
    {
        return $this->db->execute(
            'UPDATE users SET full_name = ?, phone = ? WHERE id = ?',
            [$fullName, $phone, $id],
            'ssi'
        );
    }

    // --- end of methods ---
}
