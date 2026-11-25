<?php

namespace App\Services;

/**
 * Lớp AnhBiaTrangChuService - đọc banner trình chiếu cho trang chủ cửa hàng
 * từ storage/banner/banners.json (do trang quản trị banner lưu), thiếu thông tin thì dùng banner mặc định.
 */
class AnhBiaTrangChuService
{
    private AnhBiaService $banners;
    private string $configFile;

    public function __construct(?AnhBiaService $banners = null, ?string $configFile = null)
    {
        $this->banners = $banners ?? new AnhBiaService();
        $this->configFile = $configFile ?? APP_ROOT . '/storage/banner/banners.json';
    }

    /** @return array<int, array{src: string, alt: string}> */
    public function homeBanners(): array
    {
        $defaultBanners = $this->banners->defaultBanners();
        if (!is_file($this->configFile)) {
            return $defaultBanners;
        }

        $savedBanners = json_decode((string) file_get_contents($this->configFile), true);
        if (!is_array($savedBanners) || count($savedBanners) < 1) {
            return $defaultBanners;
        }

        $result = [];
        foreach ($savedBanners as $index => $banner) {
            $src = is_array($banner) ? (string) ($banner['src'] ?? '') : '';
            $alt = is_array($banner) ? (string) ($banner['alt'] ?? '') : '';
            if ($src === '') {
                $src = (string) ($defaultBanners[$index]['src'] ?? '');
            }
            if ($alt === '') {
                $alt = (string) ($defaultBanners[$index]['alt'] ?? '');
            }
            $result[] = ['src' => $src, 'alt' => $alt];
        }

        return $result;
    }

    /** URL logo hiện tại (logo tùy chỉnh nếu có, fallback logo mặc định). */
    public function logoUrl(string $appBaseUrl): string
    {
        return $this->banners->logoUrl($appBaseUrl);
    }
}
