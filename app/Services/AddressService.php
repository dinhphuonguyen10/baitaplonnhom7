<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Address;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Lớp AddressService - quản lý sổ địa chỉ nhận hàng của khách (thêm, đặt mặc định, xóa).
 * Lỗi hiển thị cho khách được ném dưới dạng DomainException.
 */
class AddressService
{
    private Database $db;
    private Address $addresses;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->addresses = new Address($this->db);
    }

    /** Danh sách địa chỉ của khách, địa chỉ mặc định trước. */
    public function listForUser(int $userId): array
    {
        return $this->addresses->listForUser($userId);
    }

    /** Thêm địa chỉ mới (tuỳ chọn đặt làm mặc định). */
    public function add(int $userId, string $recipientName, string $phone, string $address, bool $isDefault): void
    {
        if ($recipientName === '' || $phone === '' || $address === '') {
            throw new DomainException('Vui lòng nhập đầy đủ tên người nhận, số điện thoại và địa chỉ.');
        }

        try {
            $this->db->transaction(function () use ($userId, $recipientName, $phone, $address, $isDefault): void {
                if ($isDefault) {
                    $this->addresses->clearDefaultForUser($userId);
                }
                $this->addresses->createForUser($userId, $recipientName, $address, $phone, $isDefault ? 1 : 0);
            });
        } catch (Throwable $e) {
            error_log('Address creation failed: ' . $e->getMessage());
            throw new DomainException('Không thể lưu địa chỉ lúc này. Vui lòng thử lại.', 0, $e);
        }
    }

    /** Đặt một địa chỉ của khách làm mặc định. */
    public function setDefault(int $userId, int $addressId): void
    {
        try {
            $this->db->transaction(function () use ($userId, $addressId): void {
                $this->addresses->clearDefaultForUser($userId);
                if ($this->addresses->markDefaultForUser($addressId, $userId) !== 1) {
                    throw new RuntimeException('Không tìm thấy địa chỉ thuộc tài khoản này.');
                }
            });
        } catch (Throwable $e) {
            error_log('Default address update failed: ' . $e->getMessage());
            throw new DomainException('Không thể cập nhật địa chỉ mặc định.', 0, $e);
        }
    }

    /** Xóa địa chỉ của khách. */
    public function delete(int $userId, int $addressId): void
    {
        if ($this->addresses->deleteForUser($addressId, $userId) !== 1) {
            throw new DomainException('Không tìm thấy địa chỉ thuộc tài khoản này.');
        }
    }
}
