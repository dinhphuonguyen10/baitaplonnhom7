<?php

namespace App\Services\Reports;

use App\Core\Database;
use App\Models\DanhMuc;
use DateTimeImmutable;
use Throwable;

/**
 * Lớp BaoCaoDoanhThu - báo cáo bán hàng (kế thừa BaoCaoCoSo).
 * Gồm tổng quan chỉ số và 4 mục chi tiết: doanh thu theo thời gian, theo danh mục,
 * sản phẩm bán chạy, đơn hàng theo trạng thái. Có thể lọc theo danh mục sản phẩm.
 */
class BaoCaoDoanhThu extends BaoCaoCoSo
{
    public const SECTIONS = [
        'revenue' => 'Doanh thu theo thời gian',
        'category' => 'Doanh thu theo danh mục',
        'products' => 'Sản phẩm bán chạy',
        'orders' => 'Đơn hàng theo trạng thái',
    ];

    /** id phần tử HTML của bảng tương ứng mỗi mục. */
    public const SECTION_TARGETS = [
        'revenue' => 'sales-revenue-table',
        'category' => 'sales-category-table',
        'products' => 'sales-top-products',
        'orders' => 'sales-order-status',
    ];

    private const DELIVERED = "('Đã giao hàng', 'Đã giao', 'Hoàn thành')";

    private const CANCELLED = "('Đã hủy', 'Canceled', 'cancelled', 'huy')";

    private string $section;

    /** @var mixed giá trị category_id người dùng gửi lên */
    private $categoryInput;

    private int $categoryId = 0;

    /** @var array<int, string> */
    private array $categories = [];

    private array $summary = [
        'total_orders' => 0,
        'active_orders' => 0,
        'completed_orders' => 0,
        'cancelled_orders' => 0,
        'revenue' => 0,
        'new_customers' => 0,
        'items_sold' => 0,
    ];

    private array $trendRows = [];

    private array $topProducts = [];

    private array $categoryRevenue = [];

    private array $statusCounts = [
        'Chờ xác nhận' => 0,
        'Đã xác nhận' => 0,
        'Đang chuẩn bị hàng' => 0,
        'Đang giao hàng' => 0,
        'Đã giao hàng' => 0,
        'Hoàn thành' => 0,
        'Đã hủy' => 0,
    ];

    private DanhMuc $categoryModel;

    /**
     * @param mixed $categoryInput giá trị category_id thô
     */
    public function __construct(
        KhoangThoiGianBaoCao $range,
        string $section,
        $categoryInput,
        ?string $customTitle = null,
        string $initialError = '',
        ?Database $db = null,
        ?DanhMuc $categoryModel = null
    ) {
        parent::__construct($range, $customTitle, $initialError, $db);
        $this->section = isset(self::SECTIONS[$section]) ? $section : 'revenue';
        $this->categoryInput = $categoryInput;
        $this->categoryModel = $categoryModel ?? new DanhMuc($this->db);
    }

    public function section(): string
    {
        return $this->section;
    }

    public function categoryId(): int
    {
        return $this->categoryId;
    }

    /** @return array<int, string> danh mục (id => tên) */
    public function categories(): array
    {
        return $this->categories;
    }

    public function summary(): array
    {
        return $this->summary;
    }

    public function load(): void
    {
        try {
            foreach ($this->categoryModel->findAllIdNameOrderedByName() as $row) {
                $this->categories[(int) $row['id']] = $row['name'];
            }
        } catch (Throwable $e) {
            $this->error = 'Không thể tải danh mục sản phẩm.';
            error_log('Admin report categories failed: ' . $e->getMessage());
        }

        if (!$this->range->isValid()) {
            $this->error = 'Khoảng thời gian không hợp lệ. Vui lòng chọn ngày bắt đầu và kết thúc hợp lệ.';
        }

        $categoryId = filter_var($this->categoryInput, FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId < 0 || ($categoryId > 0 && !isset($this->categories[$categoryId]))) {
            $categoryId = 0;
            $this->error = $this->error ?: 'Danh mục được chọn không hợp lệ.';
        }
        $this->categoryId = (int) $categoryId;

        $this->loadSummary();
        $this->loadTrend();
        $this->loadTopProducts();
        $this->loadCategoryRevenue();
        $this->loadStatusCounts();
    }

    /** Truy vấn báo cáo với 2 tham số ngày (+ tham số bổ sung); lỗi chỉ ghi nhận nếu chưa có lỗi trước đó. */
    private function query(string $sql, array $params): ?array
    {
        return $this->runQuery($sql, $params, 'Không thể tải dữ liệu báo cáo.', 'Admin report query failed');
    }

