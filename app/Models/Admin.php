<?php

namespace App\Models;

/**
 * Model Admin - tài khoản quản trị (manager/staff) (bảng `admins`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Admin extends BaseModel
{
    protected string $table = 'admins';

    /** Lấy tài khoản quản trị để xác thực đăng nhập (id, username, password, role). */
    public function findForLogin(string $username): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, username, password, role FROM admins WHERE username = ? LIMIT 1',
            [$username],
            's'
        );
    }

    /** Danh sách tài khoản nội bộ cho trang quản lý tài khoản (mới nhất trước). */
    public function listForManagement(): array
    {
        return $this->db->fetchAll('SELECT id, username, role, created_at FROM admins ORDER BY id DESC');
    }

    /** Lấy thông tin cơ bản (id, username, role) của một tài khoản quản trị. */
    public function findBasicById(int $id): ?array
    {
        return $this->db->fetchOne('SELECT id, username, role FROM admins WHERE id = ? LIMIT 1', [$id], 'i');
    }

    /** Kiểm tra tên đăng nhập quản trị đã tồn tại chưa. */
    public function usernameExists(string $username): bool
    {
        return $this->db->fetchOne('SELECT id FROM admins WHERE username = ? LIMIT 1', [$username], 's') !== null;
    }

    /** Tạo tài khoản quản trị mới với mật khẩu đã băm, trả về ID. */
    public function createAccount(string $username, string $passwordHash, string $role): int
    {
        return $this->db->insert(
            'INSERT INTO admins (username, password, role) VALUES (?, ?, ?)',
            [$username, $passwordHash, $role],
            'sss'
        );
    }

    /** Cập nhật mật khẩu (đã băm) cho tài khoản quản trị. */
    public function updatePasswordHash(int $id, string $passwordHash): int
    {
        return $this->db->execute('UPDATE admins SET password = ? WHERE id = ?', [$passwordHash, $id], 'si');
    }

    /** Xóa một tài khoản quản trị. */
    public function deleteAccount(int $id): int
    {
        return $this->db->execute('DELETE FROM admins WHERE id = ? LIMIT 1', [$id], 'i');
    }

    // --- end of methods ---
}
