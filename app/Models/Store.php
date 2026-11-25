<?php

namespace App\Models;

/**
 * Model Store - hệ thống cửa hàng (bảng `stores`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Store extends BaseModel
{
    protected string $table = 'stores';

    /** Danh sách cửa hàng cho trang quản trị (theo id tăng dần). */
    public function listForAdmin(): array
    {
        return $this->db->fetchAll('SELECT id, name, address, phone, hours, status FROM stores ORDER BY id ASC');
    }

    /** Thêm cửa hàng mới, trả về ID. */
    public function createStore(string $name, string $address, string $phone, string $hours, string $status): int
    {
        return $this->db->insert(
            'INSERT INTO stores (name, address, phone, hours, status) VALUES (?, ?, ?, ?, ?)',
            [$name, $address, $phone, $hours, $status],
            'sssss'
        );
    }

    /** Cập nhật thông tin cửa hàng. */
    public function updateStore(int $id, string $name, string $address, string $phone, string $hours, string $status): int
    {
        return $this->db->execute(
            'UPDATE stores SET name = ?, address = ?, phone = ?, hours = ?, status = ? WHERE id = ?',
            [$name, $address, $phone, $hours, $status, $id],
            'sssssi'
        );
    }

    /** Xóa cửa hàng theo ID. */
    public function deleteStore(int $id): int
    {
        return $this->db->execute('DELETE FROM stores WHERE id = ?', [$id], 'i');
    }

    /** Cửa hàng đang hoạt động cho trang "Hệ thống cửa hàng" (mới nhất trước). */
    public function findActiveForStorefront(): array
    {
        return $this->db->fetchAll("SELECT id, name, address, phone, hours FROM stores WHERE status = 'active' ORDER BY id DESC");
    }

    // --- end of methods ---
}
