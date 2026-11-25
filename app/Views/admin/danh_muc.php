<?php
// View: trang quản trị danh mục. Dữ liệu do Admin\DanhMucController cung cấp
// ($categories đã kèm image_url / is_deleted_flag / is_active_flag, $categoriesCsrfToken).
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý danh mục - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .cat-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .cat-panel h2 { margin: 0 0 16px; font-size: 1.15rem; color: var(--navy); }
        .cat-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end; gap: 12px; }
        .cat-form label { display: grid; gap: 6px; font-weight: 600; font-size: 14px; color: var(--navy); }
        .cat-form input, .cat-form select, .cat-form textarea { width: 100%; padding: 10px 14px; border: 1px solid #cbd4df; border-radius: 8px; font: inherit; outline: none; }
        .cat-form input:focus, .cat-form select:focus, .cat-form textarea:focus { border-color: var(--navy); }
        .cat-form textarea { min-height: 42px; resize: vertical; }
        .cat-form .form-actions { display: flex; align-items: flex-end; }
        .cat-status { display: inline-flex; padding: 4px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .cat-status-active { background: #ecfdf3; color: #027a48; }
        .cat-status-inactive, .cat-status-deleted { background: #f2f4f7; color: #475467; }
        .cat-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; background: #f2f4f7; }
        .cat-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; min-width: 220px; }
        .cat-warning { color: #b54708; font-size: 12px; margin-top: 5px; }
        .btn-primary { padding: 10px 20px; border: 0; border-radius: 8px; background: var(--navy); color: #fff; font: 600 14px inherit; cursor: pointer; transition: .2s; }
        .btn-primary:hover { background: #1a3c68; }

        .cat-table { width: 100%; border-collapse: collapse; }
        .cat-table th, .cat-table td { padding: 14px; border-bottom: 1px solid var(--line); text-align: left; }
        .cat-table th { background: #fafbfc; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .cat-table tr:hover { background: #fafbfc; }
        .btn-sm { padding: 6px 14px; border: 0; border-radius: 6px; font: 600 13px inherit; cursor: pointer; transition: .2s; }
        .btn-edit { background: #e0f2fe; color: #026aa2; }
        .btn-edit:hover { background: #b9e6fe; }
        .btn-delete { background: #fee4e2; color: #b42318; margin-left: 6px; }
        .btn-delete:hover { background: #fecdca; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .alert-error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        /* Modal Sửa */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 39, 71, .5); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal.is-open { display: flex; }
        .modal-box { width: min(600px, 92%); background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.15); }
        .modal-box h3 { margin: 0 0 16px; color: var(--navy); font-size: 1.2rem; }
        .modal-box label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: var(--navy); }
        .modal-box input, .modal-box select, .modal-box textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd4df; border-radius: 8px; margin-bottom: 16px; font: inherit; }
        .modal-box textarea { min-height: 90px; resize: vertical; }
        .modal-box .actions { display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { padding: 9px 16px; border: 1px solid #cbd4df; border-radius: 8px; background: #fff; cursor: pointer; font: inherit; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý danh mục</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>

        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý danh mục</h1>
                    <p class="page-subtitle">Thêm, chỉnh sửa và quản lý danh mục sản phẩm ÉCRIN.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $escape($message); ?></div>
            <?php endif; ?>

            <section class="cat-panel">
                <h2>Thêm danh mục</h2>
                <form method="post" class="cat-form">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
                    <label>
                        Tên danh mục
                        <input type="text" name="name" maxlength="150" placeholder="Nhập tên danh mục mới..." required>
                    </label>
                    <label>
                        Mô tả
                        <textarea name="description" rows="2"></textarea>
                    </label>
                    <label>
                        Ảnh đại diện (URL hoặc đường dẫn trong assets)
                        <input type="text" name="image" maxlength="500" placeholder="assets/... hoặc https://...">
                    </label>
                    <label>
                        Trạng thái
                        <select name="status">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                    </label>
                    <div class="form-actions"><button type="submit" class="btn-primary">Lưu danh mục</button></div>
                </form>
            </section>

            <section class="cat-panel">
                <h2>Danh sách danh mục</h2>
                <div style="overflow-x:auto">
                    <table class="cat-table">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Ảnh</th>
                                <th>Tên / mô tả</th>
                                <th>Trạng thái</th>
                                <th>Sản phẩm</th>
                                <th>Thời điểm</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="7" style="text-align:center; color: var(--muted);">Chưa có danh mục nào.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td><strong><?php echo $escape($cat['code']); ?></strong></td>
                                    <td><img class="cat-thumb" src="<?php echo $escape($cat['image_url']); ?>" alt="" onerror="this.onerror=null;this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>';"></td>
                                    <td>
                                        <strong><?php echo $escape($cat['name']); ?></strong>
                                        <?php if (trim((string)$cat['description']) !== ''): ?>
                                            <div><?php echo nl2br($escape($cat['description'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['is_deleted_flag']): ?>
                                            <span class="cat-status cat-status-deleted">Đã xóa mềm</span>
                                        <?php elseif ($cat['is_active_flag']): ?>
                                            <span class="cat-status cat-status-active">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="cat-status cat-status-inactive">Ngừng hoạt động</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$cat['product_count']; ?></td>
                                    <td>
                                        <small>Tạo: <?php echo $escape($cat['created_at']); ?></small><br>
                                        <small>Cập nhật: <?php echo $escape($cat['updated_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="cat-actions">
                                        <?php if ($cat['is_deleted_flag']): ?>
                                            <form method="post" onsubmit="return confirm('Khôi phục danh mục này? Trạng thái hoạt động hiện tại sẽ được giữ nguyên.');">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
                                                <button type="submit" class="btn-sm btn-edit">Khôi phục</button>
                                            </form>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                class="btn-sm btn-edit"
                                                data-id="<?php echo (int)$cat['id']; ?>"
                                                data-name="<?php echo $escape($cat['name']); ?>"
                                                data-description="<?php echo $escape($cat['description']); ?>"
                                                data-image="<?php echo $escape($cat['image']); ?>"
                                                data-status="<?php echo $escape($cat['status']); ?>"
                                                onclick="openEditModal(this)">Sửa</button>
                                            <?php if ((int)$cat['product_count'] > 0): ?>
                                                <div class="cat-warning">
                                                    Còn <?php echo (int)$cat['product_count']; ?> sản phẩm. Hãy chuyển chúng sang danh mục khác trước.
                                                </div>
                                            <?php else: ?>
                                                <form method="post" onsubmit="return confirm('Xóa mềm danh mục này?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
                                                    <button type="submit" class="btn-sm btn-delete">Xóa mềm</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>

<!-- Modal Sửa danh mục -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <h3>Chỉnh sửa danh mục</h3>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
            <input type="hidden" name="id" id="editId">
            <label for="editName">Tên danh mục:</label>
            <input type="text" name="name" id="editName" maxlength="150" required>
            <label for="editDescription">Mô tả:</label>
            <textarea name="description" id="editDescription"></textarea>
            <label for="editImage">Ảnh đại diện (URL hoặc đường dẫn trong assets):</label>
            <input type="text" name="image" id="editImage" maxlength="500" placeholder="assets/... hoặc https://...">
            <label for="editStatus">Trạng thái:</label>
            <select name="status" id="editStatus">
                <option value="active">Hoạt động</option>
                <option value="inactive">Ngừng hoạt động</option>
            </select>
            <div class="actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(button) {
    document.getElementById('editId').value = button.dataset.id;
    document.getElementById('editName').value = button.dataset.name;
    document.getElementById('editDescription').value = button.dataset.description;
    document.getElementById('editImage').value = button.dataset.image;
    document.getElementById('editStatus').value = button.dataset.status;
    document.getElementById('editModal').classList.add('is-open');
    document.getElementById('editName').focus();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('is-open');
}

// Bấm ra ngoài modal để đóng
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
</body>
</html>
