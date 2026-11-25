<?php

namespace App\Controllers\Client;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Services\HoSoKhachHangService;
use DomainException;

/**
 * Lớp HoSoController - trang hồ sơ khách hàng (client/ho_so.php):
 *  - GET: hiển thị họ tên, tên đăng nhập, số điện thoại, ngày tạo tài khoản
 *  - POST action=update_info: cập nhật họ tên và số điện thoại
 *  - POST action=change_password: đổi mật khẩu (cần mật khẩu hiện tại)
 * Mỗi POST kiểm tra CSRF rồi chuyển hướng lại trang (Post/Redirect/Get) kèm thông báo.
 */
class HoSoController extends BaseClientController
{
    public const CSRF_KEY = 'profile_csrf';
    private const FLASH_KEY = 'profile';

    private HoSoKhachHangService $service;

    public function __construct(?HoSoKhachHangService $service = null)
    {
        $this->service = $service ?? new HoSoKhachHangService();
    }

    /** Action: hiển thị / xử lý trang hồ sơ. */
    public function index(): void
    {
        $userId = Auth::customerId();
        if ($userId === null) {
            Response::redirect(Url::to('/index.php?login_required=profile'));
        }

        if (Request::isPost()) {
            $this->handlePost($userId);
        }

        $profile = $this->service->getProfile($userId);
        if ($profile === null) {
            // Tài khoản đã bị xóa: đăng xuất phiên khách hàng.
            Auth::logoutCustomer();
            Response::redirect(Url::to('/index.php'));
        }

        $flash = Session::flash(self::FLASH_KEY);
        $flash = is_array($flash) ? $flash : null;

        $this->render('client/ho_so', [
            'pageTitle' => 'Hồ sơ của tôi - ÉCRIN JEWELRY',
            'bodyClass' => 'profile-page',
            'profile' => $profile,
            'memberSince' => $this->formatDate((string) $profile['created_at']),
            'infoFlash' => ($flash['section'] ?? '') === 'info' ? $flash : null,
            'passwordFlash' => ($flash['section'] ?? '') === 'password' ? $flash : null,
            'csrfToken' => Session::csrfToken(self::CSRF_KEY),
        ]);
    }

    /** Xử lý form POST, lưu thông báo vào flash rồi chuyển hướng (PRG). */
    private function handlePost(int $userId): void
    {
        $action = (string) Request::post('action', '');
        $section = $action === 'change_password' ? 'password' : 'info';

        try {
            if (!Session::verifyCsrf((string) Request::post('csrf_token', ''), self::CSRF_KEY)) {
                throw new DomainException('Phiên làm việc đã hết hạn. Vui lòng tải lại trang và thử lại.');
            }

            if ($action === 'update_info') {
                $this->service->updateInfo(
                    $userId,
                    (string) Request::post('full_name', ''),
                    (string) Request::post('phone', '')
                );
                $message = 'Đã cập nhật thông tin cá nhân.';
            } elseif ($action === 'change_password') {
                $this->service->changePassword(
                    $userId,
                    (string) Request::post('current_password', ''),
                    (string) Request::post('new_password', ''),
                    (string) Request::post('confirm_password', '')
                );
                Session::regenerate(); // đổi mã phiên sau khi đổi mật khẩu
                $message = 'Đã đổi mật khẩu thành công.';
            } else {
                throw new DomainException('Thao tác không hợp lệ.');
            }

            Session::flash(self::FLASH_KEY, ['type' => 'success', 'section' => $section, 'message' => $message]);
        } catch (DomainException $e) {
            Session::flash(self::FLASH_KEY, ['type' => 'error', 'section' => $section, 'message' => $e->getMessage()]);
        }

        Response::redirect(Url::to('/client/ho_so.php') . '#' . $section);
    }

    private function formatDate(string $dateTime): string
    {
        $timestamp = strtotime($dateTime);

        return $timestamp ? date('d/m/Y', $timestamp) : '';
    }
}