    /** Điều kiện lọc đơn hàng có sản phẩm thuộc danh mục đang chọn (kèm tham số). */
    private function categoryCondition(): array
    {
        if ($this->categoryId < 1) {
            return ['', []];
        }

        return [
            ' AND EXISTS (SELECT 1 FROM order_items filter_items INNER JOIN products filter_products ON filter_products.id = filter_items.product_id WHERE filter_items.order_id = o.id AND filter_products.category_id = ?)',
            [$this->categoryId],
        ];
    }

    private function dateParams(): array
    {
        return [$this->range->startDate(), $this->range->endExclusive()];
    }

    private function loadSummary(): void
    {
        [$condition, $conditionParams] = $this->categoryCondition();
        $rows = $this->query(
            "SELECT COUNT(*) AS total_orders,
            COALESCE(SUM(o.status NOT IN " . self::CANCELLED . "), 0) AS active_orders,
            COALESCE(SUM(o.status IN " . self::DELIVERED . "), 0) AS completed_orders,
            COALESCE(SUM(o.status IN " . self::CANCELLED . "), 0) AS cancelled_orders
     FROM orders o
     WHERE o.created_at >= ? AND o.created_at < ?{$condition}",
            array_merge($this->dateParams(), $conditionParams)
        );
        if ($rows !== null) {
            $this->summary = array_merge($this->summary, $rows[0] ?? []);
        }

        if ($this->categoryId > 0) {
            $revenueSql = "SELECT COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue,
                          COALESCE(SUM(oi.quantity), 0) AS items_sold
                   FROM orders o
                   INNER JOIN order_items oi ON oi.order_id = o.id
                   INNER JOIN products p ON p.id = oi.product_id AND p.category_id = ?
                   WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.status IN " . self::DELIVERED;
            $revenueParams = array_merge([$this->categoryId], $this->dateParams());
        } else {
            $revenueSql = "SELECT COALESCE(SUM(o.total_price), 0) AS revenue,
                          COALESCE(SUM((
                              SELECT SUM(oi.quantity)
                              FROM order_items oi
                              WHERE oi.order_id = o.id
                          )), 0) AS items_sold
                   FROM orders o
                   WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.status IN " . self::DELIVERED;
            $revenueParams = $this->dateParams();
        }
        $rows = $this->query($revenueSql, $revenueParams);
        if ($rows !== null) {
            $this->summary = array_merge($this->summary, $rows[0] ?? []);
        }

        $rows = $this->query(
            'SELECT COUNT(*) AS new_customers FROM users WHERE created_at >= ? AND created_at < ?',
            $this->dateParams()
        );
        if ($rows !== null) {
            $this->summary['new_customers'] = (int) ($rows[0]['new_customers'] ?? 0);
        }
    }

