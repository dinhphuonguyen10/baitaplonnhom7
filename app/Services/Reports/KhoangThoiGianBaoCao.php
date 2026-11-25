<?php

namespace App\Services\Reports;

use DateTimeImmutable;

/**
 * Lớp KhoangThoiGianBaoCao - khoảng thời gian của báo cáo (value object).
 * Tính ngày bắt đầu/kết thúc từ lựa chọn nhanh (hôm nay, 7 ngày qua, tháng này...)
 * hoặc khoảng ngày tùy chọn (from/to dạng Y-m-d).
 */
final class KhoangThoiGianBaoCao
{
    public const PERIODS = ['today', 'yesterday', 'last_7', 'this_month', 'last_month', 'last_30', 'custom'];

    private string $period;

    private DateTimeImmutable $from;

    private DateTimeImmutable $to;

    private bool $valid = true;

    private function __construct(string $period, DateTimeImmutable $from, DateTimeImmutable $to, bool $valid)
    {
        $this->period = $period;
        $this->from = $from;
        $this->to = $to;
        $this->valid = $valid;
    }

    /**
     * Tạo khoảng thời gian từ tham số người dùng. Lựa chọn không hợp lệ => "tháng này".
     * Khoảng tùy chọn sai định dạng => giữ "tháng này" và đánh dấu isValid() = false.
     */
    public static function fromInput(string $period, string $startInput, string $endInput): self
    {
        if (!in_array($period, self::PERIODS, true)) {
            $period = 'this_month';
        }

        $today = new DateTimeImmutable('today');
        $dateFrom = $today->modify('first day of this month');
        $dateTo = $today;
        $valid = true;

        if ($period === 'today') {
            $dateFrom = $today;
            $dateTo = $today;
        } elseif ($period === 'yesterday') {
            $dateFrom = $today->modify('-1 day');
            $dateTo = $dateFrom;
        } elseif ($period === 'last_7') {
            $dateFrom = $today->modify('-6 days');
        } elseif ($period === 'last_month') {
            $dateFrom = $today->modify('first day of last month');
            $dateTo = $today->modify('last day of last month');
        } elseif ($period === 'last_30') {
            $dateFrom = $today->modify('-29 days');
        } elseif ($period === 'custom') {
            $parsedStart = DateTimeImmutable::createFromFormat('!Y-m-d', $startInput);
            $parsedEnd = DateTimeImmutable::createFromFormat('!Y-m-d', $endInput);
            if ($parsedStart && $parsedEnd && $parsedStart->format('Y-m-d') === $startInput
                && $parsedEnd->format('Y-m-d') === $endInput && $parsedStart <= $parsedEnd) {
                $dateFrom = $parsedStart;
                $dateTo = $parsedEnd;
            } else {
                $valid = false;
            }
        }

        return new self($period, $dateFrom, $dateTo, $valid);
    }

    /** Lựa chọn khoảng thời gian (đã chuẩn hóa). */
    public function period(): string
    {
        return $this->period;
    }

    /** false nếu khoảng ngày tùy chọn không hợp lệ. */
    public function isValid(): bool
    {
        return $this->valid;
    }

    public function from(): DateTimeImmutable
    {
        return $this->from;
    }

    public function to(): DateTimeImmutable
    {
        return $this->to;
    }

    /** Ngày bắt đầu (Y-m-d). */
    public function startDate(): string
    {
        return $this->from->format('Y-m-d');
    }

    /** Ngày kết thúc (Y-m-d). */
    public function endDate(): string
    {
        return $this->to->format('Y-m-d');
    }

    /** Ngày sau ngày kết thúc (Y-m-d) - dùng cho điều kiện created_at < ?. */
    public function endExclusive(): string
    {
        return $this->to->modify('+1 day')->format('Y-m-d');
    }

    /** Số ngày trong khoảng (tính cả hai đầu). */
    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /** Nhãn hiển thị dạng d/m/Y - d/m/Y. */
    public function label(): string
    {
        return $this->from->format('d/m/Y') . ' - ' . $this->to->format('d/m/Y');
    }
}
