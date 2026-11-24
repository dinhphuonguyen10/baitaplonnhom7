<?php

function getOrderWorkflowStatuses(): array
{
    return [
        'Chờ xác nhận',
        'Đã xác nhận',
        'Đang chuẩn bị hàng',
        'Đang giao hàng',
        'Đã giao hàng',
        'Hoàn thành',
        'Đã hủy',
    ];
}

function normalizeOrderWorkflowStatus(string $status): string
{
    $legacyStatuses = [
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
    return $legacyStatuses[$status] ?? $status;
}

function getAllowedOrderStatusTransitions(string $currentStatus): array
{
    $transitions = [
        'Chờ xác nhận' => ['Đã xác nhận', 'Đã hủy'],
        'Đã xác nhận' => ['Đang chuẩn bị hàng', 'Đã hủy'],
        'Đang chuẩn bị hàng' => ['Đang giao hàng', 'Đã hủy'],
        'Đang giao hàng' => ['Đã giao hàng'],
        'Đã giao hàng' => ['Hoàn thành'],
        'Hoàn thành' => [],
        'Đã hủy' => [],
    ];

    return $transitions[normalizeOrderWorkflowStatus($currentStatus)] ?? [];
}
