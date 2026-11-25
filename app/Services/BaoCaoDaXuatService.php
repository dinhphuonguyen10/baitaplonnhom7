<?php

namespace App\Services;

use App\Core\Database;
use App\Models\BaoCaoDaXuat;
use App\Services\Reports\BaoCaoDoanhThu;
use App\Services\Reports\BaoCaoKhachHang;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lớp BaoCaoDaXuatService - danh sách "Báo cáo đã xuất": ghi nhận mỗi lần xuất,
 * sửa tên / khoảng ngày và dựng đường dẫn để xuất lại.
 */
class BaoCaoDaXuatService
{
    private BaoCaoDaXuat $entries;

    public function __construct(?Database $db = null, ?BaoCaoDaXuat $entries = null)
    {
        $this->entries = $entries ?? new BaoCaoDaXuat($db ?? Database::getInstance());
    }

    /** @throws RuntimeException */
    public function ensureStorage(): void
    {
        try {
            $this->entries->createTableIfMissing();
            $this->entries->ensureSnapshotColumn();
        } catch (Throwable $e) {
            error_log('Admin exported reports table setup failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể khởi tạo danh sách báo cáo đã xuất.', 0, $e);
        }
    }

    /**
     * Danh sách báo cáo đã xuất kèm: config (mảng), type_label, section_label, from, to.
     *
     * @return array<int, array<string, mixed>>
     * @throws RuntimeException
     */
    public function listEntries(): array
    {
        try {
            $rows = $this->entries->findAllRecent();
        } catch (Throwable $e) {
            error_log('Admin exported reports query failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể tải danh sách báo cáo đã xuất.', 0, $e);
        }

        foreach ($rows as &$row) {
            $config = json_decode((string) $row['config_json'], true);
            $config = is_array($config) ? $config : [];
            $row['config'] = $config;
            $row['type_label'] = $row['report_type'] === 'customer' ? 'Khách hàng' : 'Bán hàng';
            $row['section_label'] = $row['report_type'] === 'customer'
                ? (BaoCaoKhachHang::TYPES[$config['customer_report'] ?? ''] ?? '')
                : (BaoCaoDoanhThu::SECTIONS[$config['section'] ?? ''] ?? '');
            $row['from'] = (string) ($config['from'] ?? '');
            $row['to'] = (string) ($config['to'] ?? '');
        }
        unset($row);

        return $rows;
    }

    /**
     * Ghi nhận một lần xuất cùng dữ liệu báo cáo tại thời điểm xuất (snapshot) để mở lại đúng nội dung đó.
     * Lỗi ghi nhận không được làm hỏng việc xuất tệp nên chỉ ghi log.
     */
    public function recordExport(string $name, string $reportType, array $config, string $format, string $exportedBy, array $snapshot): void
    {
        try {
            $this->ensureStorage();
            $this->entries->createEntry(
                $name,
                $reportType,
                (string) json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $format,
                $exportedBy,
                (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)
            );
        } catch (Throwable $e) {
            error_log('Admin exported report log failed: ' . $e->getMessage());
        }
    }

    /**
     * Một báo cáo đã xuất kèm dữ liệu đã lưu: null nếu không tồn tại; 'snapshot' = null nếu
     * báo cáo được xuất trước khi hệ thống lưu dữ liệu.
     */
    public function findWithSnapshot(int $id): ?array
    {
        try {
            $row = $this->entries->find($id);
        } catch (Throwable $e) {
            error_log('Admin exported report find failed: ' . $e->getMessage());

            return null;
        }
        if (!$row) {
            return null;
        }
        $snapshot = json_decode((string) ($row['snapshot_json'] ?? ''), true);
        $row['snapshot'] = is_array($snapshot) ? $snapshot : null;
        $row['type_label'] = $row['report_type'] === 'customer' ? 'Khách hàng' : 'Bán hàng';

        return $row;
    }

    /**
     * Xóa một báo cáo khỏi danh sách đã xuất. Trả về thông báo thành công.
     *
     * @param mixed $idInput
     * @throws InvalidArgumentException|RuntimeException
     */
    public function delete($idInput): string
    {
        $id = filter_var($idInput, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('Báo cáo cần xóa không hợp lệ.');
        }

        try {
            $this->entries->deleteEntry((int) $id);
        } catch (Throwable $e) {
            error_log('Admin exported report delete failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể xóa báo cáo đã xuất.', 0, $e);
        }

        return 'Đã xóa báo cáo khỏi danh sách đã xuất.';
    }

    /**
     * Bảng dữ liệu (mảng các dòng) để xuất lại tệp từ bản đã lưu: thông tin chung, chỉ số tổng quan
     * và bảng chi tiết đúng như tại thời điểm xuất lần đầu.
     */
    public static function exportRowsFromSnapshot(array $entry): array
    {
        $snapshot = $entry['snapshot'] ?? [];
        $fmt = static function (string $date): string {
            $time = strtotime($date);

            return $time ? date('d/m/Y', $time) : $date;
        };
        $createdAt = strtotime((string) ($entry['created_at'] ?? ''));

        $rows = [
            [$entry['report_type'] === 'customer' ? 'BÁO CÁO KHÁCH HÀNG - ÉCRIN JEWELRY' : 'BÁO CÁO BÁN HÀNG - ÉCRIN JEWELRY'],
            ['Tên báo cáo', (string) $entry['name']],
            ['Báo cáo gốc', (string) ($snapshot['title'] ?? '')],
            ['Khoảng thời gian', $fmt((string) ($snapshot['from'] ?? '')) . ' - ' . $fmt((string) ($snapshot['to'] ?? ''))],
        ];
        if (isset($snapshot['category'])) {
            $rows[] = ['Danh mục', (string) $snapshot['category']];
        }
        $rows[] = ['Thời gian xuất', $createdAt ? date('d/m/Y H:i', $createdAt) : ''];
        $rows[] = [];
        if (!empty($snapshot['summary'])) {
            $rows[] = ['TỔNG QUAN CHỈ SỐ', 'GIÁ TRỊ'];
            foreach ($snapshot['summary'] as $pair) {
                $rows[] = [(string) ($pair[0] ?? ''), (string) ($pair[1] ?? '')];
            }
            $rows[] = [];
        }
        $rows[] = ['BẢNG CHI TIẾT'];
        $rows[] = $snapshot['headers'] ?? [];
        foreach ($snapshot['rows'] ?? [] as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /** Chuỗi query để mở bản báo cáo đã lưu tại thời điểm xuất. */
    public static function detailQuery(array $entry): string
    {
        return http_build_query(['view' => 'exported', 'id' => (int) $entry['id']]);
    }
}
