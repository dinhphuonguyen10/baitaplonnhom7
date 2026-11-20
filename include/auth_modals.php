<style>
.pw-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.pw-wrap input {
    width: 100%;
    padding-right: 42px !important;
}
.pw-toggle-btn {
    position: absolute;
    right: 10px;
    background: transparent !important;
    border: 0 !important;
    border-radius: 0 !important;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #aaa;
    width: auto !important;
    margin: 0 !important;
    transition: color 0.2s;
    line-height: 0;
}
.pw-toggle-btn:hover { color: var(--navy, #0b2033); }
.pw-toggle-btn svg { width: 20px; height: 20px; display: block; }
</style>

    <!-- MODAL ĐĂNG NHẬP -->
    <div id="login-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="close-login">&times;</span>
            <h2 style="margin-bottom: 8px; color: var(--navy); text-align: center;">Đăng nhập khách hàng</h2>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <input type="text" id="login-username" placeholder="Tên đăng nhập"
                    style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                <div class="pw-wrap">
                    <input type="password" id="login-password" placeholder="Mật khẩu"
                        style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                    <button type="button" class="pw-toggle-btn" onclick="togglePw('login-password', this)" aria-label="Hiện/Ẩn mật khẩu" data-visible="0"></button>
                </div>
                <button id="submit-login"
                    style="padding: 12px; background: linear-gradient(135deg, var(--navy), var(--navy-mid)); color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">Đăng nhập</button>
            </div>
            <p id="login-message" style="margin: 8px 0 0; text-align: center; font-size: 0.9rem; min-height: 20px; color: #c0392b;"></p>
            <p style="margin: 6px 0 0; text-align: center;">
                <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/quen_mat_khau.php', ENT_QUOTES, 'UTF-8'); ?>" style="color: var(--gold); font-weight: bold; text-decoration: none;">Quên mật khẩu?</a>
            </p>
            <p style="margin-top: 15px; text-align: center; font-size: 0.9rem; color: #666;">
                Chưa có tài khoản? <span class="register-trigger"
                    style="color: var(--gold); cursor: pointer; font-weight: bold;">Đăng ký ngay</span>
            </p>
        </div>
    </div>

    <!-- MODAL ĐĂNG KÝ -->
    <div id="register-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-modal" id="close-register">&times;</span>
            <h2 style="margin-bottom: 20px; color: var(--navy); text-align: center;">Đăng ký tài khoản</h2>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <input type="text" id="register-username" placeholder="Tên tài khoản" autocomplete="username" maxlength="100" required
                    style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                <input type="tel" id="register-phone" placeholder="Số điện thoại (10 số, dùng để lấy lại mật khẩu)" autocomplete="tel" maxlength="10" required
                    style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                <div class="pw-wrap">
                    <input type="password" id="register-password" placeholder="Tạo mật khẩu (ví dụ: Abc@1234)" autocomplete="new-password" minlength="8" required
                        style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                    <button type="button" class="pw-toggle-btn" onclick="togglePw('register-password', this)" aria-label="Hiện/Ẩn mật khẩu" data-visible="0"></button>
                </div>
                <small style="font-size: 0.78rem; color: #666; margin-top: -8px; margin-bottom: 2px; line-height: 1.3;">* Yêu cầu tối thiểu 8 ký tự: gồm chữ hoa, thường, số và ký tự đặc biệt</small>
                <div class="pw-wrap">
                    <input type="password" id="register-confirm-password" placeholder="Nhập lại mật khẩu" autocomplete="new-password" minlength="8" required
                        style="padding: 10px; border: 1px solid #ccc; border-radius: 8px;">
                    <button type="button" class="pw-toggle-btn" onclick="togglePw('register-confirm-password', this)" aria-label="Hiện/Ẩn mật khẩu" data-visible="0"></button>
                </div>
                <button id="submit-register"
                    style="padding: 12px; background: #fff; color: var(--navy); border: 2px solid var(--gold); border-radius: 8px; font-weight: bold; cursor: pointer;">Đăng ký</button>
            </div>
            <p id="register-message" style="margin: 8px 0 0; text-align: center; font-size: 0.9rem; min-height: 20px; color: #c0392b;"></p>
            <p style="margin-top: 10px; text-align: center; font-size: 0.9rem; color: #666;">
                Đã có tài khoản? <span class="login-trigger"
                    style="color: var(--gold); cursor: pointer; font-weight: bold;">Đăng nhập</span>
            </p>
        </div>
    </div>

<script>
/* SVG mắt mở: nhìn thấy → đang hiện mật khẩu */
var EYE_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
/* SVG mắt nhắm: gạch ngang → đang ẩn mật khẩu */
var EYE_CLOSED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

function togglePw(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var isVisible = btn.getAttribute('data-visible') === '1';
    if (isVisible) {
        /* Đang hiện → nhắm mắt lại → ẩn mật khẩu */
        input.type = 'password';
        btn.innerHTML = EYE_CLOSED;
        btn.setAttribute('data-visible', '0');
        btn.title = 'Hiện mật khẩu';
    } else {
        /* Đang ẩn → mở mắt ra → hiện mật khẩu */
        input.type = 'text';
        btn.innerHTML = EYE_OPEN;
        btn.setAttribute('data-visible', '1');
        btn.title = 'Ẩn mật khẩu';
    }
}

/* Khởi tạo tất cả nút toggle với icon mắt nhắm (mật khẩu đang ẩn) */
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pw-toggle-btn').forEach(function(btn) {
        btn.innerHTML = EYE_CLOSED;
        btn.title = 'Hiện mật khẩu';
    });
});
</script>
