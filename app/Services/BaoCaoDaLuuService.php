<?php

namespace App\Services;

use App\Core\Database;
use App\Models\BaoCaoDaLuu;
use App\Services\Reports\BaoCaoKhachHang;
use App\Services\Reports\KhoangThoiGianBaoCao;
use App\Services\Reports\BaoCaoDoanhThu;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lớp BaoCaoDaLuuService - quản lý "báo cáo đã lưu" của trang Báo cáo và thống kê:
 * khởi tạo bảng, thêm/sửa tên, lưu cấu hình bộ lọc, xóa.
 * Lỗi nghiệp vụ được ném ra dưới dạng exception với thông báo tiếng Việt hiển thị cho người dùng.
 */
class BaoCaoDaLuuService
{
    public const TYPES = ['sales' => 'Bán hàng', 'customer' => 'Khách hàng'];

    private BaoCaoDaLuu $reports;

    public function __construct(?Database $db = null, ?BaoCaoDaLuu $reports = null)
    {
        $this->reports = $reports ?? new BaoCaoDaLuu($db ?? Database::getInstance());
    }

    /**
     * Đảm bảo bảng lưu báo cáo sẵn sàng.
     *
     * @throws RuntimeException với thông báo hiển thị nếu không thể khởi tạo
     */
    public function ensureStorage(): void
    {
        try {
            $this->reports->createTableIfMissing();
        } catch (Throwable $e) {
            error_log('Admin saved reports table setup failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể khởi tạo danh sách báo cáo đã lưu.', 0, $e);
        }

        try {
            $this->reports->ensureConfigColumn();
        } catch (Throwable $e) {
            error_log('Admin saved report config migration failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể chuẩn bị lưu cấu hình báo cáo.', 0, $e);
        }
    }

    /**
     * Danh sách báo cáo đã lưu (id, title, report_type).
     *
     * @throws RuntimeException
     */
    public function listReports(): array
    {
        try {
            return $this->reports->findAllForIndex();
        } catch (Throwable $e) {
            error_log('Admin saved reports query failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể tải danh sách báo cáo đã lưu.', 0, $e);
        }
    }

    /** Báo cáo đã lưu kèm cấu hình, hoặc null nếu không tìm thấy / lỗi. */
    public function find(int $id): ?array
    {
        try {
            return $this->reports->findWithConfig($id);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Lưu cấu hình bộ lọc (tạo mới nếu chưa có report_id).
     * Trả về mảng cấu hình kèm report_id để chuyển hướng về báo cáo.
     *
     * @param mixed $rawReportId
     * @param mixed $titleInput
     * @param mixed $typeInput
     * @param mixed $configInput
     * @throws InvalidArgumentException|RuntimeException
     */
    public function saveState($rawReportId, $titleInput, $typeInput, $configInput, string $createdBy): array
    {
        $rawReportId = $rawReportId ?? '';
        $reportId = $rawReportId === '' ? null : filter_var($rawReportId, FILTER_VALIDATE_INT);
        $title = is_string($titleInput) ? trim($titleInput) : '';
        $reportType = is_string($typeInput) ? $typeInput : '';
        $config = is_string($configInput) && strlen($configInput) <= 4000 ? json_decode($configInput, true) : null;

        if (!$this->isValidConfig($config, $reportType) || !preg_match('/^.{1,250}$/us', $title) || !isset(self::TYPES[$reportType])
            || ($reportId !== null && ($reportId === false || $reportId < 1))) {
            throw new InvalidArgumentException('Không thể lưu cấu hình báo cáo. Vui lòng tải lại trang và thử lại.');
        }

        $configJson = (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        try {
            if ($reportId) {
                $this->reports->updateConfig($reportId, $reportType, $configJson);
            } else {
                $reportId = $this->reports->createReportWithConfig($title, $reportType, $createdBy, $configJson);
            }
        } catch (Throwable $e) {
            error_log('Admin saved report config update failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể lưu báo cáo. Vui lòng thử lại.', 0, $e);
        }

        $config['report_id'] = $reportId;

        return $config;
    }

    /**
     * Thêm mới (create) hoặc đổi tên/loại (update) báo cáo. Trả về thông báo thành công.
     *
     * @param mixed $titleInput
     * @param mixed $typeInput
     * @param mixed $reportIdInput
     * @throws InvalidArgumentException|RuntimeException
     */
    public function saveTitle(bool $isCreate, $titleInput, $typeInput, $reportIdInput, string $createdBy): string
    {
        $title = is_string($titleInput) ? trim($titleInput) : '';
        $reportType = is_string($typeInput) ? $typeInput : '';
        $reportId = filter_var($reportIdInput, FILTER_VALIDATE_INT);

        if (!preg_match('/^.{1,250}$/us', $title) || !isset(self::TYPES[$reportType])) {
            throw new InvalidArgumentException('Vui lòng nhập tên báo cáo hợp lệ và chọn loại báo cáo.');
        }
        if (!$isCreate && ($reportId === false || $reportId < 1)) {
            throw new InvalidArgumentException('Báo cáo cần sửa không hợp lệ.');
        }

        try {
            if ($isCreate) {
                $this->reports->createReport($title, $reportType, $createdBy);
            } else {
                $this->reports->updateTitleAndType((int) $reportId, $title, $reportType);
            }
        } catch (Throwable $e) {
            error_log('Admin saved report save failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể lưu báo cáo.', 0, $e);
        }

        return $isCreate ? 'Đã thêm báo cáo.' : 'Đã cập nhật báo cáo.';
    }

    /**
     * Xóa báo cáo đã lưu. Trả về thông báo thành công.
     *
     * @param mixed $reportIdInput
     * @throws InvalidArgumentException|RuntimeException
     */
    public function delete($reportIdInput): string
    {
        $reportId = filter_var($reportIdInput, FILTER_VALIDATE_INT);
        if ($reportId === false || $reportId < 1) {
            throw new InvalidArgumentException('Báo cáo cần xóa không hợp lệ.');
        }

        try {
            $affected = $this->reports->delete($reportId);
        } catch (Throwable $e) {
            error_log('Admin saved report delete failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể xóa báo cáo.', 0, $e);
        }
        if ($affected !== 1) {
            throw new RuntimeException('Không tìm thấy báo cáo cần xóa.');
        }

        return 'Đã xóa báo cáo.';
    }

    /** Kiểm tra cấu hình bộ lọc gửi lên có hợp lệ với loại báo cáo không. */
    private function isValidConfig($config, string $reportType): bool
    {
        return is_array($config)
            && in_array($config['view'] ?? '', ['sales', 'customer'], true)
            && in_array($config['period'] ?? '', KhoangThoiGianBaoCao::PERIODS, true)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($config['from'] ?? ''))
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($config['to'] ?? ''))
            && (($reportType !== 'sales') || (filter_var($config['category_id'] ?? 0, FILTER_VALIDATE_INT) !== false && (int) ($config['category_id'] ?? 0) >= 0))
            && ($reportType === 'sales'
                ? (($config['view'] ?? '') === 'sales' && in_array($config['section'] ?? '', array_keys(BaoCaoDoanhThu::SECTIONS), true))
                : (($config['view'] ?? '') === 'customer' && in_array($config['customer_report'] ?? '', array_keys(BaoCaoKhachHang::TYPES), true)));
    }
}
