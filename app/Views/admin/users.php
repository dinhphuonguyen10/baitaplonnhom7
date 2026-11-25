<?php

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý tài khoản - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .prototype-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .prototype-panel h2 { margin-top: 0; font-size: 1.25rem; }
        .prototype-form { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; align-items: end; gap: 18px; }
        .prototype-form label { display: grid; min-width: 0; gap: 6px; font-weight: 700; font-size: 0.9rem; }
        .prototype-form input, .prototype-form select { width: 100%; padding: 9px 12px; border: 1px solid #d5dbe2; border-radius: 7px; font: inherit; }
        .prototype-form button { padding: 9px 16px; border: 0; border-radius: 7px; background: var(--admin-navy, #0f2747); color: #fff; font-weight: bold; cursor: pointer; }
        .prototype-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .prototype-table th, .prototype-table td { padding: 12px; border-bottom: 1px solid #edf0f3; text-align: left; vertical-align: middle; }
        .notice { margin: 18px 0; padding: 12px 16px; border-radius: 8px; }
        .notice.error { background: #fff0ef; color: #b42318; border: 1px solid #fda29b; }
        .notice.success { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; }
        .badge-admin { background: #fef0c7; color: #b54708; }
        .badge-manager { background: #e0f2fe; color: #0369a1; }
        .badge-staff { background: #f3f4f6; color: #374151; }

        .btn-reset-pwd {
            padding: 6px 12px;
            background: #fff;
            border: 1px solid #c4a45a;
            color: #8c6e26;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-reset-pwd:hover {
            background: #c4a45a;
            color: #fff;
        }
        .account-actions { display: inline-flex; justify-content: flex-end; gap: 8px; }
        .btn-delete-user {
            padding: 6px 12px;
            background: #fff;
            border: 1px solid #d92d20;
            color: #b42318;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-delete-user:hover { background: #d92d20; color: #fff; }

        /* Modal đổi mật khẩu */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(11, 32, 51, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff;
            border-radius: 14px;
            padding: 28px;
            width: min(440px, 92%);
            box-shadow: 0 20px 48px rgba(0,0,0,0.25);
            position: relative;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .modal-header h3 { margin: 0; color: #0b2033; font-size: 1.2rem; }
        .close-modal { font-size: 1.5rem; cursor: pointer; border: none; background: transparent; color: #888; }
        .modal-field { margin-bottom: 16px; display: grid; gap: 6px; }
        .modal-field label { font-weight: 700; font-size: 0.9rem; color: #333; }
        .modal-field input { padding: 10px 12px; border: 1px solid #cbd0d7; border-radius: 8px; font: inherit; }
        .modal-field input:focus { outline: 2px solid #c4a45a; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-cancel { padding: 9px 16px; border: 1px solid #ccc; background: #fff; border-radius: 7px; cursor: pointer; }
        .btn-submit-reset { padding: 9px 18px; border: 0; background: #0f2747; color: #fff; font-weight: bold; border-radius: 7px; cursor: pointer; }
        .btn-submit-reset:hover { background: #1a3c68; }
        /* Toggle mật khẩu */
        .pw-wrap {
            position: relative;
            display: grid;
            gap: 6px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .pw-wrap-inner {
            position: relative;
            display: flex;
            align-items: center;
        }
        .pw-wrap-inner input { width: 100%; padding-right: 40px; }
        .pw-toggle-btn {
            position: absolute;
            right: 8px;
            width: auto !important;
            min-width: 0;
            margin: 0;
            background: transparent !important;
            border: 0 !important;
            border-radius: 0 !important;
            cursor: pointer;
            font-size: 17px;
            line-height: 1;
            padding: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            color: #64758a !important;
            transition: color 0.2s;
        }
        .pw-toggle-btn svg { display: block; width: 20px; height: 20px; }
        .pw-toggle-btn:hover { color: #0f2747 !important; }

        @media (max-width: 900px) {
            .prototype-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .prototype-form > button { grid-column: 1 / -1; }
        }

        /* Giao diện quản lý tài khoản */
        .page-content {
            max-width: 1380px;
            padding: 42px 42px 64px;
        }
        .page-content > h1 {
            margin-bottom: 28px;
            color: #0f2747;
            font-size: 26px !important;
            line-height: 1.25;
            letter-spacing: -0.04em;
        }
        .prototype-panel {
            margin: 0 0 24px;
            padding: 28px;
            border: 1px solid #e4eaf1;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 12px 30px rgba(15, 39, 71, .06);
        }
        .prototype-panel h2 {
            margin: 0 0 22px;
            color: #0f2747;
            font-size: 1.2rem;
            letter-spacing: -0.02em;
        }
        .prototype-form {
            gap: 20px;
            padding: 22px;
            border: 1px solid #edf1f5;
            border-radius: 14px;
            background: #f8fafc;
        }
        .prototype-form label { color: #344054; }
        .prototype-form input,
        .prototype-form select {
            min-height: 46px;
            border-color: #d8e0ea;
            background: #fff;
            color: #17283d;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .prototype-form input:focus,
        .prototype-form select:focus {
            outline: 0;
            border-color: #6c91b8;
            box-shadow: 0 0 0 3px rgba(76, 131, 184, .14);
        }
        .prototype-form > button {
            min-height: 46px;
            padding: 0 20px;
            border-radius: 9px;
            background: #0f2747;
            transition: background .2s ease, transform .2s ease;
        }
        .prototype-form > button:hover { background: #193b61; transform: translateY(-1px); }
        .prototype-table { margin-top: 0; border: 1px solid #e8edf3; border-radius: 12px; overflow: hidden; }
        .prototype-table th {
            padding: 15px 16px;
            border-bottom: 1px solid #e4eaf1;
            background: #f7f9fb;
            color: #667085;
            font-size: .72rem;
            letter-spacing: .06em;
            text-transform: uppercase;
        }
        .prototype-table td { padding: 17px 16px; color: #344054; }
        .prototype-table tbody tr { transition: background .18s ease; }
        .prototype-table tbody tr:hover { background: #f8fbff; }
        .prototype-table td strong { color: #0f2747; }
        .badge { padding: 6px 11px; border-radius: 999px; font-size: .72rem; letter-spacing: .03em; }
        .badge-manager { background: #e8f3ff; color: #1769aa; }
        .badge-staff { background: #f1f3f5; color: #475467; }
        .account-actions { gap: 8px; flex-wrap: wrap; }
        .btn-reset-pwd, .btn-delete-user { min-height: 36px; padding: 7px 12px; border-radius: 8px; transition: all .2s ease; }
        .btn-reset-pwd:hover, .btn-delete-user:hover { transform: translateY(-1px); }
        .modal-box { border: 1px solid #e4eaf1; border-radius: 18px; }

        @media (max-width: 900px) {
            .page-content { padding: 28px 20px 48px; }
            .prototype-panel { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý tài khoản</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
        <h1>Quản lý tài khoản</h1>
        <?php if ($error !== ''): ?><p class="notice error" role="alert"><?php echo $escape($error); ?></p><?php endif; ?>
        <?php if ($success !== ''): ?><p class="notice success" role="status"><?php echo $escape($success); ?></p><?php endif; ?>

        <!-- Form tạo tài khoản mới -->
        <section class="prototype-panel">
            <form method="post" class="prototype-form">
                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                <input type="hidden" name="action" value="create">
                <label>Tên đăng nhập
                    <input type="text" name="username" placeholder="Ví dụ: nhanvien_a" minlength="3" maxlength="100" required>
                </label>
                <label class="pw-wrap">Mật khẩu ban đầu
                    <div class="pw-wrap-inner">
                        <input type="password" name="password" placeholder="Tối thiểu 8 ký tự" minlength="8" required id="create-password">
                        <button type="button" class="pw-toggle-btn" onclick="togglePw('create-password', this)" aria-label="Hiện/Ẩn mật khẩu" data-visible="0"></button>
                    </div>
                </label>
                <label>Vai trò
                    <select name="role">
                        <option value="staff">Nhân viên</option>
                        <option value="manager">Quản lý</option>
                    </select>
                </label>
                <button type="submit">+ Tạo tài khoản</button>
            </form>
        </section>

        <!-- Danh sách tài khoản & Đổi mật khẩu -->
        <section class="prototype-panel">
            <h2>Danh sách tài khoản nội bộ & Đổi mật khẩu</h2>
            <table class="prototype-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tên đăng nhập</th>
                        <th>Vai trò</th>
                        <th>Ngày tạo</th>
                        <th>Trạng thái</th>
                        <th style="text-align: right;">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($staffAccounts !== []): ?>
                    <?php foreach ($staffAccounts as $staff): ?>
                        <?php
                            $roleClass = match($staff['role']) {
                                'admin' => 'badge-admin',
                                'manager' => 'badge-manager',
                                default => 'badge-staff'
                            };
                        ?>
                        <tr>
                            <td>#<?php echo $escape($staff['id']); ?></td>
                            <td><strong><?php echo $escape($staff['username']); ?></strong></td>
                            <td><span class="badge <?php echo $roleClass; ?>"><?php echo $escape($staff['role'] === 'manager' ? 'Quản lý' : 'Nhân viên'); ?></span></td>
                            <td><?php echo $escape($staff['created_at']); ?></td>
                            <td style="color: #027a48; font-weight: 600;">✓ Hoạt động</td>
                            <td style="text-align: right;">
                                <span class="account-actions">
                                    <button type="button" class="btn-reset-pwd"
                                        onclick="openResetModal(<?php echo (int)$staff['id']; ?>, '<?php echo $escape($staff['username']); ?>')">
                                        🔑 Đổi mật khẩu
                                    </button>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_user_id" value="<?php echo (int)$staff['id']; ?>">
                                        <button type="submit" class="btn-delete-user" onclick="return confirm('Bạn có chắc muốn xóa tài khoản này không?');">🗑 Xóa</button>
                                    </form>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">Chưa có tài khoản.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </section>
    </section></main></div>

    <!-- Modal Đổi mật khẩu -->
    <div id="reset-password-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>🔑 Đổi mật khẩu nhân viên</h3>
                <button type="button" class="close-modal" onclick="closeResetModal()">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="target_user_id" id="modal-target-user-id" value="">

                <div class="modal-field">
                    <label>Tài khoản nhân viên:</label>
                    <input type="text" id="modal-target-username" readonly style="background: #f4f5f7; font-weight: bold; color: #0f2747;">
                </div>

                <div class="modal-field">
                    <label for="modal-new-password">Mật khẩu mới:</label>
                    <div style="position:relative; display:flex; align-items:center;">
                        <input type="password" name="new_password" id="modal-new-password" placeholder="Nhập mật khẩu mới (tối thiểu 8 ký tự)" minlength="8" required style="padding-right: 42px; width: 100%;">
                        <button type="button" class="pw-toggle-btn" onclick="togglePw('modal-new-password', this)" aria-label="Hiện/Ẩn mật khẩu" style="margin:0;" data-visible="0"></button>
                    </div>
                    <small style="color: #667085; font-size: 0.8rem;">* Nhập mật khẩu mới cho nhân viên. Nhấn biểu tượng mắt để xem trước.</small>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeResetModal()">Hủy</button>
                    <button type="submit" class="btn-submit-reset">Lưu mật khẩu mới</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openResetModal(userId, username) {
        document.getElementById('modal-target-user-id').value = userId;
        document.getElementById('modal-target-username').value = username;
        const pwdInput = document.getElementById('modal-new-password');
        pwdInput.value = '';
        document.getElementById('reset-password-modal').classList.add('active');
        pwdInput.focus();
    }

    function closeResetModal() {
        document.getElementById('reset-password-modal').classList.remove('active');
    }

    // Đóng modal khi bấm ra ngoài vùng box
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('reset-password-modal');
        if (e.target === modal) {
            closeResetModal();
        }
    });

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
        </section>
    </section></main></div>
</div>
</body>
</html>
