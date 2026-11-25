<?php

namespace App\Services\Reports;

use App\Core\Database;
use Throwable;

/**
 * Lớp trừu tượng AbstractReport - lớp cha của các báo cáo thống kê (bán hàng, khách hàng).
 *  - Giữ khoảng thời gian (ReportPeriod), tiêu đề tùy chỉnh và thông báo lỗi
 *  - Lớp con cài đặt load(), tiêu đề, bảng chi tiết và dữ liệu xuất Excel/CSV
 *  - Trang hiển thị / xuất file chỉ làm việc qua giao diện chung này (đa hình)
 */
abstract class AbstractReport
{
    protected Database $db;

    protected ReportPeriod $range;

    /** Tiêu đề do người dùng đặt (báo cáo đã lưu hoặc tham số title). */
    protected ?string $customTitle;

    /** Thông báo lỗi hiển thị (rỗng nếu không có lỗi). */
    protected string $error;

    public function __construct(ReportPeriod $range, ?string $customTitle = null, string $initialError = '', ?Database $db = null)
    {
        $this->range = $range;
        $this->customTitle = $customTitle;
        $this->error = $initialError;
        $this->db = $db ?? Database::getInstance();
    }

    /** Chạy các truy vấn và chuẩn bị dữ liệu báo cáo. */
    abstract public function load(): void;

    /** Tiêu đề báo cáo. */
    abstract public function title(): string;

    /** Tiêu đề các cột của bảng chi tiết. */
    abstract public function detailHeaders(): array;

    /** Các dòng của bảng chi tiết (đã định dạng để hiển thị). */
    abstract public function detailRows(): array;

    /** Toàn bộ các dòng của tệp xuất (Excel/CSV), giá trị số giữ nguyên kiểu số. */
    abstract public function exportRows(): array;

    /** Tên tệp xuất (không gồm phần mở rộng). */
    abstract public function exportFilename(): string;

    public function range(): ReportPeriod
    {
        return $this->range;
    }

    public function error(): string
    {
        return $this->error;
    }

    public function hasError(): bool
    {
        return $this->error !== '';
    }

    /**
     * Chạy truy vấn báo cáo; khi lỗi thì ghi log, đặt thông báo lỗi và trả về null.
     *
     * @param bool $overwriteError true: ghi đè lỗi trước đó; false: chỉ đặt khi chưa có lỗi
     */
    protected function runQuery(string $sql, array $params, string $errorMessage, string $logLabel, bool $overwriteError = false): ?array
    {
        try {
            return $this->db->fetchAll($sql, $params);
        } catch (Throwable $e) {
            error_log($logLabel . ': ' . $e->getMessage());
            if ($overwriteError || $this->error === '') {
                $this->error = $errorMessage;
            }

            return null;
        }
    }

    /** Định dạng tiền: 1.250.000 đ */
    protected static function money($amount): string
    {
        return number_format((float) $amount, 0, ',', '.') . ' đ';
    }

    /** Định dạng ngày giờ d/m/Y H:i từ chuỗi thời gian CSDL. */
    protected static function dateTime($value): string
    {
        return date('d/m/Y H:i', strtotime($value));
    }
}
