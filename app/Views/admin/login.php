<?php

use App\Core\View;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập quản trị - ÉCRIN JEWELRY</title>
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0;
            padding: 20px;
            background: #f7f6f2;
            color: #0b2033;
            font-family: Arial, sans-serif;
        }
        .login-card {
            width: min(420px, 100%);
            padding: 32px;
            border: 1px solid #e4e3df;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 12px 36px rgb(11 32 51 / 10%);
        }
        h1 { margin: 0 0 8px; font-size: 1.6rem; }
        .description { margin: 0 0 24px; color: #667085; }
        label { display: block; margin: 16px 0 7px; font-weight: 700; }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd0d7;
            border-radius: 8px;
            font: inherit;
        }
        input:focus { outline: 2px solid #c4a45a; border-color: transparent; }
        button {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            border: 0;
            border-radius: 8px;
            background: #0b2033;
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover { background: #172f45; }
        .error {
            margin: 0 0 16px;
            padding: 11px;
            border-radius: 8px;
            background: #fff0ef;
            color: #b42318;
        }
        .pw-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .pw-wrap input {
            width: 100%;
            padding-right: 44px;
        }
        .pw-toggle-btn {
            position: absolute;
            right: 10px;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            cursor: pointer;
            padding: 4px;
            width: auto !important;
            margin: 0 !important;
            font-size: 18px;
            line-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #aaa;
            transition: color 0.2s;
        }
        .pw-toggle-btn svg { width: 20px; height: 20px; display: block; }
        .pw-toggle-btn:hover { color: #0b2033; }

    </style>
</head>
<body>
    <main class="login-card">
        <h1>Đăng nhập quản trị</h1>
        <p class="description">Cổng quản trị dành riêng cho tài khoản Nhân viên và Quản lí.</p>

        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?php echo View::e($error); ?></p>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo View::e($csrfToken); ?>">

            <label for="username">Tên đăng nhập</label>
            <input id="username" name="username" type="text" autocomplete="username" required autofocus>

            <label for="password">Mật khẩu</label>
            <div class="pw-wrap">
                <input id="password" name="password" type="password" autocomplete="current-password" required>
                <button type="button" class="pw-toggle-btn" onclick="togglePw('password', this)" aria-label="Hiện/Ẩn mật khẩu" data-visible="0"></button>
            </div>

            <button type="submit">Đăng nhập</button>
        </form>

    </main>
<script>
var EYE_OPEN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
var EYE_CLOSED = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';

function togglePw(inputId, btn) {
    var input = document.getElementById(inputId);
    if (!input) return;
    var isVisible = btn.getAttribute('data-visible') === '1';
    if (isVisible) {
        input.type = 'password';
        btn.innerHTML = EYE_CLOSED;
        btn.setAttribute('data-visible', '0');
        btn.title = 'Hiện mật khẩu';
    } else {
        input.type = 'text';
        btn.innerHTML = EYE_OPEN;
        btn.setAttribute('data-visible', '1');
        btn.title = 'Ẩn mật khẩu';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.pw-toggle-btn').forEach(function(btn) {
        btn.innerHTML = EYE_CLOSED;
        btn.title = 'Hiện mật khẩu';
    });
});
</script>
</body>
</html>
