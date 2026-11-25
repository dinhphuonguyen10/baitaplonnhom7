<?php

namespace App\Helpers;

/**
 * Lớp DinhDangSanPhamQuanTri - định dạng hiển thị sản phẩm trong trang quản trị
 * (nhãn tồn kho, nhãn trạng thái, URL ảnh).
 */
final class DinhDangSanPhamQuanTri
{
    /** @return array{0: string, 1: string} [nhãn tồn kho, class CSS] */
    public static function stock(int $stock): array
    {
        if ($stock > 10) {
            return ['Còn hàng (' . $stock . ')', 'stock-ok'];
        }
        if ($stock > 0) {
            return ['Sắp hết (' . $stock . ')', 'stock-low'];
        }

        return ['Hết hàng (0)', 'stock-out'];
    }

    /** @return array{0: string, 1: string} [nhãn trạng thái, class CSS] */
    public static function status(string $status): array
    {
        if ($status === 'inactive') {
            return ['Ngừng kinh doanh', 'status-inactive'];
        }
        if ($status === 'hidden') {
            return ['Bị ẩn', 'status-hidden'];
        }

        return ['Đang kinh doanh', 'status-active'];
    }

    /** URL ảnh: giữ nguyên nếu là URL tuyệt đối, ngược lại nối với đường dẫn gốc ứng dụng. */
    public static function imageUrl(string $image, string $appBaseUrl): string
    {
        return (str_starts_with($image, 'http://') || str_starts_with($image, 'https://'))
            ? $image
            : $appBaseUrl . '/' . ltrim($image, '/');
    }
}
