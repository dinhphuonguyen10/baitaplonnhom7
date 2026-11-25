<?php

namespace App\Models;

/**
 * Model Address - sổ địa chỉ nhận hàng của khách (bảng `addresses`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Address extends BaseModel
{
    protected string $table = 'addresses';

    // ───────────── Sổ địa chỉ của khách hàng (agentB) ─────────────

    /** Địa chỉ của khách, mặc định trước (kèm tọa độ khi $withCoordinates). */
    public function listForUser(int $userId, bool $withCoordinates = false): array
    {
        $columns = 'id, recipient_name, address, phone, is_default' . ($withCoordinates ? ', latitude, longitude' : '');

        return $this->db->fetchAll(
            "SELECT {$columns} FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC",
            [$userId],
            'i'
        );
    }

    /** Khóa và lấy ID địa chỉ thuộc khách hàng. */
    public function lockOwnedId(int $addressId, int $userId): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM addresses WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE',
            [$addressId, $userId],
            'ii'
        );

        return $row ? (int) $row['id'] : null;
    }

    /** Tìm địa chỉ trùng khớp (tên, địa chỉ, SĐT) của khách. */
    public function findMatchingId(int $userId, string $recipientName, string $address, string $phone): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM addresses WHERE user_id = ? AND recipient_name = ? AND address = ? AND phone = ? LIMIT 1',
            [$userId, $recipientName, $address, $phone],
            'isss'
        );

        return $row ? (int) $row['id'] : null;
    }

    /** Bỏ cờ mặc định của mọi địa chỉ của khách (trừ $exceptId nếu có). */
    public function clearDefaultForUser(int $userId, ?int $exceptId = null): int
    {
        if ($exceptId === null) {
            return $this->db->execute('UPDATE addresses SET is_default = 0 WHERE user_id = ?', [$userId], 'i');
        }

        return $this->db->execute(
            'UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id <> ?',
            [$userId, $exceptId],
            'ii'
        );
    }

    /** Đặt địa chỉ làm mặc định (và cập nhật tọa độ nếu có). Trả về số dòng bị ảnh hưởng. */
    public function markDefaultForUser(int $addressId, int $userId, ?float $latitude = null, ?float $longitude = null): int
    {
        if ($latitude === null) {
            return $this->db->execute(
                'UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?',
                [$addressId, $userId],
                'ii'
            );
        }

        return $this->db->execute(
            'UPDATE addresses SET is_default = 1, latitude = ?, longitude = ? WHERE id = ? AND user_id = ?',
            [$latitude, $longitude, $addressId, $userId],
            'ddii'
        );
    }

    /** Cập nhật tọa độ bản đồ của địa chỉ. */
    public function updateCoordinatesForUser(int $addressId, int $userId, float $latitude, float $longitude): int
    {
        return $this->db->execute(
            'UPDATE addresses SET latitude = ?, longitude = ? WHERE id = ? AND user_id = ?',
            [$latitude, $longitude, $addressId, $userId],
            'ddii'
        );
    }

    /** Thêm địa chỉ mới cho khách (tọa độ tuỳ chọn), trả về ID. */
    public function createForUser(
        int $userId,
        string $recipientName,
        string $address,
        string $phone,
        int $isDefault,
        ?float $latitude = null,
        ?float $longitude = null
    ): int {
        if ($latitude === null) {
            return $this->db->insert(
                'INSERT INTO addresses (user_id, recipient_name, address, phone, is_default) VALUES (?, ?, ?, ?, ?)',
                [$userId, $recipientName, $address, $phone, $isDefault],
                'isssi'
            );
        }

        return $this->db->insert(
            'INSERT INTO addresses (user_id, recipient_name, address, phone, is_default, latitude, longitude)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $recipientName, $address, $phone, $isDefault, $latitude, $longitude],
            'isssidd'
        );
    }

    /** Xóa địa chỉ thuộc khách. Trả về số dòng bị xóa. */
    public function deleteForUser(int $addressId, int $userId): int
    {
        return $this->db->execute('DELETE FROM addresses WHERE id = ? AND user_id = ?', [$addressId, $userId], 'ii');
    }

    // --- end of methods ---
}
