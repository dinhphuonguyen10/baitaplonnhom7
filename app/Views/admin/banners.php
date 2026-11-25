<?php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý banner - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .banner-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .banner-card { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 16px; }
        .banner-card h2 { margin: 0 0 12px; color: var(--navy); font-size: 16px; }
        .banner-preview { width: 100%; height: 210px; border-radius: 9px; object-fit: cover; background: #eef1f5; border: 1px solid #e3e8ef; }
        .banner-form { display: grid; gap: 10px; margin-top: 14px; }
        .banner-form input[type="text"], .banner-form input[type="file"] { width: 100%; padding: 10px 11px; border: 1px solid #d8dee7; border-radius: 7px; }
        .banner-actions { display: flex; align-items: flex-end; gap: 8px; margin-top: 2px; }
        .banner-actions form { margin: 0; }
        .banner-actions button {
            border: 0;
            border-radius: 7px;
            padding: 9px 14px;
            color: #fff;
            background: var(--navy);
            font-weight: 700;
            cursor: pointer;
            transition: transform .12s ease, box-shadow .12s ease, background .12s ease;
        }
        .banner-actions button:hover { background: #193b61; box-shadow: 0 4px 10px rgba(15,39,71,.18); }
        .banner-actions button:active { transform: translateY(2px) scale(.98); box-shadow: 0 1px 3px rgba(15,39,71,.22); }
        .banner-actions .reset-banner { background: #718096; }
        .banner-actions .reset-banner:hover { background: #596a7e; }
        .message { margin: 0 0 16px; padding: 12px 14px; border-radius: 8px; }
        .message.success { transition: opacity .35s ease, transform .35s ease; }
        .message.success.is-hiding { opacity: 0; transform: translateY(-6px); }
        .message.success { background: #edf8f1; color: #087a46; }
        .message.error { background: #fff0ef; color: #b42318; }
        @media (max-width: 900px) { .banner-grid { grid-template-columns: 1fr; } }

        /* ── Logo section ── */
        .logo-section { margin: 22px 0 24px; }
        .logo-section-title { margin: 0 0 14px; color: var(--navy); font-size: 26px !important; line-height: 1.25; font-weight: 700; }
        .logo-card {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 32px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 24px;
            align-items: center;
            box-shadow: 0 8px 24px rgba(15,39,71,.04);
        }
        .logo-preview-wrap { display: flex; flex-direction: column; align-items: center; }
        .logo-preview-box {
            width: 200px; height: 120px;
            display: flex; align-items: center; justify-content: center;
            border: 2px dashed #d0d9e4; border-radius: 10px;
            background: #f7f9fb; overflow: hidden;
        }
        .logo-preview-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .logo-upload-form { display: grid; gap: 16px; }
        .logo-upload-form label { font-size: 15px; color: var(--navy); font-weight: 600; }
        .logo-upload-form input[type="file"] { width: 100%; padding: 12px; border: 1px solid #d8dee7; border-radius: 9px; background: #fafbfc; }
        .logo-upload-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .logo-btn-upload {
            padding: 10px 20px; border: 0; border-radius: 8px;
            background: var(--navy); color: #fff; font-weight: 700;
            cursor: pointer; font-size: 13px;
            transition: background .15s, transform .12s;
        }
        .logo-btn-upload:hover { background: #193b61; }
        .logo-btn-reset {
            padding: 10px 16px; border: 0; border-radius: 8px;
            background: #f0f2f5; color: #546070; font-weight: 600;
            cursor: pointer; font-size: 13px;
            transition: background .15s;
        }
        .logo-btn-reset:hover { background: #e1e5ec; }
        .logo-preview-live { max-width: 160px; max-height: 90px; object-fit: contain; display: none; border-radius: 6px; }
        @media (max-width: 700px) { .logo-card { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý banner</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="logo-section">
                <h2 class="logo-section-title">Quản lý logo trang web</h2>

                <?php if ($logoMessage !== ''): ?>
                    <div class="message <?php echo $logoMessageType === 'success' ? 'success' : 'error'; ?>"><?php echo $escape($logoMessage); ?></div>
                <?php endif; ?>

                <div class="logo-card">
                    <!-- Cột trái: Preview logo hiện tại -->
                    <div class="logo-preview-wrap">
                        <div class="logo-preview-box">
                            <img id="logo-current-img"
                                 src="<?php echo $escape($appBaseUrl . '/' . ltrim($currentLogoSrc, '/')); ?>"
                                 alt="Logo hiện tại">
                        </div>
                    </div>

                    <!-- Cột phải: Form upload -->
                    <div>
                        <form class="logo-upload-form" method="post" enctype="multipart/form-data" id="logo-upload-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="upload_logo">

                            <label for="logo-file-input">Chọn file logo mới</label>
                            <input type="file" id="logo-file-input" name="logo_image"
                                   accept="image/jpeg,image/png,image/webp,image/svg+xml" required>

                            <!-- Preview trước khi upload -->
                            <div id="logo-preview-new-wrap" style="display:none;">
                                <div style="font-size:13px; color:#5a6a7e; font-weight:600; margin-bottom:6px;">Xem trước logo mới</div>
                                <div style="display:flex; align-items:center; justify-content:center; width:200px; height:100px; border:2px dashed #c5d0dc; border-radius:8px; background:#f7f9fb; overflow:hidden;">
                                    <img id="logo-preview-new" src="" alt="Preview" class="logo-preview-live" style="display:block;">
                                </div>
                            </div>

                            <div class="logo-upload-actions">
                                <button type="submit" class="logo-btn-upload">⬆ Cập nhật logo</button>
                                <?php if ($currentLogoSrc !== 'assets/banner/logo.png'): ?>
                                    <form method="post" style="margin:0">
                                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="reset_logo">
                                        <button type="submit" class="logo-btn-reset"
                                                onclick="return confirm('Khôi phục về logo mặc định?')">↺ Dùng logo mặc định</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="welcome-row">
                <div>
                    <h1>Quản lý banner</h1>
                </div>
            </div>
            <?php if ($message !== ''): ?>
                <div class="message <?php echo $messageType === 'success' ? 'success' : 'error'; ?>"><?php echo $escape($message); ?></div>
            <?php endif; ?>

            <div class="banner-grid">
                <?php foreach ($banners as $index => $banner): ?>
                    <?php $slot = $index + 1; ?>
                    <article class="banner-card">
                        <h2>Banner <?php echo $slot; ?></h2>
                        <img class="banner-preview"
                             src="<?php echo $escape($appBaseUrl . '/' . ltrim($banner['src'], '/')); ?>"
                             alt="<?php echo $escape($banner['alt']); ?>">
                        <form id="replace-banner-<?php echo $slot; ?>" class="banner-form" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                            <input type="hidden" name="action" value="replace">
                            <input type="hidden" name="slot" value="<?php echo $slot; ?>">
                            <label>Chọn ảnh mới (JPG, PNG, WebP)
                                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required>
                            </label>
                        </form>
                        <div class="banner-actions">
                            <form method="post" enctype="multipart/form-data">
                                <button type="submit" form="replace-banner-<?php echo $slot; ?>">Thay banner</button>
                            </form>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                <input type="hidden" name="action" value="reset">
                                <input type="hidden" name="slot" value="<?php echo $slot; ?>">
                                <button class="reset-banner" type="submit" onclick="return confirm('Khôi phục banner này về ảnh mặc định?')">Khôi phục mặc định</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </section>
    </main>
</div>
<script>
    document.querySelectorAll('.message.success').forEach(function (message) {
        window.setTimeout(function () {
            message.classList.add('is-hiding');
            window.setTimeout(function () { message.remove(); }, 350);
        }, 3000);
    });

    // Preview logo trước khi upload
    document.getElementById('logo-file-input').addEventListener('change', function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            document.getElementById('logo-preview-new').src = e.target.result;
            document.getElementById('logo-preview-new-wrap').style.display = 'block';
        };
        reader.readAsDataURL(file);
    });
</script>
</body>
</html>