    /** Doanh thu theo ngày (hoặc theo tháng nếu khoảng thời gian > 62 ngày). */
    private function loadTrend(): void
    {
        $dateFrom = $this->range->from();
        $dateTo = $this->range->to();
        $groupByMonth = $this->range->days() > 62;
        $bucketExpression = $groupByMonth
            ? "DATE_FORMAT(o.created_at, '%Y-%m')"
            : "DATE_FORMAT(o.created_at, '%Y-%m-%d')";
        $timeBucketCount = $groupByMonth
            ? ((int) $dateTo->format('Y') * 12 + (int) $dateTo->format('n')) - ((int) $dateFrom->format('Y') * 12 + (int) $dateFrom->format('n')) + 1
            : $this->range->days();

        if ($this->categoryId > 0) {
            [$condition, $conditionParams] = $this->categoryCondition();
            $trendSql = "SELECT {$bucketExpression} AS report_bucket,
                        COUNT(DISTINCT o.id) AS order_count,
                        COALESCE(SUM(CASE WHEN o.status IN " . self::DELIVERED . " AND p.id IS NOT NULL THEN oi.quantity ELSE 0 END), 0) AS items_sold,
                        COALESCE(SUM(CASE WHEN o.status IN " . self::DELIVERED . " AND p.id IS NOT NULL THEN oi.quantity * oi.price ELSE 0 END), 0) AS revenue
                 FROM orders o
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 LEFT JOIN products p ON p.id = oi.product_id AND p.category_id = ?
                 WHERE o.created_at >= ? AND o.created_at < ?{$condition}
                 GROUP BY report_bucket
                 ORDER BY report_bucket";
            $params = array_merge([$this->categoryId], $this->dateParams(), $conditionParams);
        } else {
            $trendSql = "SELECT {$bucketExpression} AS report_bucket,
                        COUNT(*) AS order_count,
                        COALESCE(SUM(CASE WHEN o.status IN " . self::DELIVERED . " THEN (
                            SELECT COALESCE(SUM(oi.quantity), 0)
                            FROM order_items oi
                            WHERE oi.order_id = o.id
                        ) ELSE 0 END), 0) AS items_sold,
                        COALESCE(SUM(CASE WHEN o.status IN " . self::DELIVERED . " THEN o.total_price ELSE 0 END), 0) AS revenue
                 FROM orders o
                 WHERE o.created_at >= ? AND o.created_at < ?
                 GROUP BY report_bucket
                 ORDER BY report_bucket";
            $params = $this->dateParams();
        }

        $buckets = [];
        foreach ($this->query($trendSql, $params) ?? [] as $row) {
            $bucketDate = new DateTimeImmutable((string) $row['report_bucket']);
            if ($groupByMonth) {
                $bucketIndex = ((int) $bucketDate->format('Y') * 12 + (int) $bucketDate->format('n'))
                    - ((int) $dateFrom->format('Y') * 12 + (int) $dateFrom->format('n'));
            } else {
                $bucketIndex = (int) $dateFrom->diff($bucketDate)->days;
            }
            $buckets[$bucketIndex] = [
                'revenue' => (float) $row['revenue'],
                'order_count' => (int) $row['order_count'],
                'items_sold' => (int) $row['items_sold'],
            ];
        }

        $this->trendRows = [];
        for ($slot = 0; $slot < $timeBucketCount; $slot++) {
            $currentPoint = $buckets[$slot] ?? ['revenue' => 0.0, 'order_count' => 0, 'items_sold' => 0];
            $pointDate = $groupByMonth
                ? $dateFrom->modify('first day of this month')->modify('+' . $slot . ' months')
                : $dateFrom->modify('+' . $slot . ' days');
            $this->trendRows[] = [
                'bucket' => $slot,
                'table_label' => $groupByMonth ? $pointDate->format('m/Y') : $pointDate->format('d/m/Y'),
                'revenue' => $currentPoint['revenue'],
                'order_count' => $currentPoint['order_count'],
                'items_sold' => $currentPoint['items_sold'],
            ];
        }
    }

    private function loadTopProducts(): void
    {
        $params = $this->dateParams();
        $categoryFilter = '';
        if ($this->categoryId > 0) {
            $categoryFilter = ' AND p.category_id = ?';
            $params[] = $this->categoryId;
        }
        $this->topProducts = $this->query(
            "SELECT oi.product_name,
                          SUM(oi.quantity) AS quantity_sold,
                          SUM(oi.quantity * oi.price) AS product_revenue
                   FROM order_items oi
                   INNER JOIN orders o ON o.id = oi.order_id
                   LEFT JOIN products p ON p.id = oi.product_id
                   WHERE o.created_at >= ? AND o.created_at < ?
                     AND o.status IN " . self::DELIVERED . $categoryFilter .
            ' GROUP BY oi.product_id, oi.product_name
      ORDER BY quantity_sold DESC, product_revenue DESC, oi.product_name ASC, oi.product_id ASC',
            $params
        ) ?? [];
    }

    private function loadCategoryRevenue(): void
    {
        $params = $this->dateParams();
        $categoryFilter = '';
        if ($this->categoryId > 0) {
            $categoryFilter = ' AND p.category_id = ?';
            $params[] = $this->categoryId;
        }
        $this->categoryRevenue = $this->query(
            "SELECT c.name AS category_name,
                              COALESCE(SUM(oi.quantity * oi.price), 0) AS revenue,
                              COALESCE(SUM(oi.quantity), 0) AS items_sold
                       FROM orders o
                       INNER JOIN order_items oi ON oi.order_id = o.id
                       INNER JOIN products p ON p.id = oi.product_id
                       INNER JOIN categories c ON c.id = p.category_id
                       WHERE o.created_at >= ? AND o.created_at < ?
                         AND o.status IN " . self::DELIVERED . $categoryFilter . "
                       GROUP BY c.id, c.name
                       ORDER BY revenue DESC, items_sold DESC, c.name ASC",
            $params
        ) ?? [];
    }

    private function loadStatusCounts(): void
    {
        [$condition, $conditionParams] = $this->categoryCondition();
        $rows = $this->query(
            "SELECT CASE
                WHEN o.status IN ('Đang xử lý') THEN 'Đã xác nhận'
                WHEN o.status IN ('Chuẩn bị đơn hàng', 'Đang chuẩn bị') THEN 'Đang chuẩn bị hàng'
                WHEN o.status IN ('Đang vận chuyển', 'Đang giao') THEN 'Đang giao hàng'
                WHEN o.status IN ('Đã giao') THEN 'Đã giao hàng'
                WHEN o.status IN ('Canceled', 'cancelled', 'huy') THEN 'Đã hủy'
                ELSE o.status
            END AS report_status,
            COUNT(*) AS order_count
     FROM orders o
     WHERE o.created_at >= ? AND o.created_at < ?{$condition}
     GROUP BY report_status",
            array_merge($this->dateParams(), $conditionParams)
        );
        foreach ($rows ?? [] as $row) {
            if (isset($this->statusCounts[$row['report_status']])) {
                $this->statusCounts[$row['report_status']] += (int) $row['order_count'];
            }
        }
    }

