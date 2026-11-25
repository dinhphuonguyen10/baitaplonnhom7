<?php

namespace App\Services\Reports;

use App\Core\Database;

/**
 * Lớp CustomerReport - báo cáo khách hàng (kế thừa AbstractReport):
 *  - new:     khách hàng mới đăng ký trong kỳ
 *  - repeat:  khách mua từ 2 đơn trở lên trong kỳ
 *  - revenue: khách hàng theo doanh thu đã giao
 *  - orders:  khách hàng theo số đơn hàng
 */
class CustomerReport extends AbstractReport
{
    public const TYPES = [
        'new' => 'Khách hàng mới theo thời gian',
        'repeat' => 'Khách hàng mua trở lại',
        'revenue' => 'Khách hàng theo doanh thu',
        'orders' => 'Khách hàng theo số đơn hàng',
    ];

    private string $type;

    /** Dòng dữ liệu thô từ CSDL. */
    private array $rows = [];

    public function __construct(ReportPeriod $range, string $type, ?string $customTitle = null, ?Database $db = null)
    {
        parent::__construct($range, $customTitle, '', $db);
        $this->type = isset(self::TYPES[$type]) ? $type : 'new';
    }

    public function type(): string
    {
        return $this->type;
    }

    public function load(): void
    {
        if (!$this->range->isValid()) {
            $this->error = 'Khoảng thời gian không hợp lệ. Vui lòng chọn ngày bắt đầu và kết thúc hợp lệ.';
        }

        $startDate = $this->range->startDate();
        $endExclusive = $this->range->endExclusive();

        $sql = "SELECT u.id, u.username, u.phone, u.created_at,
                           COUNT(DISTINCT o.id) AS order_count,
                           COALESCE(SUM(CASE WHEN o.status IN ('Đã giao hàng','Đã giao') THEN o.total_price ELSE 0 END),0) AS revenue,
                           MAX(o.created_at) AS last_order
                    FROM users u
                    LEFT JOIN orders o ON o.user_id = u.id
                      AND o.created_at >= ? AND o.created_at < ?
                      AND o.status NOT IN ('Đã hủy','Canceled','cancelled','huy')";
        $params = [$startDate, $endExclusive];
        if ($this->type === 'new') {
            $sql .= ' WHERE u.created_at >= ? AND u.created_at < ?';
            $params[] = $startDate;
            $params[] = $endExclusive;
        }
        $sql .= ' GROUP BY u.id, u.username, u.phone, u.created_at';
        if ($this->type === 'repeat') {
            $sql .= ' HAVING COUNT(DISTINCT o.id) >= 2';
        } elseif ($this->type !== 'new') {
            $sql .= ' HAVING COUNT(DISTINCT o.id) > 0';
        }
        if ($this->type === 'revenue') {
            $sql .= ' ORDER BY revenue DESC, order_count DESC';
        } elseif ($this->type === 'orders' || $this->type === 'repeat') {
            $sql .= ' ORDER BY order_count DESC, revenue DESC';
        } else {
            $sql .= ' ORDER BY u.created_at DESC';
        }
        $sql .= ' LIMIT 100';

        $rows = $this->runQuery($sql, $params, 'Không thể tải báo cáo khách hàng.', 'Admin customer report query failed', true);
        $this->rows = $rows ?? [];
    }

    public function title(): string
    {
        return $this->customTitle ?: (self::TYPES[$this->type] ?? 'Báo cáo khách hàng');
    }

    /** Khoảng thời gian dạng Y-m-d - Y-m-d. */
    public function rangeLabel(): string
    {
        return $this->range->startDate() . ' - ' . $this->range->endDate();
    }

    public function detailHeaders(): array
    {
        return $this->type === 'new'
            ? ['Tên tài khoản', 'Số điện thoại', 'Ngày đăng ký', 'Đơn trong kỳ']
            : ['Tên tài khoản', 'Số điện thoại', 'Số đơn trong kỳ', 'Doanh thu đã giao', 'Đơn gần nhất'];
    }

    public function detailRows(): array
    {
        $detailRows = [];
        foreach ($this->rows as $customer) {
            if ($this->type === 'new') {
                $detailRows[] = [
                    (string) $customer['username'],
                    (string) ($customer['phone'] ?: '—'),
                    self::dateTime($customer['created_at']),
                    number_format((int) $customer['order_count']),
                ];
            } else {
                $detailRows[] = [
                    (string) $customer['username'],
                    (string) ($customer['phone'] ?: '—'),
                    number_format((int) $customer['order_count']),
                    self::money($customer['revenue']),
                    $customer['last_order'] ? self::dateTime($customer['last_order']) : '—',
                ];
            }
        }

        return $detailRows;
    }

    public function exportRows(): array
    {
        $excelRows = [
            ['BÁO CÁO KHÁCH HÀNG - ÉCRIN JEWELRY'],
            ['Loại báo cáo', $this->title()],
            ['Khoảng thời gian', $this->rangeLabel()],
            ['Thời gian xuất', date('d/m/Y H:i')],
            [],
            $this->detailHeaders(),
        ];
        foreach ($this->rows as $customer) {
            if ($this->type === 'new') {
                $excelRows[] = [
                    (string) $customer['username'],
                    (string) ($customer['phone'] ?: '—'),
                    self::dateTime($customer['created_at']),
                    (int) $customer['order_count'],
                ];
            } else {
                $excelRows[] = [
                    (string) $customer['username'],
                    (string) ($customer['phone'] ?: '—'),
                    (int) $customer['order_count'],
                    (float) $customer['revenue'],
                    $customer['last_order'] ? self::dateTime($customer['last_order']) : '—',
                ];
            }
        }

        return $excelRows;
    }

    public function exportFilename(): string
    {
        return 'bao-cao-khach-hang-' . $this->type . '-' . $this->range->startDate() . '-den-' . $this->range->endDate();
    }
}
