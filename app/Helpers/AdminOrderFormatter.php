<?php

namespace App\Helpers;

/**
 * Lớp AdminOrderFormatter - tiện ích hiển thị trạng thái đơn hàng ở trang quản trị:
 * chuẩn hóa trạng thái cũ, chọn lớp CSS cho nhãn trạng thái/thanh toán.
 */
final class AdminOrderFormatter
{
    /** Các trạng thái đơn hàng hợp lệ (theo thứ tự xử lý). */
    public const STATUSES = ['Chờ xác nhận', 'Đã xác nhận', 'Chuẩn bị đơn hàng', 'Đang vận chuyển', 'Đang giao', 'Đã giao hàng', 'Đã hủy'];

    /** Trạng thái cũ => trạng thái mới tương ứng. */
    public const LEGACY_STATUS_MAP = [
        'Đang xử lý' => 'Đã xác nhận',
        'Đang chuẩn bị' => 'Chuẩn bị đơn hàng',
        'Đang giao' => 'Đang giao',
        'Đã giao' => 'Đã giao hàng',
    ];

    /** Các giá trị được coi là đơn đã hủy. */
    public const CANCELLED_STATUSES = ['Đã hủy', 'Canceled', 'cancelled', 'huy'];

    /** Lớp CSS của nhãn trạng thái đơn. */
    public const STATUS_CLASSES = [
        'Chờ xác nhận' => 'st-pending',
        'Đã xác nhận' => 'st-confirmed',
        'Chuẩn bị đơn hàng' => 'st-preparing',
        'Đang vận chuyển' => 'st-shipping',
        'Đang giao' => 'st-shipping',
        'Đã giao hàng' => 'st-completed',
        'Đang xử lý' => 'st-confirmed',
        'Đang chuẩn bị' => 'st-preparing',
        'Đã giao' => 'st-completed',
        'Đã hủy' => 'st-cancelled',
    ];

    /** Chuẩn hóa trạng thái cũ sang trạng thái mới. */
    public static function normalize(string $status): string
    {
        return self::LEGACY_STATUS_MAP[$status] ?? $status;
    }

    /** Lớp CSS cho nhãn trạng thái (dựa trên trạng thái gốc). */
    public static function statusClass(string $status): string
    {
        return self::STATUS_CLASSES[$status] ?? 'st-pending';
    }

    public static function isCancelled(string $normalizedStatus): bool
    {
        return in_array($normalizedStatus, self::CANCELLED_STATUSES, true);
    }

    /** Chuỗi tìm kiếm (tên khách, SĐT, mã đơn) dạng chữ thường. */
    public static function searchText(array $order): string
    {
        return strtolower($order['customer'] . ' ' . $order['phone'] . ' ' . $order['id']);
    }
}