    public function title(): string
    {
        return $this->customTitle ?: (self::SECTIONS[$this->section] ?? 'Báo cáo bán hàng');
    }

    public function detailHeaders(): array
    {
        switch ($this->section) {
            case 'revenue':
                return ['Ngày', 'Số đơn hàng', 'Sản phẩm đã bán', 'Doanh thu'];
            case 'category':
                return ['Danh mục', 'Sản phẩm đã bán', 'Doanh thu'];
            case 'products':
                return ['Sản phẩm', 'Số lượng đã bán', 'Doanh thu sản phẩm'];
            default:
                return ['Trạng thái đơn hàng', 'Số lượng đơn'];
        }
    }

    public function detailRows(): array
    {
        $detailRows = [];
        if ($this->section === 'revenue') {
            foreach ($this->trendRows as $point) {
                $detailRows[] = [
                    $point['table_label'],
                    number_format($point['order_count']),
                    number_format($point['items_sold']),
                    self::money($point['revenue']),
                ];
            }
        } elseif ($this->section === 'category') {
            foreach ($this->categoryRevenue as $category) {
                $detailRows[] = [
                    $category['category_name'],
                    number_format((int) $category['items_sold']),
                    self::money($category['revenue']),
                ];
            }
        } elseif ($this->section === 'products') {
            foreach ($this->topProducts as $product) {
                $detailRows[] = [
                    $product['product_name'],
                    number_format((int) $product['quantity_sold']),
                    self::money($product['product_revenue']),
                ];
            }
        } else {
            foreach ($this->statusCounts as $status => $count) {
                $detailRows[] = [$status, number_format($count)];
            }
        }

        return $detailRows;
    }

    public function exportRows(): array
    {
        $summary = $this->summary;
        $periodLabel = $this->range->label();
        $excelRows = [
            ['BÁO CÁO BÁN HÀNG - ÉCRIN JEWELRY'],
            ['Loại báo cáo', $this->title()],
            ['Khoảng thời gian', $periodLabel],
            ['Danh mục', $this->categoryId > 0 ? (string) ($this->categories[$this->categoryId] ?? 'Tất cả') : 'Tất cả'],
            ['Thời gian xuất', date('d/m/Y H:i')],
            [],
            ['TỔNG QUAN CHỈ SỐ', 'GIÁ TRỊ'],
            ['Doanh thu đã giao', (float) $summary['revenue']],
            ['Tổng đơn hàng', (int) $summary['total_orders']],
            ['Đơn hàng đã giao', (int) $summary['completed_orders']],
            ['Đơn hàng đã hủy', (int) $summary['cancelled_orders']],
            ['Khách hàng mới', (int) $summary['new_customers']],
            ['Sản phẩm đã bán', (int) $summary['items_sold']],
            [],
            ['BẢNG CHI TIẾT'],
            $this->detailHeaders(),
        ];

        if ($this->section === 'revenue') {
            $excelRows[] = [
                'Tổng cộng (' . $periodLabel . ')',
                (int) $summary['total_orders'],
                (int) $summary['items_sold'],
                (float) $summary['revenue'],
            ];
            foreach ($this->trendRows as $point) {
                $excelRows[] = [
                    $point['table_label'],
                    (int) $point['order_count'],
                    (int) $point['items_sold'],
                    (float) $point['revenue'],
                ];
            }
        } elseif ($this->section === 'category') {
            foreach ($this->categoryRevenue as $category) {
                $excelRows[] = [
                    (string) $category['category_name'],
                    (int) $category['items_sold'],
                    (float) $category['revenue'],
                ];
            }
        } elseif ($this->section === 'products') {
            foreach ($this->topProducts as $product) {
                $excelRows[] = [
                    (string) $product['product_name'],
                    (int) $product['quantity_sold'],
                    (float) $product['product_revenue'],
                ];
            }
        } else {
            foreach ($this->statusCounts as $status => $count) {
                $excelRows[] = [$status, (int) $count];
            }
        }

        return $excelRows;
    }

    public function exportFilename(): string
    {
        return 'bao-cao-' . $this->section . '-' . $this->range->startDate() . '-den-' . $this->range->endDate();
    }
}
