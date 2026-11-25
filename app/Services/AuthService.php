<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\DatabaseException;
use App\Core\Session;
use App\Exceptions\AuthException;
use App\Models\Admin;
use App\Models\User;
use Throwable;

/**
 * Lớp AuthService - nghiệp vụ xác thực từ giao diện cửa hàng:
 *  - Đăng nhập chung (tìm khách hàng trước, sau đó tài khoản quản trị)
 *  - Đăng ký tài khoản khách hàng
 *  - Quên mật khẩu: xác minh tên tài khoản + SĐT và đặt lại mật khẩu
 *
 * Lỗi nghiệp vụ được ném dưới dạng AuthException (mã = HTTP status).
 */
class AuthService
{
    public const MSG_INVALID_CREDENTIALS = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    public const MSG_INVALID_PHONE = 'Số điện thoại không hợp lệ (phải đúng 10 số, ví dụ: 0901234567).';

    private User $users;
    private Admin $admins;

    public function __construct(?User $users = null, ?Admin $admins = null)
    {
        $this->users = $users ?? new User();
        $this->admins = $admins ?? new Admin();
    }

    // ───────────── Đăng nhập ─────────────

    /**
     * Đăng nhập: ưu tiên bảng users (khách hàng), nếu không có username thì tìm trong admins.
     * Phiên admin và khách hàng được lưu riêng để đăng nhập một bên không xóa phiên bên kia.
     *
     * @return array{role: string, username: string}
     * @throws AuthException 401 khi sai thông tin
     */
    public function login(string $username, string $password): array
    {
        $user = $this->users->findCredentialsByUsername($username);
        if ($user) {
            if (!$this->passwordMatches($password, (string) $user['password'])) {
                // Username trùng nhưng sai mật khẩu → trả lỗi luôn, không tìm tiếp admins
                throw new AuthException(self::MSG_INVALID_CREDENTIALS, 401);
            }

            if ($password === $user['password']) {
                $this->upgradePlainPassword(function (string $hash) use ($user): void {
                    $this->users->updatePasswordHash((int) $user['id'], $hash);
                }, $password);
            }

            Auth::loginCustomer((int) $user['id'], (string) $user['username']);

            return ['role' => 'customer', 'username' => $user['username']];
        }

        $admin = $this->admins->findForLogin($username);
        if ($admin && $this->passwordMatches($password, (string) $admin['password'])) {
            if ($password === $admin['password']) {
                $this->upgradePlainPassword(function (string $hash) use ($admin): void {
                    $this->admins->updatePasswordHash((int) $admin['id'], $hash);
                }, $password);
            }

            Auth::loginAdmin((int) $admin['id'], (string) $admin['username'], (string) $admin['role']);

            return ['role' => $admin['role'], 'username' => $admin['username']];
        }

        throw new AuthException(self::MSG_INVALID_CREDENTIALS, 401);
    }

    /** Đăng xuất phiên khách hàng (giữ nguyên phiên quản trị). */
    public function logoutCustomer(): void
    {
        Auth::logoutCustomer();
    }

    /**
     * Kiểm tra phiên khách hàng (role = customer) còn khớp với tài khoản trong DB.
     * Còn tồn tại: đồng bộ lại user_id/username trong session và trả về [id, username].
     * Tài khoản không còn: xóa thông tin đăng nhập khách hàng khỏi session, trả về null.
     */
    public function refreshCustomerSession(): ?array
    {
        $userId = filter_var(Session::get('user_id'), FILTER_VALIDATE_INT);
        $user = ($userId !== false && $userId > 0) ? $this->users->find((int) $userId) : null;

        if ($user === null) {
            Auth::logoutCustomer();

            return null;
        }

        Session::set('user_id', (int) $user['id']);
        Session::set('username', (string) $user['username']);

        return ['id' => (int) $user['id'], 'username' => (string) $user['username']];
    }

    // ───────────── Đăng ký ─────────────

    /**
     * Đăng ký tài khoản khách hàng mới.
     *
     * @throws AuthException 400/409/500 kèm thông báo tiếng Việt
     */
    public function registerCustomer(string $username, string $password, string $phone): int
    {
        $phone = $this->normalizePhone($phone);
        if ($phone !== '' && !$this->isValidPhone($phone)) {
            throw new AuthException(self::MSG_INVALID_PHONE, 400);
        }
        if ($username === '' || strlen($username) > 100) {
            throw new AuthException('Tên tài khoản không hợp lệ (tối đa 100 ký tự).', 400);
        }
        if (!$this->isValidPhone($phone)) {
            throw new AuthException(self::MSG_INVALID_PHONE, 400);
        }
        if (!$this->isStrongPassword($password)) {
            throw new AuthException('Mật khẩu phải có tối thiểu 8 ký tự, gồm chữ thường, chữ in hoa, số và ký tự đặc biệt (ví dụ: Abc@1234).', 400);
        }

        try {
            $phoneUsed = $this->users->phoneRegistered($phone);
        } catch (DatabaseException $e) {
            throw new AuthException('Không thể xử lý yêu cầu lúc này.', 500);
        }
        if ($phoneUsed) {
            throw new AuthException('Số điện thoại này đã được đăng ký trước đó. Vui lòng nhập số điện thoại khác.', 409);
        }

        try {
            return $this->users->createCustomer($username, password_hash($password, PASSWORD_DEFAULT), $phone);
        } catch (DatabaseException $e) {
            error_log('Customer registration failed: ' . $e->getMessage());
            if ($e->getCode() === 1062) {
                throw new AuthException('Tên tài khoản đã được sử dụng.', 409);
            }
            throw new AuthException('Không thể tạo tài khoản lúc này.', 500);
        }
    }

