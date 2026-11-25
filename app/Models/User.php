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

    // --- end of methods ---
}
