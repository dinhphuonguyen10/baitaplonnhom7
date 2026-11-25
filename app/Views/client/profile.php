<?php
// View: hồ sơ khách hàng. Dữ liệu do Client\ProfileController::index() cung cấp.
use App\Core\View;

include VIEW_PATH . '/layouts/header.php';
?>
<style>
    .profile-main { max-width: 760px; margin: 32px auto 56px; padding: 0 16px; }
    .profile-main h1 { margin: 0 0 6px; color: var(--navy); }
    .profile-subtitle { margin: 0 0 24px; color: #666; }
    .profile-card { background: #fff; border: 1px solid #e6e1d8; border-radius: 14px; padding: 24px; margin-bottom: 20px; box-shadow: 0 6px 18px rgba(15, 39, 71, .05); }
    .profile-card h2 { margin: 0 0 16px; font-size: 1.15rem; color: var(--navy); }
    .profile-info { display: grid; grid-template-columns: 180px 1fr; gap: 10px 16px; margin: 0; }
    .profile-info dt { color: #777; }
    .profile-info dd { margin: 0; font-weight: 600; color: #222; }
    .profile-form { display: grid; gap: 14px; max-width: 420px; }
    .profile-form label { display: grid; gap: 6px; font-weight: 600; font-size: 14px; color: var(--navy); }
    .profile-form input { padding: 11px 14px; border: 1px solid #cbd4df; border-radius: 10px; font: inherit; }
    .profile-form input:focus { outline: none; border-color: var(--navy); }
    .profile-form small { color: #777; font-weight: 400; }
    .profile-form button { justify-self: start; padding: 11px 22px; border: 0; border-radius: 10px; background: var(--navy); color: #fff; font: 600 14px inherit; cursor: pointer; }
    .profile-alert { padding: 11px 14px; border-radius: 10px; margin-bottom: 14px; font-size: 14px; }
    .profile-alert.success { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
    .profile-alert.error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }
    .profile-links { display: flex; gap: 16px; flex-wrap: wrap; }
    .profile-links a { color: var(--navy); font-weight: 600; }
    @media (max-width: 560px) { .profile-info { grid-template-columns: 1fr; } .profile-info dd { margin-bottom: 8px; } }
</style>

<main class="profile-main">
    <h1>Hồ sơ của tôi</h1>
    <p class="profile-subtitle">Quản lý thông tin tài khoản và bảo mật đăng nhập.</p>

    <section class="profile-card" aria-labelledby="profile-info-title">
        <h2 id="profile-info-title">Thông tin tài khoản</h2>
        <dl class="profile-info">
            <dt>Họ và tên</dt>
            <dd><?php echo ($profile['full_name'] ?? '') !== '' ? View::e($profile['full_name']) : 'Chưa cập nhật'; ?></dd>
            <dt>Tên đăng nhập</dt>
            <dd><?php echo View::e($profile['username']); ?></dd>
            <dt>Số điện thoại</dt>
            <dd><?php echo ($profile['phone'] ?? '') !== '' ? View::e($profile['phone']) : 'Chưa cập nhật'; ?></dd>
            <dt>Thành viên từ</dt>
            <dd><?php echo View::e($memberSince); ?></dd>
        </dl>
    </section>

    <section class="profile-card" id="info" aria-labelledby="profile-edit-title">
        <h2 id="profile-edit-title">Cập nhật thông tin cá nhân</h2>
        <?php if ($infoFlash !== null): ?>
            <div class="profile-alert <?php echo $infoFlash['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo View::e($infoFlash['message']); ?></div>
        <?php endif; ?>
        <form method="post" class="profile-form">
            <input type="hidden" name="action" value="update_info">
            <input type="hidden" name="csrf_token" value="<?php echo View::e($csrfToken); ?>">
            <label>
                Họ và tên
                <input type="text" name="full_name" maxlength="150" autocomplete="name"
                       placeholder="Ví dụ: Nguyễn Thị Mai" value="<?php echo View::e($profile['full_name'] ?? ''); ?>" required>
            </label>
            <label>
                Số điện thoại
                <input type="tel" name="phone" inputmode="numeric" maxlength="20" autocomplete="tel"
                       placeholder="Ví dụ: 0912345678" value="<?php echo View::e($profile['phone'] ?? ''); ?>" required>
                <small>Gồm 10 chữ số, bắt đầu bằng 0. Số điện thoại dùng để khôi phục mật khẩu.</small>
            </label>
            <button type="submit">Lưu thông tin</button>
        </form>
    </section>

    <section class="profile-card" id="password" aria-labelledby="profile-password-title">
        <h2 id="profile-password-title">Đổi mật khẩu</h2>
        <?php if ($passwordFlash !== null): ?>
            <div class="profile-alert <?php echo $passwordFlash['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo View::e($passwordFlash['message']); ?></div>
        <?php endif; ?>
        <form method="post" class="profile-form" autocomplete="off">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?php echo View::e($csrfToken); ?>">
            <label>
                Mật khẩu hiện tại
                <input type="password" name="current_password" autocomplete="current-password" required>
            </label>
            <label>
                Mật khẩu mới
                <input type="password" name="new_password" autocomplete="new-password" minlength="8" required>
                <small>Tối thiểu 8 ký tự, gồm chữ thường, chữ in hoa, số và ký tự đặc biệt (ví dụ: Abc@1234).</small>
            </label>
            <label>
                Xác nhận mật khẩu mới
                <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
            </label>
            <button type="submit">Đổi mật khẩu</button>
        </form>
    </section>

    <section class="profile-card" aria-label="Liên kết tài khoản">
        <div class="profile-links">
            <a href="<?php echo View::e($appBaseUrl . '/client/tracuudh.php'); ?>">Đơn hàng của tôi</a>
            <a href="<?php echo View::e($appBaseUrl . '/client/wishlist.php'); ?>">Sản phẩm yêu thích</a>
        </div>
    </section>
</main>
<?php include VIEW_PATH . '/layouts/footer.php'; ?>
