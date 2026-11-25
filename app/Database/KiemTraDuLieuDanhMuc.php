<?php

namespace App\Database;

use App\Core\Database;
use RuntimeException;

/**
 * Lớp KiemTraDuLieuDanhMuc - kiểm tra khối seed danh mục "Set quà tặng" trong database/baitaplon.sql
 * có chạy lặp lại được không, và mọi ảnh sản phẩm trong assets/ đều có sản phẩm tương ứng.
 * Dùng bởi scripts/kiem_tra_danh_muc_qua_tang.php. Lỗi được ném dưới dạng RuntimeException.
 */
class KiemTraDuLieuDanhMuc
{
    private Database $db;
    private string $root;

    public function __construct(?Database $db = null, ?string $root = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->root = $root ?? APP_ROOT;
    }

    /** Đọc khối SQL seed danh mục quà tặng từ file dump. */
    public function giftSeedBlock(): string
    {
        $source = (string) file_get_contents($this->root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baitaplon.sql');
        $source = str_replace(["\r\n", "\r"], "\n", $source);
        $start = strpos($source, "INSERT INTO categories (name, slug)\nVALUES ('Set quà tặng', 'set-qua-tang')");
        $end = $start === false ? false : strpos($source, '-- SQL VIEWS', $start);

        if ($start === false || $end === false) {
            throw new RuntimeException('Gift category seed block not found.');
        }

        return substr($source, $start, $end - $start);
    }

    /** Chạy khối seed $times lần (kiểm tra tính lặp lại). */
    public function runSeedBlock(string $sql, int $times = 2): void
    {
        $connection = $this->db->connection();
        for ($run = 1; $run <= $times; $run++) {
            if (!$connection->multi_query($sql)) {
                throw new RuntimeException($connection->error);
            }
            do {
                if ($result = $connection->store_result()) {
                    $result->free();
                }
            } while ($connection->more_results() && $connection->next_result());
            if ($connection->errno) {
                throw new RuntimeException($connection->error);
            }
        }
    }

    /**
     * Kiểm tra mỗi ảnh sản phẩm của từng danh mục đều có sản phẩm. Gọi $report(slug, số ảnh) cho mỗi danh mục đạt.
     */
    public function verifyCategoryImages(callable $report): void
    {
        $imagePatterns = [
            'bong-tai' => 'assets' . DIRECTORY_SEPARATOR . 'bôngtai' . DIRECTORY_SEPARATOR . 'bong*.jpg',
            'day-chuyen' => 'assets' . DIRECTORY_SEPARATOR . 'dây chuyền' . DIRECTORY_SEPARATOR . 'dâychuyen' . DIRECTORY_SEPARATOR . 'day*.jpg',
            'nhan' => 'assets' . DIRECTORY_SEPARATOR . 'nhẫn' . DIRECTORY_SEPARATOR . 'nhan*.png',
            'vong-tay' => 'assets' . DIRECTORY_SEPARATOR . 'vòng tay' . DIRECTORY_SEPARATOR . 'vong*.png',
            'set-qua-tang' => 'assets' . DIRECTORY_SEPARATOR . 'quà' . DIRECTORY_SEPARATOR . 'set*.jpg',
        ];

        foreach ($imagePatterns as $slug => $pattern) {
            $files = glob($this->root . DIRECTORY_SEPARATOR . $pattern);
            if ($files === false || $files === []) {
                throw new RuntimeException('No product images found for category ' . $slug . '.');
            }

            $expectedImages = [];
            foreach ($files as $file) {
                $expectedImages[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($this->root) + 1));
            }

            $productImages = array_fill_keys($this->db->fetchColumn(
                'SELECT p.image FROM products p JOIN categories c ON c.id = p.category_id WHERE c.slug = ?',
                [$slug],
                's'
            ), true);

            foreach ($expectedImages as $image) {
                if (!isset($productImages[$image])) {
                    throw new RuntimeException('Missing product for image ' . $image . ' in category ' . $slug . '.');
                }
            }

            $report($slug, count($expectedImages));
        }
    }
}