    // ───────────── Quên mật khẩu ─────────────

    /**
     * Bước 1: xác minh tên tài khoản + SĐT (chỉ tài khoản khách hàng).
     *
     * @throws AuthException
     */
    public function verifyResetAccount(string $username, string $phone): void
    {
        if ($username === '' || $phone === '') {
            throw new AuthException('Vui lòng nhập đầy đủ thông tin.', 400);
        }

        if ($this->findResetUserId($username, $phone) === null) {
            // Trả về lỗi chung, không tiết lộ thông tin nào đúng/sai
            throw new AuthException('Tên đăng nhập hoặc số điện thoại không chính xác.', 404);
        }
    }

    /**
     * Bước 2: đặt lại mật khẩu sau khi xác minh lại tài khoản.
     *
     * @throws AuthException
     */
    public function resetPassword(string $username, string $phone, string $newPassword, string $confirmPassword): void
    {
        if ($username === '' || $phone === '') {
            throw new AuthException('Thiếu thông tin tài khoản.', 400);
        }
        if (!$this->isStrongPassword($newPassword)) {
            throw new AuthException('Mật khẩu mới phải có tối thiểu 8 ký tự, gồm chữ thường, chữ in hoa, số và ký tự đặc biệt (ví dụ: Abc@1234).', 400);
        }
        if ($newPassword !== $confirmPassword) {
            throw new AuthException('Mật khẩu xác nhận không khớp.', 400);
        }

        $userId = $this->findResetUserId($username, $phone);
        if ($userId === null) {
            throw new AuthException('Tài khoản không tồn tại.', 404);
        }

        try {
            $this->users->updatePasswordHash($userId, password_hash($newPassword, PASSWORD_DEFAULT));
        } catch (DatabaseException $e) {
            throw new AuthException('Lỗi hệ thống.', 500);
        }
    }

    /**
     * Chuẩn hoá SĐT cho chức năng quên mật khẩu (bỏ ký tự phân cách, +84xxx → 0xxx) và kiểm tra định dạng.
     *
     * @throws AuthException 400 khi SĐT không hợp lệ
     */
    public function normalizeResetPhone(string $phone): string
    {
        $phone = $this->normalizePhone($phone);
        if (strncmp($phone, '+84', 3) === 0) {
            $phone = '0' . substr($phone, 3);
        }
        if ($phone !== '' && !$this->isValidPhone($phone)) {
            throw new AuthException(self::MSG_INVALID_PHONE, 400);
        }

        return $phone;
    }

    // ───────────── Tiện ích nội bộ ─────────────

    private function findResetUserId(string $username, string $phone): ?int
    {
        try {
            return $this->users->findIdByUsernameAndPhone($username, $phone);
        } catch (DatabaseException $e) {
            throw new AuthException('Lỗi hệ thống.', 500);
        }
    }

    /** Hỗ trợ cả mật khẩu đã băm và mật khẩu cũ dạng plain-text. */
    private function passwordMatches(string $password, string $stored): bool
    {
        return password_verify($password, $stored) || $password === $stored;
    }

    /** Tự động nâng cấp mật khẩu plain-text lên hash (bỏ qua nếu lỗi, như trước đây). */
    private function upgradePlainPassword(callable $save, string $password): void
    {
        try {
            $save(password_hash($password, PASSWORD_DEFAULT));
        } catch (Throwable $e) {
            // Không chặn đăng nhập khi không cập nhật được mật khẩu.
        }
    }

    /** Bỏ khoảng trắng, dấu chấm, gạch, ngoặc khỏi SĐT. */
    private function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/[\s().-]/', '', $phone);
    }

    private function isValidPhone(string $phone): bool
    {
        return preg_match('/^0[0-9]{9}$/', $phone) === 1;
    }

    /** Tối thiểu 8 ký tự, có chữ thường, chữ in hoa, số và ký tự đặc biệt. */
    private function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[a-z]/', $password)
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^a-zA-Z0-9]/', $password);
    }
}
