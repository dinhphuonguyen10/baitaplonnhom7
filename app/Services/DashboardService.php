<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;

/**
 * Lớp DashboardService - tính toán số liệu cho trang Tổng quan quản trị:
 * kỳ thời gian, kết quả kinh doanh, so sánh kỳ trước, đơn mới nhất, sản phẩm bán chạy, biểu đồ doanh thu.
 */
class DashboardService
{
    public const PERIOD_LABELS = [
        'today' => 'Hôm nay',
        'yesterday' => 'Hôm qua',
        'last_7' => '7 ngày qua',
        'this_month' => 'Tháng này',
        'last_month' => 'Tháng trước',
        'last_30' => '30 ngày gần nhất',
        'custom' => 'Tùy chọn ngày',
    ];

    private const COMPLETED_STATUSES_SQL = "('Đã giao hàng','Đã giao')";
    private const CANCELLED_STATUSES_SQL = "('Đã hủy','Canceled','cancelled','huy')";

    private const LEGACY_STATUS_MAP = [
        'Đang xử lý' => 'Đã xác nhận',
        'Đang chuẩn bị' => 'Chuẩn bị đơn hàng',
        'Đã giao' => 'Đã giao hàng',
        'Canceled' => 'Đã hủy',
        'cancelled' => 'Đã hủy',
        'huy' => 'Đã hủy',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Xác định kỳ thống kê từ tham số period/from/to.
     *
     * @return array{period: string, periodStart: DateTimeImmutable, periodEnd: DateTimeImmutable}
     */
    public function resolvePeriod(string $period, string $startInput, string $endInput, ?DateTimeImmutable $today = null): array
    {
        $today = $today ?? new DateTimeImmutable('today');
        if (!array_key_exists($period, self::PERIOD_LABELS)) {
            $period = 'this_month';
        }

        $periodStart = $today->modify('first day of this month');
        $periodEnd = $today;

        if ($period === 'today') {
            $periodStart = $today;
            $periodEnd = $today;
        } elseif ($period === 'yesterday') {
            $periodStart = $today->modify('-1 day');
            $periodEnd = $periodStart;
        } elseif ($period === 'last_7') {
            $periodStart = $today->modify('-6 days');
        } elseif ($period === 'last_month') {
            $periodStart = $today->modify('first day of last month');
            $periodEnd = $today->modify('last day of last month');
        } elseif ($period === 'last_30') {
            $periodStart = $today->modify('-29 days');
        } elseif ($period === 'custom') {
            $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
            $parsedEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
            if ($parsedStart && $parsedEnd
                && $parsedStart->format('Y-m-d') === $startInput
                && $parsedEnd->format('Y-m-d') === $endInput
                && $parsedStart <= $parsedEnd) {
                $periodStart = $parsedStart;
                $periodEnd = $parsedEnd;
            } else {
                $period = 'this_month';
                $periodStart = $today->modify('first day of this month');
                $periodEnd = $today;
            }
        }

        return ['period' => $period, 'periodStart' => $periodStart, 'periodEnd' => $periodEnd];
    }

    /** Tổng hợp toàn bộ dữ liệu cho trang Tổng quan. */
    public function overview(string $period, string $startInput, string $endInput): array
    {
        $resolved = $this->resolvePeriod($period, $startInput, $endInput);
        $period = $resolved['period'];
        /** @var DateTimeImmutable $periodStart */
        $periodStart = $resolved['periodStart'];
        /** @var DateTimeImmutable $periodEnd */
        $periodEnd = $resolved['periodEnd'];

        $periodEndExclusive = $periodEnd->modify('+1 day');
        $rangeStart = $periodStart->format('Y-m-d 00:00:00');
        $rangeEnd = $periodEndExclusive->format('Y-m-d 00:00:00');

        $stats = ['revenue' => 0, 'orders' => 0, 'completed' => 0, 'items_sold' => 0];
        $stats = array_merge($stats, $this->orderStats($rangeStart, $rangeEnd, true));
        $stats = array_merge($stats, $this->itemsSold($rangeStart, $rangeEnd));

        $periodDays = (int) $periodStart->diff($periodEndExclusive)->days;
        $previousPeriodEndExclusive = $periodStart;
        $previousPeriodStart = $periodStart->modify('-' . $periodDays . ' days');
        $previousRangeStart = $previousPeriodStart->format('Y-m-d 00:00:00');
        $previousRangeEnd = $previousPeriodEndExclusive->format('Y-m-d 00:00:00');
        $comparisonPeriodLabel = $previousPeriodStart->format('d/m/Y') . ' - ' . $previousPeriodEndExclusive->modify('-1 day')->format('d/m/Y');

        $previousStats = array_merge(['revenue' => 0, 'orders' => 0], $this->orderStats($previousRangeStart, $previousRangeEnd, false));

        $averageOrder = (int) $stats['completed'] > 0
            ? (float) $stats['revenue'] / (int) $stats['completed']
            : 0;

        $comparisonNotes = [
            'revenue' => self::formatComparison((float) $stats['revenue'], (float) $previousStats['revenue']),
            'orders' => self::formatComparison((float) $stats['orders'], (float) $previousStats['orders']),
        ];

        $dailyCurrentRevenue = $this->dailyRevenue($rangeStart, $rangeEnd);
        $dailyPreviousRevenue = $this->dailyRevenue($previousRangeStart, $previousRangeEnd);
        $chartDays = [];
        for ($dayIndex = 0; $dayIndex < $periodDays; $dayIndex++) {
            $currentDate = $periodStart->modify('+' . $dayIndex . ' days');
            $previousDate = $previousPeriodStart->modify('+' . $dayIndex . ' days');
            $chartDays[] = [
                'label' => $currentDate->format('d/m'),
                'current' => $dailyCurrentRevenue[$currentDate->format('Y-m-d')] ?? 0.0,
                'previous' => $dailyPreviousRevenue[$previousDate->format('Y-m-d')] ?? 0.0,
            ];
        }
        $chartValues = [];
        foreach ($chartDays as $chartDay) {
            $chartValues[] = $chartDay['current'];
            $chartValues[] = $chartDay['previous'];
        }
        $chartMax = max($chartValues);
        $chartMax = $chartMax > 0 ? ceil($chartMax / 5000000) * 5000000 : 5000000;

        return [
            'period' => $period,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'stats' => $stats,
            'previousStats' => $previousStats,
            'periodDays' => $periodDays,
            'comparisonPeriodLabel' => $comparisonPeriodLabel,
            'averageOrder' => $averageOrder,
            'comparisonNotes' => $comparisonNotes,
            'statusCounts' => $this->statusCounts($rangeStart, $rangeEnd),
            'recentOrders' => $this->recentOrders(),
            'topProducts' => $this->topProducts($rangeStart, $rangeEnd),
            'chartDays' => $chartDays,
            'chartMax' => $chartMax,
            'periodLabel' => self::PERIOD_LABELS[$period],
        ];
    }

    /** Doanh thu (đơn đã giao), số đơn và (tùy chọn) số đơn hoàn thành trong khoảng thời gian. */
    private function orderStats(string $start, string $end, bool $withCompleted): array
    {
        $completedColumn = $withCompleted
            ? ",\n        COALESCE(SUM(status IN " . self::COMPLETED_STATUSES_SQL . "), 0) AS completed"
            : '';

        return $this->db->fetchOne(
            "SELECT
        COALESCE(SUM(CASE WHEN status IN " . self::COMPLETED_STATUSES_SQL . " THEN total_price ELSE 0 END), 0) AS revenue,
        COUNT(*) AS orders{$completedColumn}
     FROM orders
     WHERE created_at >= ? AND created_at < ?",
            [$start, $end],
            'ss'
        ) ?? [];
    }

    /** Tổng số lượng sản phẩm đã bán (trừ đơn hủy). */
    private function itemsSold(string $start, string $end): array
    {
        return $this->db->fetchOne(
            "SELECT COALESCE(SUM(oi.quantity),0) AS items_sold
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at >= ? AND o.created_at < ?
       AND o.status NOT IN " . self::CANCELLED_STATUSES_SQL,
            [$start, $end],
            'ss'
        ) ?? [];
    }

    /** Số đơn theo trạng thái (đã chuẩn hóa trạng thái cũ). */
    private function statusCounts(string $start, string $end): array
    {
        $statusCounts = [
            'Chờ xác nhận' => 0,
            'Đã xác nhận' => 0,
            'Chuẩn bị đơn hàng' => 0,
            'Đang vận chuyển' => 0,
            'Đang giao' => 0,
            'Đã giao hàng' => 0,
            'Đã hủy' => 0,
        ];
        $rows = $this->db->fetchAll(
            'SELECT status, COUNT(*) AS order_count FROM orders WHERE created_at >= ? AND created_at < ? GROUP BY status',
            [$start, $end],
            'ss'
        );
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $normalizedStatus = self::LEGACY_STATUS_MAP[$status] ?? $status;
            if (isset($statusCounts[$normalizedStatus])) {
                $statusCounts[$normalizedStatus] += (int) $row['order_count'];
            }
        }

        return $statusCounts;
    }

