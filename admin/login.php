<?php
session_start();
require_once __DIR__ . '/../include/admin_roles.php';

// Tác dụng: Kiểm tra nếu quản trị viên đã đăng nhập từ trước thì chuyển hướng ngay về trang tổng quan
if (isset($_SESSION['admin_user_id']) && isAdminAreaRole($_SESSION['admin_role'] ?? null)) {
    header('Location: ' . adminRoleHomeUrl($_SESSION['admin_role']));
    exit;
}

if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config/db.php';

    $token = $_POST['csrf_token'] ?? '';
    $username = isset($_POST['username']) && is_string($_POST['username'])
        ? trim($_POST['username'])
        : '';
    $password = isset($_POST['password']) && is_string($_POST['password'])
        ? $_POST['password']
        : '';

    if (!is_string($token) || !hash_equals($_SESSION['admin_login_csrf'], $token)) {
        $error = 'Phiên đăng nhập không hợp lệ. Vui lòng tải lại trang và thử lại.';
    } elseif ($username === '' || $password === '') {
        $error = 'Vui lòng nhập tên đăng nhập và mật khẩu.';
    } else {
        // Phiên admin dùng khóa riêng và có thể tồn tại song song với phiên khách hàng.
        // Tác dụng: Query bảng admins (tài khoản nội bộ) thay vì bảng users (khách hàng)
        $stmt = $conn->prepare('SELECT id, username, password, role FROM admins WHERE username = ? LIMIT 1');

        if (!$stmt) {
            $error = 'Không thể đăng nhập lúc này. Vui lòng thử lại sau.';
        } else {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $conn->close();

            if (!$user || !isAdminAreaRole($user['role'] ?? null) || !password_verify($password, $user['password'])) {
                $error = 'Thông tin đăng nhập không đúng hoặc tài khoản không có quyền quản trị.';
            } else {
                session_regenerate_id(true);

                // Giữ nguyên phiên khách hàng để có thể chuyển qua lại giữa admin và client.
                $_SESSION['admin_user_id']  = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role']     = $user['role'];
                unset($_SESSION['admin_login_csrf']);

                header('Location: ' . adminRoleHomeUrl($user['role']));
                exit;
            }
        }
    }
}
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
            <p class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>

        <form method="post" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_login_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

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
