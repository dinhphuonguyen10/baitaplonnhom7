<?php

namespace App\Services;

use App\Core\DatabaseException;
use App\Models\CuaHang;
use InvalidArgumentException;

/**
 * Lớp CuaHangService - nghiệp vụ quản lý hệ thống cửa hàng (thêm/sửa/xóa).
 */
class CuaHangService
{
    public const STATUSES = ['active', 'inactive'];

    private CuaHang $stores;

    public function __construct(?CuaHang $stores = null)
    {
        $this->stores = $stores ?? new CuaHang();
    }

    public function listStores(): array
    {
        return $this->stores->listForAdmin();
    }

    /**
     * Thêm mới (id = 0) hoặc cập nhật cửa hàng. Trả về thông báo kết quả.
     *
     * @throws InvalidArgumentException khi thiếu tên/địa chỉ
     */
    public function save(int $id, string $name, string $address, string $phone, string $hours, string $status): string
    {
        $status = in_array($status, self::STATUSES, true) ? $status : 'active';

        if ($name === '' || $address === '') {
            throw new InvalidArgumentException('Vui lòng nhập tên và địa chỉ cửa hàng.');
        }

        if ($id > 0) {
            try {
                $this->stores->updateStore($id, $name, $address, $phone, $hours, $status);

                return 'Đã cập nhật cửa hàng thành công.';
            } catch (DatabaseException $exception) {
                return 'Không thể cập nhật cửa hàng.';
            }
        }

        try {
            $this->stores->createStore($name, $address, $phone, $hours, $status);

            return 'Đã thêm cửa hàng thành công.';
        } catch (DatabaseException $exception) {
            return 'Không thể thêm cửa hàng.';
        }
    }

    /** Xóa cửa hàng. Trả về thông báo kết quả. */
    public function delete(int $id): string
    {
        try {
            $this->stores->deleteStore($id);

            return 'Đã xóa cửa hàng thành công.';
        } catch (DatabaseException $exception) {
            return 'Không thể xóa cửa hàng.';
        }
    }
}