    /** 5 đơn hàng mới nhất. */
    private function recentOrders(): array
    {
        return $this->db->fetchAll(
            'SELECT id, full_name, total_price, status, created_at FROM orders ORDER BY created_at DESC, id DESC LIMIT 5'
        );
    }

    /** 3 sản phẩm bán chạy nhất trong kỳ. */
    private function topProducts(string $start, string $end): array
    {
        return $this->db->fetchAll(
            "SELECT oi.product_name,
            COALESCE(MAX(p.image), '') AS image,
            COALESCE(SUM(oi.quantity),0) AS quantity_sold,
            COALESCE(SUM(oi.quantity * oi.price),0) AS product_revenue
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE o.created_at >= ? AND o.created_at < ?
       AND o.status NOT IN " . self::CANCELLED_STATUSES_SQL . "
     GROUP BY oi.product_name
     ORDER BY quantity_sold DESC
     LIMIT 3",
            [$start, $end],
            'ss'
        );
    }

    /** Doanh thu theo ngày (đơn đã giao): [Y-m-d => doanh thu]. */
    private function dailyRevenue(string $start, string $end): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE(created_at) AS sale_date, COALESCE(SUM(total_price),0) AS revenue
     FROM orders
     WHERE status IN " . self::COMPLETED_STATUSES_SQL . "
       AND created_at >= ? AND created_at < ?
     GROUP BY DATE(created_at)",
            [$start, $end],
            'ss'
        );
        $dailyRevenue = [];
        foreach ($rows as $row) {
            $dailyRevenue[(string) $row['sale_date']] = (float) $row['revenue'];
        }

        return $dailyRevenue;
    }

    /** So sánh với kỳ trước: ['class' => is-up|is-down|is-neutral, 'text' => '↑ 12,5%']. */
    public static function formatComparison(float $current, float $previous): array
    {
        if ($previous == 0.0) {
            return ['class' => 'is-neutral', 'text' => $current == 0.0 ? '0%' : '—'];
        }
        $change = (($current - $previous) / abs($previous)) * 100;
        if (abs($change) < 0.05) {
            return ['class' => 'is-neutral', 'text' => '0%'];
        }

        return [
            'class' => $change > 0 ? 'is-up' : 'is-down',
            'text' => ($change > 0 ? '↑ ' : '↓ ') . number_format(abs($change), 1, ',', '.') . '%',
        ];
    }

    /** Định dạng số tiền rút gọn: 1,2t / 15tr / 500k / 900đ. */
    public static function formatCompact(float $value): string
    {
        if ($value >= 1000000000) {
            return number_format($value / 1000000000, 1, ',', '.') . 't';
        }
        if ($value >= 1000000) {
            return number_format($value / 1000000, 0, ',', '.') . 'tr';
        }
        if ($value >= 1000) {
            return number_format($value / 1000, 0, ',', '.') . 'k';
        }

        return number_format($value, 0, ',', '.') . 'đ';
    }
}
