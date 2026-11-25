<?php
// scripts/kiem_tra_danh_muc_qua_tang.php - Kiểm tra seed danh mục quà tặng chạy lặp lại được và ảnh sản phẩm đầy đủ (CLI).
require_once __DIR__ . '/../app/bootstrap.php';

use App\Database\KiemTraDuLieuDanhMuc;

try {
    $verifier = new KiemTraDuLieuDanhMuc();
    $verifier->runSeedBlock($verifier->giftSeedBlock(), 2);
    $verifier->verifyCategoryImages(static function (string $slug, int $count): void {
        echo $slug . ': ' . $count . ' / ' . $count . ' image products verified.' . PHP_EOL;
    });
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

echo "Catalog seed is repeatable; all category images have products.\n";
