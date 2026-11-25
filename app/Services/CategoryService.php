<?php

namespace App\Services;

use App\Core\DatabaseException;
use App\Models\Category;
use InvalidArgumentException;
use RuntimeException;

/**
 * Lớp CategoryService - nghiệp vụ thêm / sửa / xóa danh mục sản phẩm trong trang quản trị.
 * Các phương thức trả về thông báo thành công; lỗi được ném ra dưới dạng ngoại lệ
 * (InvalidArgumentException: dữ liệu không hợp lệ, RuntimeException: lỗi hệ thống).
 */
class CategoryService
{
    private const SLUG_SEARCH = [
        'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
        'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
        'ì','í','ị','ỉ','ĩ',
        'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
        'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
        'ỳ','ý','ỵ','ỷ','ỹ','đ',
        'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ',
        'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
        'Ì','Í','Ị','Ỉ','Ĩ',
        'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
        'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ',
        'Ỳ','Ý','Ỵ','Ỷ','Ỹ','Đ',
    ];

    private const SLUG_REPLACE = [
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d',
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d',
    ];

    private Category $categories;

    public function __construct(?Category $categories = null)
    {
        $this->categories = $categories ?? new Category();
    }

    /** Tạo slug từ tên tiếng Việt (đảm bảo cột slug trong DB luôn hợp lệ). */
    public static function makeSlug(string $str): string
    {
        $str = str_replace(self::SLUG_SEARCH, self::SLUG_REPLACE, $str);
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $str), '-'));

        return $slug !== '' ? $slug : 'cat-' . time();
    }

    /** Danh sách danh mục cho trang quản trị. */
    public function listForAdmin(): array
    {
        return $this->categories->findAllIdNameForAdmin();
    }

    public function add(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Vui lòng nhập tên danh mục.');
        }
        if ($this->categories->nameExistsForAdmin($name)) {
            throw new InvalidArgumentException('Tên danh mục này đã tồn tại!');
        }

        $slug = self::makeSlug($name);
        // Đảm bảo slug duy nhất
        if ($this->categories->slugExistsForAdmin($slug)) {
            $slug .= '-' . time();
        }

        try {
            $this->categories->createWithSlug($name, $slug);
        } catch (DatabaseException $e) {
            throw new RuntimeException('Lỗi hệ thống khi thêm danh mục.', 0, $e);
        }

        return "Đã thêm danh mục '{$name}' thành công!";
    }

    public function edit(int $id, string $name): string
    {
        $name = trim($name);
        if ($id <= 0 || $name === '') {
            throw new InvalidArgumentException('Dữ liệu sửa không hợp lệ.');
        }
        if ($this->categories->nameExistsForAdmin($name, $id)) {
            throw new InvalidArgumentException('Tên danh mục này đã được sử dụng!');
        }

        try {
            $this->categories->updateNameAndSlug($id, $name, self::makeSlug($name));
        } catch (DatabaseException $e) {
            throw new RuntimeException('Lỗi khi cập nhật danh mục.', 0, $e);
        }

        return 'Đã cập nhật danh mục thành công!';
    }

    /** Xóa danh mục; trả về null khi id không hợp lệ (không làm gì). */
    public function delete(int $id): ?string
    {
        if ($id <= 0) {
            return null;
        }

        $total = $this->categories->countProductsInCategory($id);
        if ($total > 0) {
            throw new InvalidArgumentException("Không thể xóa: Danh mục này đang chứa {$total} sản phẩm!");
        }

        try {
            $this->categories->delete($id);
        } catch (DatabaseException $e) {
            throw new RuntimeException('Không thể xóa danh mục này.', 0, $e);
        }

        return 'Đã xóa danh mục thành công!';
    }
}
