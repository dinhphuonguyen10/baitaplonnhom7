<?php
// View: trang quản trị sản phẩm. Dữ liệu do Admin\ProductController cung cấp.
use App\Helpers\ProductAdminFormatter;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý sản phẩm - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .prod-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .prod-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .toolbar-left { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .toolbar-left input, .toolbar-left select { padding: 9px 14px; border: 1px solid #cbd4df; border-radius: 8px; font: inherit; outline: none; }
        .toolbar-left input:focus, .toolbar-left select:focus { border-color: var(--navy); }
        .toolbar-left input { min-width: 240px; }

        .btn-add { padding: 10px 18px; border: 0; border-radius: 8px; background: var(--navy); color: #fff; font: 600 14px inherit; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-add:hover { background: #1a3c68; }

        .prod-table { width: 100%; border-collapse: collapse; }
        .prod-table th, .prod-table td { padding: 13px 15px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        .prod-table th { background: #fafbfc; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .prod-table tr:hover { background: #fafbfc; }

        .thumb-img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid var(--line); background: #f7f9fb; display: block; }
        .prod-name-cell { min-width: 250px; }
        .prod-name { font-family: 'Roboto', sans-serif; font-size: 14.5px; font-weight: 600; color: var(--navy); line-height: 1.35; }
        .prod-desc-preview { font-family: 'Roboto', sans-serif; font-size: 12px; color: #64748b; line-height: 1.45; margin-top: 4px; max-width: 380px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
        .cat-badge { display: inline-block; padding: 4px 11px; background: #eef2f6; color: var(--navy); border-radius: 20px; font-size: 12px; font-weight: 600; font-family: 'Roboto', sans-serif; letter-spacing: 0.02em; }
        .price-text { font-family: 'Roboto', sans-serif; font-size: 14px; font-weight: 700; color: var(--navy); }
        .stock-tag { font-weight: 600; font-size: 13px; font-family: 'Roboto', sans-serif; }
        .stock-ok   { color: #027a48; }
        .stock-low  { color: #b54708; }
        .stock-out  { color: #b42318; }

        .btn-suggest-desc { background: #f8fafc; border: 1px solid #cbd5e1; color: var(--navy); font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 6px; cursor: pointer; transition: all .2s; }
        .btn-suggest-desc:hover { background: #eef2f6; border-color: var(--navy); color: #b58c54; }
        .desc-hints { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 4px; }
        .desc-hints small { color: var(--muted); font-size: 11px; }
        .desc-pill { background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 12px; cursor: pointer; transition: all .15s; }
        .desc-pill:hover { background: #e2e8f0; color: var(--navy); border-color: #cbd5e1; }

        .btn-sm { padding: 6px 14px; border: 0; border-radius: 6px; font: 600 13px inherit; cursor: pointer; transition: .2s; }
        .btn-edit { background: #e0f2fe; color: #026aa2; }
        .btn-edit:hover { background: #b9e6fe; }
        .btn-delete { background: #fee4e2; color: #b42318; margin-left: 6px; }
        .btn-delete:hover { background: #fecdca; }
        .btn-restore { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .btn-restore:hover { background: #d1fadf; }

        /* Tabs chuyển đổi Active / Trash */
        .admin-nav-tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .admin-nav-tab { padding: 10px 20px; border-radius: 8px 8px 0 0; text-decoration: none; font-weight: 600; font-size: 14px; color: #64748b; background: transparent; transition: all .2s; display: inline-flex; align-items: center; gap: 8px; margin-bottom: -2px; }
        .admin-nav-tab:hover { color: var(--navy); background: #f8fafc; }
        .admin-nav-tab.active { color: var(--navy); border-bottom: 3px solid var(--navy); background: #fff; }
        .tab-count { font-size: 11px; padding: 2px 8px; border-radius: 12px; background: #e2e8f0; color: #475467; font-weight: 700; }
        .admin-nav-tab.active .tab-count { background: var(--navy); color: #fff; }

        /* Badge trạng thái sản phẩm */
        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.02em; }
        .status-active   { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .status-inactive { background: #fffaeb; color: #b54708; border: 1px solid #fedf89; }
        .status-hidden   { background: #f2f4f7; color: #475467; border: 1px solid #eaecf0; }
        .status-deleted  { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .alert-error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        /* Modal popup */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 39, 71, .55); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal.is-open { display: flex; }
        .modal-box { width: min(540px, 94%); max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 14px; padding: 26px; box-shadow: 0 16px 40px rgba(0,0,0,.2); }
        .modal-box h3 { margin: 0 0 18px; color: var(--navy); font-size: 1.25rem; border-bottom: 1px solid var(--line); padding-bottom: 10px; }
        .form-grid { display: grid; gap: 14px; }
        .form-field { display: grid; gap: 6px; }
        .form-field label { font-weight: 600; font-size: 13px; color: var(--navy); }
        .form-field input, .form-field select, .form-field textarea { padding: 9px 12px; border: 1px solid #cbd4df; border-radius: 8px; font: inherit; outline: none; }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color: var(--navy); }
        .form-field textarea { resize: vertical; min-height: 70px; line-height: 1.5; }
        .form-field small { color: var(--muted); font-size: 11px; }

        /* Khung nhập thêm hàng */
        .stock-adjust-box { background: #f4f7fb; border: 1px dashed #b8c7db; border-radius: 8px; padding: 12px; margin-top: 4px; }
        .stock-adjust-box strong { color: var(--navy); font-size: 13px; }
        .stock-calc-preview { font-size: 12px; color: #026aa2; margin-top: 4px; font-weight: 600; }

        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--line); }
        .btn-cancel { padding: 9px 16px; border: 1px solid #cbd4df; border-radius: 8px; background: #fff; cursor: pointer; font: inherit; }
        .current-img-preview { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý sản phẩm</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>

        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý sản phẩm</h1>
                    <p class="page-subtitle">Hệ thống quản lý sản phẩm thương mại điện tử ÉCRIN Jewelry.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $escape($message); ?></div>
            <?php endif; ?>

            <!-- TABS CHUYỂN ĐỔI: SẢN PHẨM HOẠT ĐỘNG / THÙNG RÁC -->
            <div class="admin-nav-tabs">
                <a href="products.php?view=active" class="admin-nav-tab <?php echo $currentView === 'active' ? 'active' : ''; ?>">
                    <span>🛍️ Danh sách sản phẩm</span>
                    <span class="tab-count"><?php echo $countActive; ?></span>
                </a>
                <a href="products.php?view=trash" class="admin-nav-tab <?php echo $currentView === 'trash' ? 'active' : ''; ?>">
                    <span>🗑️ Thùng rác (Đã xóa mềm)</span>
                    <span class="tab-count" style="<?php echo $countTrash > 0 ? 'background: #fee2e2; color: #dc2626;' : ''; ?>"><?php echo $countTrash; ?></span>
                </a>
            </div>

            <section class="prod-panel">
                <!-- Thanh công cụ tìm kiếm và lọc -->
                <div class="prod-toolbar">
                    <div class="toolbar-left">
                        <input type="search" id="search-input" placeholder="<?php echo $currentView === 'trash' ? 'Tìm trong thùng rác...' : 'Tìm sản phẩm...'; ?>">
                        <select id="category-filter">
                            <option value="">Tất cả danh mục</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"><?php echo $escape($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($currentView === 'active'): ?>
                            <select id="status-filter">
                                <option value="">Tất cả trạng thái</option>
                                <option value="active">🟢 Đang kinh doanh</option>
                                <option value="inactive">🟠 Ngừng kinh doanh</option>
                                <option value="hidden">⚪ Bị ẩn</option>
                                <option value="out_of_stock">🔴 Hết hàng (Tồn = 0)</option>
                            </select>
                        <?php endif; ?>
                    </div>
                    <?php if ($currentView === 'active'): ?>
                        <button type="button" class="btn-add" onclick="openAddModal()">+ Thêm sản phẩm</button>
                    <?php else: ?>
                        <span style="font-size: 13px; color: #64748b; font-weight: 500;">💡 Sản phẩm đã xóa mềm vẫn bảo toàn lịch sử đơn hàng &amp; báo cáo.</span>
                    <?php endif; ?>
                </div>

                <!-- Bảng danh sách sản phẩm -->
                <div style="overflow-x:auto">
                    <table class="prod-table" id="products-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Giá bán</th>
                                <th>Trạng thái &amp; Kho</th>
                                <?php if ($currentView === 'trash'): ?>
                                    <th>Thông tin xóa</th>
                                <?php endif; ?>
                                <th style="width: <?php echo $currentView === 'trash' ? '200px' : '160px'; ?>; text-align: center;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr id="empty-row"><td colspan="<?php echo $currentView === 'trash' ? '7' : '6'; ?>" style="text-align:center; color: var(--muted); padding: 30px;">
                                    <?php echo $currentView === 'trash' ? 'Thùng rác hiện đang trống. Chưa có sản phẩm nào bị xóa mềm.' : 'Chưa có sản phẩm nào.'; ?>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <?php
                                        $stockNum = (int)$p['stock'];
                                        [$stockText, $stockClass] = ProductAdminFormatter::stock($stockNum);
                                        $prodStatus = $p['status'] ?? 'active';
                                        [$statusText, $statusClass] = ProductAdminFormatter::status((string) $prodStatus);
                                        $imgSrc = ProductAdminFormatter::imageUrl((string) $p['image'], $appBaseUrl);
                                    ?>
                                    <tr class="product-row"
                                        data-name="<?php echo strtolower($escape($p['name'])); ?>"
                                        data-sku="<?php echo strtolower($escape($p['sku'])); ?>"
                                        data-cat-id="<?php echo (int)$p['category_id']; ?>"
                                        data-status="<?php echo $escape($prodStatus); ?>"
                                        data-stock="<?php echo $stockNum; ?>">
                                        <td>
                                            <img src="<?php echo $escape($imgSrc); ?>" alt="" class="thumb-img" onerror="this.onerror=null;this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>'">
                                        </td>
                                        <td class="prod-name-cell">
                                            <div class="prod-name"><?php echo $escape($p['name']); ?></div>
                                            <small style="color: #64748b;">SKU: <?php echo $escape($p['sku']); ?></small>
                                            <div style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">
                                                <?php if (!empty($p['is_featured'])): ?>
                                                    <span style="display: inline-block; font-size: 11px; padding: 2px 7px; border-radius: 4px; background: #fef08a; color: #854d0e; font-weight: 600;">⭐ Nổi bật</span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['is_suggested'])): ?>
                                                    <span style="display: inline-block; font-size: 11px; padding: 2px 7px; border-radius: 4px; background: #e0e7ff; color: #3730a3; font-weight: 600;">💡 Gợi ý</span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['is_new'])): ?>
                                                    <span style="display: inline-block; font-size: 11px; padding: 2px 7px; border-radius: 4px; background: #dcfce7; color: #166534; font-weight: 600;">🆕 Mới</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($p['description'])): ?>
                                                <div class="prod-desc-preview" title="<?php echo $escape($p['description']); ?>"><?php echo $escape($p['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="cat-badge"><?php echo $escape($p['category_name']); ?></span></td>
                                        <td>
                                            <strong class="price-text"><?php echo number_format((float)$p['price_sale'], 0, ',', '.'); ?>đ</strong>
                                            <?php if (!empty($p['price_original']) && (float)$p['price_original'] > (float)$p['price_sale']): ?>
                                                <div style="font-size: 11px; color: #94a3b8; text-decoration: line-through;">
                                                    <?php echo number_format((float)$p['price_original'], 0, ',', '.'); ?>đ
                                                </div>
                                                <span style="display: inline-block; font-size: 11px; padding: 1px 5px; border-radius: 4px; background: #fee2e2; color: #dc2626; font-weight: 700;">
                                                    <?php echo $escape($p['discount'] ?? ''); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                                <?php if ((int)$p['is_deleted'] === 1): ?>
                                                    <span class="status-badge status-deleted">Đã xóa mềm</span>
                                                <?php else: ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $escape($statusText); ?></span>
                                                <?php endif; ?>
                                                <span class="stock-tag <?php echo $stockClass; ?>"><?php echo $escape($stockText); ?></span>
                                            </div>
                                            <?php if ((int)($p['sold_count'] ?? 0) > 0): ?>
                                                <div style="font-size: 0.8rem; color: #e67e22; font-weight: 500; margin-top: 4px;">Đã bán: <?php echo (int)$p['sold_count']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($currentView === 'trash'): ?>
                                            <td>
                                                <div style="font-size: 12px; color: #64748b;">
                                                    <div><strong>Ngày xóa:</strong> <?php echo !empty($p['deleted_at']) ? date('d/m/Y H:i', strtotime($p['deleted_at'])) : 'N/A'; ?></div>
                                                    <div><strong>Bởi:</strong> <?php echo $escape($p['deleted_by'] ?? 'Admin'); ?></div>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td style="text-align: center;">
                                            <?php if ($currentView === 'trash'): ?>
                                                <!-- THAO TÁC TRONG THÙNG RÁC: KHÔI PHỤC -->
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Khôi phục sản phẩm này về danh sách hoạt động?');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($productsCsrfToken); ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-restore" title="Khôi phục sản phẩm">↺ Khôi phục</button>
                                                </form>
                                            <?php else: ?>
                                                <!-- THAO TÁC ĐANG HOẠT ĐỘNG: SỬA HOẶC XÓA MỀM -->
                                                <button type="button" class="btn-sm btn-edit"
                                                    onclick='openEditModal(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, "<?php echo $escape($imgSrc); ?>")'>
                                                    Sửa
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Chuyển sản phẩm này vào Thùng rác (xóa mềm)?\n\nSản phẩm sẽ ẩn khỏi Client nhưng vẫn bảo toàn lịch sử đơn hàng và báo cáo.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($productsCsrfToken); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-delete" title="Xóa mềm vào thùng rác">Xóa</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr id="no-search-results" style="display: none;">
                                <td colspan="<?php echo $currentView === 'trash' ? '7' : '6'; ?>" style="text-align:center; color: var(--muted); padding: 30px;">Không tìm thấy sản phẩm phù hợp.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>

<!-- MODAL THÊM SẢN PHẨM -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <h3>+ Thêm sản phẩm mới</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($productsCsrfToken); ?>">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-field">
                    <label>Tên sản phẩm (*):</label>
                    <input type="text" name="name" placeholder="Ví dụ: Bông tai ÉCRIN ánh vàng" required>
                </div>

                <div class="form-field">
                    <label>Mã sản phẩm / SKU (*):</label>
                    <input type="text" name="sku" maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" placeholder="Ví dụ: ER-BT-001" required>
                </div>

                <div class="form-field">
                    <label>Danh mục sản phẩm (*):</label>
                    <select name="category_id" id="add-category" onchange="onAddCategoryChange(this.value)" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo $escape($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label>Giá gốc (VNĐ) (*):</label>
                    <input type="number" name="regular_price" min="0" max="9999999999.99" step="0.01" required>
                    <label for="add-promotion-price">Giá khuyến mãi (để trống nếu không có):</label>
                    <input type="number" name="promotion_price" id="add-promotion-price" min="0" max="9999999999.99" step="0.01">
                    <label for="add-promotion-ends-at">Kết thúc khuyến mãi (không bắt buộc):</label>
                    <input type="datetime-local" name="promotion_ends_at" id="add-promotion-ends-at">
                    <small>Giá khuyến mãi phải thấp hơn giá gốc. Hết thời hạn, giá hiển thị tự trở về giá gốc.</small>
                </div>

                <div class="form-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <label for="add-description">Mô tả chi tiết sản phẩm:</label>
                        <button type="button" class="btn-suggest-desc" onclick="generateSampleDescription('add')">✨ Điền mô tả mẫu cuốn hút</button>
                    </div>
                    <textarea name="description" id="add-description" rows="4" placeholder="Nhập mô tả sản phẩm...">Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.</textarea>
                    <div class="desc-hints">
                        <small>💡 Gợi ý theo danh mục:</small>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 2)">Bông tai</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 3)">Dây chuyền</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 4)">Nhẫn</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 5)">Vòng tay</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 6)">Set quà tặng</button>
                    </div>
                </div>

                <div class="form-field">
                    <label>Số lượng tồn kho ban đầu (*):</label>
                    <input type="number" name="stock" value="10" min="0" required>
                </div>

                <div class="form-field">
                    <label>Trạng thái kinh doanh (*):</label>
                    <select name="status" id="add-status" required>
                        <option value="active" selected>🟢 Đang kinh doanh (Hiển thị và cho phép đặt mua)</option>
                        <option value="inactive">🟠 Ngừng kinh doanh (Không cho đặt mua, giữ lịch sử)</option>
                        <option value="hidden">⚪ Bị ẩn (Ẩn khỏi toàn bộ giao diện Client)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Phân loại hiển thị trang chủ:</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 4px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_featured" value="1">
                            <span>⭐ Sản phẩm nổi bật</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_suggested" value="1">
                            <span>💡 Gợi ý cho bạn</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_new" value="1" checked>
                            <span>🆕 Sản phẩm mới</span>
                        </label>
                    </div>
                </div>

                <div class="form-field">
                    <label>Ảnh sản phẩm:</label>
                    <input type="file" name="image_file" accept="image/*">
                    <small>Hoặc nhập link ảnh trực tiếp:</small>
                    <input type="text" name="image_url" placeholder="assets/bôngtai/bong1.jpg hoặc link URL">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Hủy</button>
                <button type="submit" class="btn-add">Thêm sản phẩm</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SỬA SẢN PHẨM -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <h3>Chỉnh sửa sản phẩm</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($productsCsrfToken); ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <div class="form-grid">
                <div class="form-field">
                    <label>Tên sản phẩm (*):</label>
                    <input type="text" name="name" id="edit-name" required>
                </div>

                <div class="form-field">
                    <label>Mã sản phẩm / SKU (*):</label>
                    <input type="text" name="sku" id="edit-sku" maxlength="100" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" required>
                </div>

                <div class="form-field">
                    <label>Danh mục (*):</label>
                    <select name="category_id" id="edit-category" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo $escape($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label>Giá gốc (VNĐ) (*):</label>
                    <input type="number" name="regular_price" id="edit-regular-price" min="0" max="9999999999.99" step="0.01" required>
                    <label for="edit-promotion-price">Giá khuyến mãi (để trống nếu không có):</label>
                    <input type="number" name="promotion_price" id="edit-promotion-price" min="0" max="9999999999.99" step="0.01">
                    <label for="edit-promotion-ends-at">Kết thúc khuyến mãi (không bắt buộc):</label>
                    <input type="datetime-local" name="promotion_ends_at" id="edit-promotion-ends-at">
                    <div style="margin-top: 8px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                            <label style="font-size: 13px; font-weight: 600; color: #334155;">Áp dụng % giảm giá nhanh:</label>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(5)">5%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(10)">10%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(15)">15%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(20)">20%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(30)">30%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(50)">50%</button>
                                <button type="button" class="btn-sm" style="background: #fee2e2; color: #b91c1c; padding: 2px 8px;" onclick="removeDiscount()">Hủy giảm giá</button>
                            </div>
                        </div>
                        <div id="price-calc-hint" style="font-size: 12px; color: #0284c7; margin-top: 6px; font-weight: 500;"></div>
                    </div>
                </div>

                <div class="form-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <label for="edit-description">Mô tả chi tiết sản phẩm:</label>
                        <button type="button" class="btn-suggest-desc" onclick="generateSampleDescription('edit')">✨ Gợi ý mô tả cuốn hút</button>
                    </div>
                    <textarea name="description" id="edit-description" rows="4" placeholder="Nhập mô tả sản phẩm, chất liệu đá/bạc..."></textarea>
                    <div class="desc-hints">
                        <small>💡 Gợi ý theo danh mục:</small>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 2)">Bông tai</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 3)">Dây chuyền</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 4)">Nhẫn</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 5)">Vòng tay</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 6)">Set quà tặng</button>
                    </div>
                </div>

                <!-- KHU VỰC QUẢN LÝ SỐ LƯỢNG / NHẬP THÊM HÀNG -->
                <div class="form-field">
                    <label>Số lượng tồn kho:</label>
                    <input type="number" name="stock" id="edit-stock" min="0" required>
                    
                    <div class="stock-adjust-box">
                        <strong>📦 Nhập thêm hàng về kho:</strong>
                        <div style="display: flex; gap: 8px; margin-top: 6px; align-items: center;">
                            <input type="number" id="add-stock-input" placeholder="Số lượng nhập thêm (VD: 5)" min="0" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd4df; border-radius: 6px;">
                            <button type="button" class="btn-sm btn-edit" style="white-space: nowrap;" onclick="applyStockAdd()">+ Cộng kho</button>
                        </div>
                        <div class="stock-calc-preview" id="stock-calc-hint"></div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Trạng thái kinh doanh (*):</label>
                    <select name="status" id="edit-status" required>
                        <option value="active">🟢 Đang kinh doanh (Hiển thị và cho phép đặt mua)</option>
                        <option value="inactive">🟠 Ngừng kinh doanh (Không cho đặt mua, giữ lịch sử)</option>
                        <option value="hidden">⚪ Bị ẩn (Ẩn khỏi toàn bộ giao diện Client)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Phân loại hiển thị trang chủ:</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 4px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_featured" id="edit-is-featured" value="1">
                            <span>⭐ Sản phẩm nổi bật</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_suggested" id="edit-is-suggested" value="1">
                            <span>💡 Gợi ý cho bạn</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_new" id="edit-is-new" value="1">
                            <span>🆕 Sản phẩm mới</span>
                        </label>
                    </div>
                </div>

                <div class="form-field">
                    <label>Ảnh sản phẩm hiện tại:</label>
                    <div><img id="edit-img-preview" src="<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>" alt="" class="current-img-preview"></div>
                    <label>Đổi ảnh mới (nếu muốn):</label>
                    <input type="file" name="image_file" accept="image/*">
                    <input type="text" name="image_url_new" id="edit-img-url" placeholder="Hoặc nhập đường dẫn ảnh mới..." style="margin-top: 6px;">
                    <label style="display: flex; align-items: center; gap: 6px; margin-top: 8px;">
                        <input type="checkbox" name="remove_image" id="edit-remove-image" value="1">
                        <span>Gỡ ảnh khỏi sản phẩm, dùng ảnh mặc định</span>
                    </label>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn-add">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
const productImageFallbackUrl = <?php echo json_encode($appBaseUrl . '/assets/banner/logo.png', JSON_UNESCAPED_SLASHES); ?>;

// Mẫu mô tả cuốn hút theo từng danh mục
const descTemplates = {
    2: 'Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.',
    3: 'Mặt dây chuyền được chạm khắc tỉ mỉ với đường nét thanh mảnh, uốn lượn mềm mại ôm trọn viên đá chủ lấp lánh như viên ngọc quý giữa lòng đại dương. Sợi dây bạc Ý 925 mạ rhodium cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ, biểu trưng cho tình yêu vĩnh cửu và phong thái kiêu sa, đài các khó phai.',
    4: 'Thiết kế nhẫn solitaire kinh điển kết hợp kỹ thuật nạm đá tinh xảo bậc nhất, nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut phản chiếu ánh hào quang lộng lẫy tựa kim cương tự nhiên. Sự lựa chọn hoàn mỹ biểu trưng cho tình yêu đích thực và khẳng định phong cách vương giả, độc bản của quý cô hiện đại.',
    5: 'Chiếc vòng tay ôm khít cổ tay yêu kiều, chế tác công phu từ chất liệu Bạc Ý 925 đúc nguyên khối dày dặn và các hạt charm đính đá vi mô sáng trong vắt. Thiết kế thanh lịch, chống oxy hóa bền lâu, mang lại nguồn năng lượng an lành, duyên dáng và phong thái thượng lưu trong từng cử chỉ tay.',
    6: 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.'
};

function fillCategoryDesc(modalType, catId) {
    const textareaId = modalType === 'add' ? 'add-description' : 'edit-description';
    const textarea = document.getElementById(textareaId);
    if (textarea && descTemplates[catId]) {
        textarea.value = descTemplates[catId];
        textarea.focus();
    }
}

function generateSampleDescription(modalType) {
    const catSelectId = modalType === 'add' ? 'add-category' : 'edit-category';
    const catId = parseInt(document.getElementById(catSelectId).value, 10) || 2;
    fillCategoryDesc(modalType, catId);
}

function onAddCategoryChange(catId) {
    const numId = parseInt(catId, 10);
    const textarea = document.getElementById('add-description');
    if (textarea && (!textarea.value.trim() || Object.values(descTemplates).includes(textarea.value.trim()))) {
        if (descTemplates[numId]) {
            textarea.value = descTemplates[numId];
        }
    }
}

// Modal Thêm sản phẩm
function openAddModal() {
    document.getElementById('addModal').classList.add('is-open');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('is-open');
}

// Modal Sửa sản phẩm
let originalStock = 0;
let basePriceForDiscount = 0;
let currentEditImageSrc = productImageFallbackUrl;

function setEditImagePreview(src) {
    const imagePreview = document.getElementById('edit-img-preview');
    imagePreview.onerror = () => {
        imagePreview.onerror = null;
        imagePreview.src = productImageFallbackUrl;
    };
    imagePreview.src = src || productImageFallbackUrl;
}

function openEditModal(prod, imgSrc) {
    document.getElementById('edit-id').value          = prod.id;
    document.getElementById('edit-name').value        = prod.name;
    document.getElementById('edit-sku').value         = prod.sku || '';
    document.getElementById('edit-category').value    = prod.category_id;
    document.getElementById('edit-regular-price').value = prod.price_original;
    document.getElementById('edit-stock').value       = prod.stock;
    document.getElementById('edit-status').value      = prod.status || 'active';
    document.getElementById('edit-description').value = prod.description || descTemplates[prod.category_id] || '';
    document.getElementById('edit-is-featured').checked  = parseInt(prod.is_featured, 10) === 1;
    document.getElementById('edit-is-suggested').checked = parseInt(prod.is_suggested, 10) === 1;
    document.getElementById('edit-is-new').checked       = parseInt(prod.is_new, 10) === 1;
    originalStock = parseInt(prod.stock, 10) || 0;

    const origPrice = parseFloat(prod.price_original) || 0;
    const promotionPrice = prod.promotion_price !== null ? parseFloat(prod.promotion_price) : NaN;
    basePriceForDiscount = origPrice;
    document.getElementById('edit-promotion-price').value = Number.isFinite(promotionPrice) ? promotionPrice : '';
    document.getElementById('edit-promotion-ends-at').value = Number.isFinite(promotionPrice) && prod.promotion_ends_at
        ? String(prod.promotion_ends_at).replace(' ', 'T').slice(0, 16)
        : '';
    updatePriceCalcHint();

    // Reset ô nhập thêm hàng
    const addStockInput = document.getElementById('add-stock-input');
    addStockInput.value = '';
    document.getElementById('stock-calc-hint').textContent = 'Hiện tại đang có ' + originalStock + ' sản phẩm trong kho.';

    currentEditImageSrc = imgSrc || productImageFallbackUrl;
    document.getElementById('edit-remove-image').checked = false;
    setEditImagePreview(currentEditImageSrc);
    document.getElementById('edit-img-url').value   = '';
    document.getElementById('editModal').classList.add('is-open');
}

function updatePriceCalcHint() {
    const hint = document.getElementById('price-calc-hint');
    const regular = parseFloat(document.getElementById('edit-regular-price').value) || 0;
    const promotionInput = document.getElementById('edit-promotion-price').value;
    const promotion = parseFloat(promotionInput);
    if (promotionInput !== '' && Number.isFinite(promotion) && promotion >= 0 && promotion < regular) {
        const pct = Math.round((1 - (promotion / regular)) * 100);
        hint.innerHTML = '🏷️ Giá gốc: <strong>' + regular.toLocaleString('vi-VN') + 'đ</strong> ➡ Giá khuyến mãi: <strong>' + promotion.toLocaleString('vi-VN') + 'đ</strong> (Giảm <strong>-' + pct + '%</strong>)';
    } else {
        hint.innerHTML = '💡 Sản phẩm đang bán với giá thường (không giảm giá).';
    }
}

function applyDiscountPercent(pct) {
    if (basePriceForDiscount <= 0) return;
    const discountedPrice = Math.round(basePriceForDiscount * (1 - pct / 100) * 100) / 100;
    document.getElementById('edit-promotion-price').value = discountedPrice;
    updatePriceCalcHint();
}

function removeDiscount() {
    document.getElementById('edit-promotion-price').value = '';
    document.getElementById('edit-promotion-ends-at').value = '';
    updatePriceCalcHint();
}

document.getElementById('edit-regular-price')?.addEventListener('input', function() {
    basePriceForDiscount = parseFloat(this.value) || 0;
    updatePriceCalcHint();
});
document.getElementById('edit-promotion-price')?.addEventListener('input', function() {
    updatePriceCalcHint();
});

function closeEditModal() {
    document.getElementById('editModal').classList.remove('is-open');
}

document.getElementById('edit-remove-image').addEventListener('change', function() {
    setEditImagePreview(this.checked ? productImageFallbackUrl : currentEditImageSrc);
});
document.querySelector('#editModal input[name="image_file"]').addEventListener('change', function() {
    if (this.files.length > 0) {
        document.getElementById('edit-remove-image').checked = false;
    }
});
document.getElementById('edit-img-url').addEventListener('input', function() {
    if (this.value.trim() !== '') {
        document.getElementById('edit-remove-image').checked = false;
    }
});

// Tính năng tự động cộng kho khi nhập thêm hàng
document.getElementById('add-stock-input').addEventListener('input', function() {
    const addVal = parseInt(this.value, 10);
    const hint = document.getElementById('stock-calc-hint');
    if (!isNaN(addVal) && addVal > 0) {
        const newTotal = originalStock + addVal;
        hint.textContent = '➡ Kho hiện tại ' + originalStock + ' + nhập thêm ' + addVal + ' = ' + newTotal + ' sản phẩm.';
    } else {
        hint.textContent = 'Hiện tại đang có ' + originalStock + ' sản phẩm trong kho.';
    }
});

function applyStockAdd() {
    const addVal = parseInt(document.getElementById('add-stock-input').value, 10);
    if (!isNaN(addVal) && addVal > 0) {
        const newTotal = originalStock + addVal;
        document.getElementById('edit-stock').value = newTotal;
        originalStock = newTotal;
        document.getElementById('add-stock-input').value = '';
        document.getElementById('stock-calc-hint').textContent = '✔ Đã cập nhật tồn kho thành: ' + newTotal;
    }
}

// Đóng modal khi bấm ra ngoài
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('is-open');
    }
});

// TÍNH NĂNG TÌM KIẾM & LỌC DANH MỤC / TRẠNG THÁI REALTIME
const searchInput = document.getElementById('search-input');
const categoryFilter = document.getElementById('category-filter');
const statusFilter = document.getElementById('status-filter');
const rows = document.querySelectorAll('.product-row');
const noResults = document.getElementById('no-search-results');

function filterProducts() {
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const selectedCat = categoryFilter ? categoryFilter.value : '';
    const selectedStatus = statusFilter ? statusFilter.value : '';
    let visibleCount = 0;

    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const sku = row.getAttribute('data-sku') || '';
        const catId = row.getAttribute('data-cat-id') || '';
        const status = row.getAttribute('data-status') || '';
        const stock = parseInt(row.getAttribute('data-stock'), 10) || 0;

        const matchName = query === '' || name.includes(query) || sku.includes(query);
        const matchCat = selectedCat === '' || catId === selectedCat;

        let matchStatus = true;
        if (selectedStatus === 'out_of_stock') {
            matchStatus = stock <= 0;
        } else if (selectedStatus !== '') {
            matchStatus = status === selectedStatus;
        }

        if (matchName && matchCat && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 && rows.length > 0 ? '' : 'none';
    }
}

if (searchInput) searchInput.addEventListener('input', filterProducts);
if (categoryFilter) categoryFilter.addEventListener('change', filterProducts);
if (statusFilter) statusFilter.addEventListener('change', filterProducts);
</script>
</body>
</html>
