<?php

namespace App\Services;

use RuntimeException;

/**
 * Lớp AnhBiaService - quản lý 4 banner trang chủ và logo website.
 * Dữ liệu lưu dạng JSON:
 *  - storage/banner/banners.json : mảng 4 phần tử [{src, alt}]
 *  - storage/banner/logo.json    : {"src": "..."}
 * Ảnh tải lên lưu trong storage/banner/uploads (banner) và storage/banner (logo).
 */
class AnhBiaService
{
    public const DEFAULT_LOGO_SRC = 'assets/banner/logo.png';
    public const SLOT_COUNT = 4;

    private const BANNER_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const LOGO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    private string $rootDir;
    private string $bannerDirectory;
    private string $uploadDirectory;
    private string $configFile;
    private string $logoConfigFile;
    private TaiAnhLenService $uploader;

    public function __construct(?TaiAnhLenService $uploader = null, ?string $rootDir = null)
    {
        $this->uploader = $uploader ?? new TaiAnhLenService();
        $this->rootDir = $rootDir ?? APP_ROOT;
        $this->bannerDirectory = $this->rootDir . '/storage/banner';
        $this->uploadDirectory = $this->bannerDirectory . '/uploads';
        $this->configFile = $this->bannerDirectory . '/banners.json';
        $this->logoConfigFile = $this->bannerDirectory . '/logo.json';
    }

    /** Danh sách banner mặc định. */
    public function defaultBanners(): array
    {
        return [
            ['src' => 'assets/banner/baner1.png', 'alt' => 'Bộ sưu tập trang sức mới Écrin Jewelry'],
            ['src' => 'assets/banner/baner2.png', 'alt' => 'Trang sức Écrin trên nền lụa xanh thanh lịch'],
            ['src' => 'assets/banner/baner3.png', 'alt' => 'Bộ trang sức hoa Écrin Jewelry'],
            ['src' => 'assets/banner/baner4.png', 'alt' => 'Nhẫn và bông tai Écrin Jewelry'],
        ];
    }

    /** Tạo các thư mục lưu banner nếu chưa có. */
    public function ensureDirectories(): void
    {
        $this->uploader->ensureDirectory($this->bannerDirectory);
        $this->uploader->ensureDirectory($this->uploadDirectory);
    }

    /** Đọc cấu hình banner (đúng 4 phần tử), nếu không hợp lệ trả về mặc định. */
    public function loadBanners(): array
    {
        if (is_file($this->configFile)) {
            $saved = json_decode((string) file_get_contents($this->configFile), true);
            if (is_array($saved) && count($saved) === self::SLOT_COUNT) {
                return $saved;
            }
        }

        return $this->defaultBanners();
    }

    /** Đường dẫn tương đối của logo hiện tại (VD: storage/banner/logo_xxx.png). */
    public function currentLogoSrc(): string
    {
        if (is_file($this->logoConfigFile)) {
            $data = json_decode((string) file_get_contents($this->logoConfigFile), true);
            if (is_array($data) && !empty($data['src'])) {
                return (string) $data['src'];
            }
        }

        return self::DEFAULT_LOGO_SRC;
    }

    /** URL đầy đủ của logo hiện tại. */
    public function logoUrl(string $appBaseUrl): string
    {
        return $appBaseUrl . '/' . ltrim($this->currentLogoSrc(), '/');
    }

