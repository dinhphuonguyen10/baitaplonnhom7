<?php

namespace App\Services;

use App\Core\DatabaseException;
use App\Helpers\KiemTraTaiKhoan;
use App\Models\KhachHang;
use DomainException;

/**
 * Lớp HoSoKhachHangService - nghiệp vụ hồ sơ khách hàng:
 * xem thông tin tài khoản, cập nhật số điện thoại, đổi mật khẩu.
 * Lỗi nghiệp vụ được ném ra dạng DomainException kèm thông báo tiếng Việt.
 */
class HoSoKhachHangService
{
    private KhachHang $users;

    public function __construct(?KhachHang $users = null)
    {
        $this->users = $users ?? new KhachHang();
    }

    /** Thông tin hồ sơ (id, username, phone, created_at) hoặc null nếu tài khoản không còn tồn tại. */
    public function getProfile(int $userId): ?array
    {
        return $this->users->findProfile($userId);
    }

    /**
     * Cập nhật họ tên và số điện thoại của khách hàng.
     * Họ tên: 2-150 ký tự, chỉ gồm chữ cái (có dấu) và khoảng trắng.
     */
    public function updateInfo(int $userId, string $fullName, string $phone): void
    {
        $fullName = KiemTraTaiKhoan::normalizeFullName($fullName);
        if (!KiemTraTaiKhoan::isValidFullName($fullName)) {
            throw new DomainException('Họ và tên phải từ 2 đến 150 ký tự và chỉ gồm chữ cái, khoảng trắng.');
        }

        $phone = KiemTraTaiKhoan::normalizePhone(trim($phone));
        if (!KiemTraTaiKhoan::isValidPhone($phone)) {
            throw new DomainException('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.');
        }

        $profile = $this->requireProfile($userId);
        if ((string) ($profile['full_name'] ?? '') === $fullName && (string) ($profile['phone'] ?? '') === $phone) {
            throw new DomainException('Thông tin không có thay đổi nào để lưu.');
        }
        if ($this->users->phoneUsedByOtherUser($phone, $userId)) {
            throw new DomainException('Số điện thoại này đã được tài khoản khác sử dụng.');
        }

        try {
            $this->users->updateProfileInfo($userId, $fullName, $phone);
        } catch (DatabaseException $e) {
            if ($e->getCode() === 1062) {
                throw new DomainException('Số điện thoại này đã được tài khoản khác sử dụng.');
            }
            error_log('Profile info update failed: ' . $e->getMessage());
            throw new DomainException('Không thể cập nhật thông tin lúc này. Vui lòng thử lại sau.');
        }
    }

    /** Đổi mật khẩu sau khi xác nhận mật khẩu hiện tại. */
    public function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
    {
        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new DomainException('Vui lòng nhập đầy đủ mật khẩu hiện tại, mật khẩu mới và xác nhận mật khẩu.');
        }

        $storedHash = $this->users->findPasswordHash($userId);
        if ($storedHash === null) {
            throw new DomainException('Tài khoản không tồn tại.');
        }
        // Hỗ trợ cả mật khẩu đã băm và mật khẩu cũ dạng plain-text (như chức năng đăng nhập).
        if (!password_verify($currentPassword, $storedHash) && $currentPassword !== $storedHash) {
            throw new DomainException('Mật khẩu hiện tại không đúng.');
        }
        if (!KiemTraTaiKhoan::isStrongPassword($newPassword)) {
            throw new DomainException('Mật khẩu mới phải có tối thiểu 8 ký tự, gồm chữ thường, chữ in hoa, số và ký tự đặc biệt (ví dụ: Abc@1234).');
        }
        if ($newPassword !== $confirmPassword) {
            throw new DomainException('Mật khẩu xác nhận không khớp.');
        }
        if ($newPassword === $currentPassword) {
            throw new DomainException('Mật khẩu mới phải khác mật khẩu hiện tại.');
        }

        try {
            $this->users->updatePasswordHash($userId, password_hash($newPassword, PASSWORD_DEFAULT));
        } catch (DatabaseException $e) {
            error_log('Profile password update failed: ' . $e->getMessage());
            throw new DomainException('Không thể đổi mật khẩu lúc này. Vui lòng thử lại sau.');
        }
    }

    private function requireProfile(int $userId): array
    {
        $profile = $this->users->findProfile($userId);
        if ($profile === null) {
            throw new DomainException('Tài khoản không tồn tại.');
        }

        return $profile;
    }
}
