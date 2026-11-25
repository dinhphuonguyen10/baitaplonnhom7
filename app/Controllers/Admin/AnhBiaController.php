<?php

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Services\AnhBiaService;
use RuntimeException;

/**
 * Controller trang admin/anh_bia.php - thay/khôi phục 4 banner trang chủ và logo.
 * Chỉ dành cho quản lý (manager/admin).
 */
class AnhBiaController extends BaseAdminController
{
    protected bool $requireManager = true;

    private const CSRF_KEY = 'banner_csrf';
    private const INVALID_SESSION = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.';

    private AnhBiaService $banners;

    public function __construct(?Database $db = null, ?AnhBiaService $banners = null)
    {
        parent::__construct($db);
        $this->banners = $banners ?? new AnhBiaService();
    }

    /** Xử lý POST (nếu có) và trả về dữ liệu cho view. */
    protected function handle(): array
    {
        $currentLogoSrc = $this->banners->currentLogoSrc();
        $this->banners->ensureDirectories();
        $banners = $this->banners->loadBanners();
        $csrfToken = $this->csrfToken(self::CSRF_KEY);

        $message = '';
        $messageType = '';
        $logoMessage = '';
        $logoMessageType = '';

        if (Request::isPost()) {
            $token = (string) Request::post('csrf_token', '');
            $action = (string) Request::post('action', '');
            $validToken = $this->verifyCsrf($token, self::CSRF_KEY);

            if ($action === 'upload_logo' || $action === 'reset_logo') {
                if (!$validToken) {
                    $logoMessage = self::INVALID_SESSION;
                    $logoMessageType = 'error';
                } else {
                    try {
                        if ($action === 'upload_logo') {
                            $currentLogoSrc = $this->banners->uploadLogo(Request::file('logo_image'));
                            $logoMessage = 'Đã cập nhật logo thành công.';
                        } else {
                            $currentLogoSrc = $this->banners->resetLogo();
                            $logoMessage = 'Đã khôi phục logo mặc định.';
                        }
                        $logoMessageType = 'success';
                    } catch (RuntimeException $e) {
                        $logoMessage = $e->getMessage();
                        $logoMessageType = 'error';
                    }
                }
            } elseif (!$validToken) {
                $message = self::INVALID_SESSION;
                $messageType = 'error';
            } elseif ($action === 'reset' || $action === 'replace') {
                $slot = (int) Request::post('slot', 0);
                try {
                    if ($action === 'reset') {
                        $updated = $this->banners->resetBanner($banners, $slot);
                        if ($updated !== null) {
                            $banners = $updated;
                            $message = "Đã khôi phục banner $slot về mặc định.";
                            $messageType = 'success';
                        }
                    } else {
                        $banners = $this->banners->replaceBanner($banners, $slot, Request::file('banner_image'));
                        $message = "Đã thay banner $slot thành công.";
                        $messageType = 'success';
                    }
                } catch (RuntimeException $e) {
                    $message = $e->getMessage();
                    $messageType = 'error';
                }
            }
        }

        return array_merge($this->layoutData('anh_bia.php'), [
            'banners' => $banners,
            'currentLogoSrc' => $currentLogoSrc,
            'csrfToken' => $csrfToken,
            'message' => $message,
            'messageType' => $messageType,
            'logoMessage' => $logoMessage,
            'logoMessageType' => $logoMessageType,
        ]);
    }

    /** Action: hiển thị View admin/banners. */
    public function index(): void
    {
        $this->render('admin/anh_bia', $this->handle());
    }
}