    /**
     * Thay logo bằng ảnh tải lên. Trả về đường dẫn logo mới.
     *
     * @throws RuntimeException với thông báo tiếng Việt khi thất bại
     */
    public function uploadLogo(?array $upload): string
    {
        if (!$this->uploader->isUploaded($upload)) {
            throw new RuntimeException('Vui lòng chọn file ảnh logo.');
        }

        $extension = $this->uploader->extensionByMime($upload, self::LOGO_TYPES);
        if ($extension === null) {
            throw new RuntimeException('Chỉ chấp nhận ảnh JPG, PNG, WebP hoặc SVG.');
        }

        $currentLogoSrc = $this->currentLogoSrc();
        $logoFileName = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $this->uploader->ensureDirectory($this->bannerDirectory);
        $logoDest = $this->bannerDirectory . DIRECTORY_SEPARATOR . $logoFileName;
        if (!$this->uploader->move($upload, $logoDest)) {
            throw new RuntimeException('Không thể lưu file logo.');
        }

        // Xóa logo cũ nếu là file đã upload (không xóa logo gốc assets/)
        $this->deleteUploadedLogo($currentLogoSrc);
        $newLogoSrc = 'storage/banner/' . $logoFileName;
        file_put_contents($this->logoConfigFile, json_encode(['src' => $newLogoSrc], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $newLogoSrc;
    }

    /** Khôi phục logo mặc định, trả về đường dẫn logo mặc định. */
    public function resetLogo(): string
    {
        $this->deleteUploadedLogo($this->currentLogoSrc());
        if (is_file($this->logoConfigFile)) {
            unlink($this->logoConfigFile);
        }

        return self::DEFAULT_LOGO_SRC;
    }

    /**
     * Khôi phục banner ở vị trí $slot (1-4) về mặc định.
     * Trả về null nếu vị trí không hợp lệ (không làm gì), ngược lại trả về danh sách banner mới.
     *
     * @throws RuntimeException khi không lưu được cấu hình
     */
    public function resetBanner(array $banners, int $slot): ?array
    {
        if ($slot < 1 || $slot > self::SLOT_COUNT) {
            return null;
        }

        $index = $slot - 1;
        $this->deleteUploadedBanner((string) ($banners[$index]['src'] ?? ''));
        $banners[$index] = $this->defaultBanners()[$index];
        if (!$this->saveBanners($banners)) {
            throw new RuntimeException('Không thể lưu cấu hình banner.');
        }

        return $banners;
    }

    /**
     * Thay ảnh banner ở vị trí $slot bằng ảnh tải lên, trả về danh sách banner mới.
     *
     * @throws RuntimeException với thông báo tiếng Việt khi thất bại
     */
    public function replaceBanner(array $banners, int $slot, ?array $upload): array
    {
        if ($slot < 1 || $slot > self::SLOT_COUNT) {
            throw new RuntimeException('Vị trí banner không hợp lệ.');
        }
        if (!$this->uploader->isUploaded($upload)) {
            throw new RuntimeException('Vui lòng chọn một ảnh banner.');
        }

        $extension = $this->uploader->extensionByMime($upload, self::BANNER_TYPES);
        if ($extension === null) {
            throw new RuntimeException('Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.');
        }

        $fileName = 'banner_' . $slot . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $destination = $this->uploadDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!$this->uploader->move($upload, $destination)) {
            throw new RuntimeException('Không thể lưu ảnh banner.');
        }

        $index = $slot - 1;
        $oldSrc = (string) ($banners[$index]['src'] ?? '');
        $alt = (string) ($banners[$index]['alt'] ?? $this->defaultBanners()[$index]['alt']);
        $banners[$index] = [
            'src' => 'storage/banner/uploads/' . $fileName,
            'alt' => $alt,
        ];

        if (!$this->saveBanners($banners)) {
            unlink($destination);
            throw new RuntimeException('Không thể lưu cấu hình banner.');
        }

        $this->deleteUploadedBanner($oldSrc);

        return $banners;
    }

    /** Ghi cấu hình banner ra JSON (giữ nguyên định dạng file). */
    private function saveBanners(array $banners): bool
    {
        return file_put_contents(
            $this->configFile,
            json_encode($banners, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ) !== false;
    }

    private function deleteUploadedBanner(string $src): void
    {
        if (strpos($src, 'storage/banner/uploads/') === 0) {
            $file = $this->rootDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $src);
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function deleteUploadedLogo(string $src): void
    {
        if (strpos($src, 'storage/banner/logo_') === 0) {
            $file = $this->rootDir . '/' . $src;
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
