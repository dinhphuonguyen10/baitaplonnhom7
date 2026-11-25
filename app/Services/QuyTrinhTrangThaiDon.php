<?php

namespace App\Services;

/**
 * Lớp QuyTrinhTrangThaiDon - quy trình trạng thái đơn hàng:
 * danh sách trạng thái, chuẩn hóa trạng thái cũ và các bước chuyển hợp lệ.
 */
final class QuyTrinhTrangThaiDon
{
    public const STATUSES = [
        'Chờ xác nhận',
        'Đã xác nhận',
        'Đang chuẩn bị hàng',
        'Đang giao hàng',
        'Đã giao hàng',
        'Hoàn thành',
        'Đã hủy',
    ];

    /** Trạng thái cũ => trạng thái chuẩn. */
    public const LEGACY_STATUSES = [
        'Pending' => 'Chờ xác nhận',
        'Đang xử lý' => 'Đã xác nhận',
        'Chuẩn bị đơn hàng' => 'Đang chuẩn bị hàng',
        'Đang chuẩn bị' => 'Đang chuẩn bị hàng',
        'Đang vận chuyển' => 'Đang giao hàng',
        'Đang giao' => 'Đang giao hàng',
        'Đã giao' => 'Đã giao hàng',
        'Canceled' => 'Đã hủy',
        'cancelled' => 'Đã hủy',
        'huy' => 'Đã hủy',
    ];

    /** Trạng thái hiện tại => các trạng thái được phép chuyển tới. */
    public const TRANSITIONS = [
        'Chờ xác nhận' => ['Đã xác nhận', 'Đã hủy'],
        'Đã xác nhận' => ['Đang chuẩn bị hàng', 'Đã hủy'],
        'Đang chuẩn bị hàng' => ['Đang giao hàng', 'Đã hủy'],
        'Đang giao hàng' => ['Đã giao hàng'],
        'Đã giao hàng' => ['Hoàn thành'],
        'Hoàn thành' => [],
        'Đã hủy' => [],
    ];

    public static function statuses(): array
    {
        return self::STATUSES;
    }

    public static function normalize(string $status): string
    {
        return self::LEGACY_STATUSES[$status] ?? $status;
    }

    public static function allowedTransitions(string $currentStatus): array
    {
        return self::TRANSITIONS[self::normalize($currentStatus)] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowedTransitions($from), true);
    }
}
