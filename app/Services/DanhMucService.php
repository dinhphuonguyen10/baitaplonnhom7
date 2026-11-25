<?php

namespace App\Services;

use App\Models\DanhMuc;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Lớp DanhMucService - nghiệp vụ quản trị danh mục: thêm / sửa (mã, mô tả, ảnh, trạng thái), xóa mềm, khôi phục.
 * Các phương thức trả về thông báo thành công; lỗi được ném ra dưới dạng ngoại lệ
 * (DomainException / InvalidArgumentException: lỗi nghiệp vụ hiển thị cho người dùng; lỗi khác: lỗi hệ thống).
 */
class DanhMucService
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

    public const STATUSES = ['active', 'inactive'];

    /** Ảnh mặc định theo slug khi danh mục chưa có ảnh riêng (dùng chung cho trang chủ khách và trang quản trị). */
    public const DEFAULT_IMAGES = [
        'bong-tai' => 'assets/bôngtai/bong1.jpg',
        'day-chuyen' => 'assets/dây chuyền/dâychuyen/day1.jpg',
        'nhan' => 'assets/nhẫn/nhan1.png',
        'vong-tay' => 'assets/vòng tay/vong1.png',
        'set-qua-tang' => 'assets/quà/set1.jpg',
    ];

    private const UPLOAD_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;

    /** Thông báo chung khi gặp lỗi hệ thống (lỗi chi tiết chỉ ghi vào error_log). */
    public const MSG_SYSTEM_ERROR = 'Không thể hoàn tất thao tác danh mục. Vui lòng thử lại.';

    private DanhMuc $categories;

    public function __construct(?DanhMuc $categories = null)
    {
        $this->categories = $categories ?? new DanhMuc();
    }

    /** Tạo slug từ tên tiếng Việt (đảm bảo cột slug trong DB luôn hợp lệ). */
    public static function makeSlug(string $str): string
    {
        $str = str_replace(self::SLUG_SEARCH, self::SLUG_REPLACE, $str);
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $str), '-'));

        return $slug !== '' ? $slug : 'cat-' . time();
    }

    /**
     * Danh sách danh mục (kể cả đã xóa mềm) cho trang quản trị, kèm các trường hiển thị:
     * image_url (ảnh hoặc logo mặc định), is_deleted_flag, is_active_flag.
     */
    public function listForAdmin(string $appBaseUrl): array
    {
        $rows = [];
        foreach ($this->categories->findAllWithProductCountForAdmin() as $cat) {
            $image = trim((string) ($cat['image'] ?? ''));
            // Cùng quy tắc với trang chủ khách: ảnh riêng > ảnh mặc định theo slug > logo.
            $effective = $image !== '' ? $image : (self::DEFAULT_IMAGES[(string) ($cat['slug'] ?? '')] ?? 'assets/banner/logo.png');
            $cat['image_url'] = preg_match('~^https?://~i', $effective)
                ? $effective
                : $appBaseUrl . '/' . ltrim($effective, '/');
            $cat['image_name'] = basename((string) (parse_url($effective, PHP_URL_PATH) ?: $effective));
            $cat['is_deleted_flag'] = (int) $cat['is_deleted'] === 1;
            $cat['is_active_flag'] = $cat['status'] === 'active' && !$cat['is_deleted_flag'];
            $rows[] = $cat;
        }

        return $rows;
    }

    /**
     * Thực hiện một thao tác form (add | edit | delete | restore) và trả về thông báo thành công.
     *
     * @param array $input dữ liệu POST
     * @throws DomainException|InvalidArgumentException lỗi nghiệp vụ (thông báo hiển thị cho người dùng)
     * @throws Throwable lỗi hệ thống (controller hiển thị MSG_SYSTEM_ERROR)
     */
    public function handleAction(string $action, array $input, string $adminUsername, ?array $imageFile = null): string
    {
        switch ($action) {
            case 'add':
                return $this->add($input, $imageFile);
            case 'edit':
                return $this->edit($input, $imageFile);
            case 'delete':
                return $this->softDelete($input, $adminUsername);
            case 'restore':
                return $this->restore($input);
            default:
                throw new InvalidArgumentException('Thao tác danh mục không hợp lệ.');
        }
    }

    private function add(array $input, ?array $imageFile): string
    {
        $category = $this->categoryInput($input, $imageFile);

        return $this->inTransaction(function () use ($category): string {
            $this->assertUniqueName($category['name'], 0);
            $slug = $this->uniqueSlug($category['name'], 0);
            $code = 'DM-' . strtoupper(bin2hex(random_bytes(4)));
            $this->categories->createWithDetails(
                $code,
                $category['name'],
                $slug,
                $category['description'],
                $category['image'],
                $category['status']
            );

            return 'Đã thêm danh mục thành công.';
        });
    }

    private function edit(array $input, ?array $imageFile): string
    {
        $id = $this->categoryId($input);
        $category = $this->categoryInput($input, $imageFile);

        return $this->inTransaction(function () use ($id, $category): string {
            if ($this->categories->lockByDeletedFlag($id, 0) === null) {
                throw new DomainException('Danh mục không tồn tại hoặc đã bị xóa mềm.');
            }
            $this->assertUniqueName($category['name'], $id);
            $slug = $this->uniqueSlug($category['name'], $id);
            $this->categories->updateDetails(
                $id,
                $category['name'],
                $slug,
                $category['description'],
                $category['image'],
                $category['status']
            );

            return 'Đã cập nhật danh mục.';
        });
    }

    private function softDelete(array $input, string $adminUsername): string
    {
        $id = $this->categoryId($input);

        return $this->inTransaction(function () use ($id, $adminUsername): string {
            if ($this->categories->lockByDeletedFlag($id, 0) === null) {
                throw new DomainException('Danh mục không tồn tại hoặc đã bị xóa mềm.');
            }
            // Đếm cả sản phẩm đã xóa mềm: phải chuyển hết sang danh mục khác trước khi xóa.
            $productCount = $this->categories->countProductsInCategory($id);
            if ($productCount > 0) {
                throw new DomainException(
                    "Danh mục đang có {$productCount} sản phẩm. Hãy chuyển sản phẩm sang danh mục khác trong Quản lý sản phẩm trước khi xóa."
                );
            }
            $this->categories->softDelete($id, $adminUsername !== '' ? $adminUsername : 'admin');

            return 'Đã xóa mềm danh mục.';
        });
    }

    private function restore(array $input): string
    {
        $id = $this->categoryId($input);

        return $this->inTransaction(function () use ($id): string {
            $deletedCategory = $this->categories->lockByDeletedFlag($id, 1);
            if ($deletedCategory === null) {
                throw new DomainException('Danh mục không tồn tại trong thùng rác.');
            }
            if (!in_array($deletedCategory['status'], self::STATUSES, true)
                || trim((string) $deletedCategory['code']) === '') {
                throw new DomainException('Danh mục có trạng thái hoặc mã không hợp lệ nên chưa thể khôi phục.');
            }
            $this->assertUniqueName((string) $deletedCategory['name'], $id);
            $this->categories->restoreDeleted($id);

            return 'Đã khôi phục danh mục. Trạng thái hoạt động hiện tại được giữ nguyên.';
        });
    }

    /** Chạy $callback trong transaction; rollback và ném lại ngoại lệ nếu có lỗi. */
    private function inTransaction(callable $callback): string
    {
        return $this->categories->getDb()->transaction(static fn() => $callback());
    }

    private function categoryId(array $input): int
    {
        $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new InvalidArgumentException('Mã danh mục không hợp lệ.');
        }

        return (int) $id;
    }

    /** Chuẩn hoá + kiểm tra dữ liệu form danh mục: name, description, image, status. */
    private function categoryInput(array $input, ?array $imageFile = null): array
    {
        $status = $input['status'] ?? 'active';
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Trạng thái danh mục không hợp lệ.');
        }
        $description = $input['description'] ?? '';
        if (!is_string($description)) {
            throw new InvalidArgumentException('Mô tả danh mục không hợp lệ.');
        }

        return [
            'name' => $this->normalizeName($input['name'] ?? null),
            'description' => trim($description),
            'image' => $this->saveUploadedImage($imageFile) ?? $this->normalizeImage($input['image'] ?? ''),
            'status' => $status,
        ];
    }

    private function normalizeName($value): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Tên danh mục không hợp lệ.');
        }
        $name = preg_replace('/\s+/u', ' ', trim($value));
        if (!is_string($name) || $name === '') {
            throw new InvalidArgumentException('Vui lòng nhập tên danh mục.');
        }
        if (mb_strlen($name, 'UTF-8') > 150) {
            throw new InvalidArgumentException('Tên danh mục không được vượt quá 150 ký tự.');
        }

        return $name;
    }

    /** Lưu ảnh tải lên vào assets/uploads; trả về đường dẫn tương đối, hoặc null nếu không chọn tệp nào. */
    private function saveUploadedImage(?array $file): ?string
    {
        $error = is_array($file) ? (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Không thể nhận tệp ảnh tải lên.');
        }
        if ((int) $file['size'] <= 0 || (int) $file['size'] > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('Ảnh danh mục tải lên phải có dung lượng tối đa 5 MB.');
        }
        $uploader = new TaiAnhLenService();
        $mime = $uploader->detectImageMime($tmp);
        if (!isset(self::UPLOAD_MIME[$mime])) {
            throw new InvalidArgumentException('Tệp tải lên không phải ảnh JPEG, PNG, WebP hoặc GIF hợp lệ.');
        }
        $directory = APP_ROOT . '/assets/uploads/';
        $uploader->ensureDirectory($directory);
        $fileName = $uploader->reserveUniqueFile($directory, 'cat_', self::UPLOAD_MIME[$mime]);
        if ($fileName === null) {
            throw new RuntimeException('Không thể cấp tên tệp cho ảnh danh mục.');
        }
        if (!$uploader->move($file, $directory . $fileName)) {
            @unlink($directory . $fileName);
            throw new RuntimeException('Không thể lưu ảnh danh mục đã tải lên.');
        }

        return 'assets/uploads/' . $fileName;
    }

    /** Ảnh danh mục: rỗng => null; URL http/https; hoặc tệp có thật trong thư mục assets. */
    private function normalizeImage($value): ?string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException('Đường dẫn ảnh danh mục không hợp lệ.');
        }
        $image = trim($value);
        if ($image === '') {
            return null;
        }
        if (strlen($image) > 500) {
            throw new InvalidArgumentException('Đường dẫn ảnh danh mục quá dài.');
        }
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new InvalidArgumentException('Ảnh danh mục chỉ nhận URL HTTP hoặc HTTPS.');
            }

            return $image;
        }
        if (strpos($image, 'assets/') !== 0 || strpos($image, '..') !== false || strpos($image, '\\') !== false) {
            throw new InvalidArgumentException('Ảnh nội bộ phải là đường dẫn hợp lệ bên trong thư mục assets.');
        }
        $assetsRoot = realpath(APP_ROOT . '/assets');
        $imagePath = realpath(APP_ROOT . '/' . $image);
        if ($assetsRoot === false || $imagePath === false
            || !is_file($imagePath)
            || strpos($imagePath, $assetsRoot . DIRECTORY_SEPARATOR) !== 0) {
            throw new InvalidArgumentException('Không tìm thấy tệp ảnh danh mục trong assets.');
        }

        return $image;
    }

    private function assertUniqueName(string $name, int $categoryId): void
    {
        if ($this->categories->nameExistsForAdmin($name, $categoryId)) {
            throw new DomainException('Tên danh mục này đã được sử dụng.');
        }
    }

    /** Slug duy nhất từ tên (thêm hậu tố -2, -3... khi trùng), tối đa 80 ký tự. */
    private function uniqueSlug(string $name, int $categoryId): string
    {
        $base = substr(self::makeSlug($name), 0, 80);
        for ($suffix = 1; $suffix < 1000; $suffix++) {
            $candidate = $suffix === 1
                ? $base
                : substr($base, 0, 79 - strlen((string) $suffix)) . '-' . $suffix;
            if (!$this->categories->slugUsedByOther($candidate, $categoryId)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Không thể tạo mã đường dẫn duy nhất cho danh mục.');
    }
}
