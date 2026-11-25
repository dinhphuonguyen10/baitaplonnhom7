<?php

namespace App\Helpers;

use App\Services\QuyTrinhTrangThaiDon;

/**
 * Lớp DinhDangDonHangQuanTri - tiện ích hiển thị trạng thái đơn hàng ở trang quản trị:
 * chuẩn hóa trạng thái cũ, chọn lớp CSS cho nhãn trạng thái/thanh toán,
 * các bước chuyển trạng thái hợp lệ (theo QuyTrinhTrangThaiDon).
 */
final class DinhDangDonHangQuanTri
{
    /** Các trạng thái đơn hàng hợp lệ (theo thứ tự xử lý). */
    public const STATUSES = QuyTrinhTrangThaiDon::STATUSES;

    /** Các giá trị được coi là đơn đã hủy. */
    public const CANCELLED_STATUSES = ['Đã hủy', 'Canceled', 'cancelled', 'huy'];

    /** Lớp CSS của nhãn trạng thái đơn (theo trạng thái đã chuẩn hóa). */
    public const STATUS_CLASSES = [
        'Chờ xác nhận' => 'st-pending',
        'Đã xác nhận' => 'st-confirmed',
        'Đang chuẩn bị hàng' => 'st-preparing',
        'Đang giao hàng' => 'st-shipping',
        'Đã giao hàng' => 'st-completed',
        'Hoàn thành' => 'st-completed',
        'Đã hủy' => 'st-cancelled',
    ];

    /** Chuẩn hóa trạng thái cũ sang trạng thái mới. */
    public static function normalize(string $status): string
    {
        return QuyTrinhTrangThaiDon::normalize($status);
    }

    /** Lớp CSS cho nhãn trạng thái (dựa trên trạng thái đã chuẩn hóa). */
    public static function statusClass(string $normalizedStatus): string
    {
        return self::STATUS_CLASSES[$normalizedStatus] ?? 'st-pending';
    }

    /** Các trạng thái tiếp theo được phép chuyển tới từ trạng thái hiện tại. */
    public static function allowedTransitions(string $status): array
    {
        return QuyTrinhTrangThaiDon::allowedTransitions($status);
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
