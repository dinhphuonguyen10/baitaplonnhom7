<?php

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý hệ thống cửa hàng - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .page-content { padding-top: 18px; }
        .welcome-row { display: none; }
        /* Tab navigation */
        .store-tabs { display: flex; gap: 8px; margin: 0 0 18px; border-bottom: 2px solid var(--line); }
        .store-tab { padding: 11px 18px; border: 0; border-bottom: 3px solid transparent; margin-bottom: -2px; background: none; color: #526173; font: 700 14px inherit; cursor: pointer; transition: .2s; }
        .store-tab:hover { color: #102a43; background: #f4f6f8; }
        .store-tab.active { border-bottom-color: #102a43; color: #102a43; background: #f4f6f8; }

        /* Store preview (client view) */
        .store-preview-wrap { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: #fff; }
        .store-container-admin { display: grid; grid-template-columns: 320px 1fr; min-height: 560px; }
        .store-sidebar-admin { border-right: 1px solid var(--line); padding: 20px; display: flex; flex-direction: column; gap: 12px; }
        .store-sidebar-admin h2 { margin: 0 0 10px; color: var(--navy); font-size: 1.1rem; }
        .store-search-admin input { width: 100%; padding: 9px 12px; border: 1px solid #d5dbe2; border-radius: 8px; font: inherit; }
        .store-list-admin { flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 8px; max-height: 440px; overscroll-behavior: contain; scrollbar-width: thin; scrollbar-color: #b7c3d0 #f1f4f7; }
        .store-list-admin::-webkit-scrollbar { width: 8px; }
        .store-list-admin::-webkit-scrollbar-track { border-radius: 8px; background: #f1f4f7; }
        .store-list-admin::-webkit-scrollbar-thumb { border-radius: 8px; background: #b7c3d0; }
        .store-preview-pagination { display: flex; justify-content: center; align-items: center; gap: 6px; padding-top: 4px; }
        .store-preview-pagination[hidden] { display: none; }
        .store-preview-pagination button { min-width: 32px; height: 32px; padding: 0 8px; border: 1px solid var(--line); border-radius: 8px; background: #fff; color: var(--navy); font-weight: 700; cursor: pointer; }
        .store-preview-pagination button:hover:not(:disabled), .store-preview-pagination button.is-active { border-color: var(--navy); background: var(--navy); color: #fff; }
        .store-preview-pagination button:disabled { cursor: not-allowed; opacity: .45; }
        .store-btn { display: block; width: 100%; padding: 13px 15px; border: 1px solid var(--line); border-radius: 10px; background: #fff; color: inherit; cursor: pointer; text-align: left; font: inherit; transition: .2s; }
        .store-btn[hidden] { display: none !important; }
        .store-btn:hover, .store-btn.active { border-color: #B09060; background: #FBF8F3; }
        .store-btn h3 { margin: 0 0 4px; color: var(--navy); font-size: 13px; }
        .store-btn p  { margin: 0; color: var(--muted); font-size: 12px; line-height: 1.4; }
        .store-empty-msg { color: var(--muted); text-align: center; font-size: 13px; display: none; }
        .store-map-col { display: flex; flex-direction: column; }
        .store-map-frame iframe { display: block; width: 100%; height: 380px; border: 0; }
        .store-detail-info { padding: 18px 22px; border-top: 1px solid var(--line); background: #FAFBFC; }
        .store-detail-info h2 { margin: 0 0 12px; color: #B09060; font-size: 1.1rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 14px; }
        .info-box h3 { margin: 0 0 4px; color: var(--muted); font-size: 10px; text-transform: uppercase; letter-spacing: 1px; }
        .info-box p  { margin: 0; color: var(--navy); font-weight: 600; font-size: 13px; }

        /* Management table */
        .mgmt-panel { background: #fff; border: 1px solid var(--line); border-radius: 12px; padding: 18px; }
        .mgmt-panel h2 { margin: 0 0 16px; font-size: 1.1rem; color: var(--navy); }
        .store-form-title { margin: 18px 0 12px; color: var(--navy); font-size: 15px; }
        .mgmt-actions { display: flex; gap: 10px; margin-bottom: 16px; }
        .mgmt-actions button { padding: 9px 16px; border: 0; border-radius: 7px; background: var(--navy); color: #fff; font: 600 13px inherit; cursor: pointer; }
        .store-table-search { width: min(100%, 420px); margin: 0 0 12px; }
        .store-table-search label { display: block; margin-bottom: 6px; color: #526173; font-size: 12px; font-weight: 700; }
        .store-table-search input { width: 100%; height: 42px; padding: 0 12px; border: 1px solid #d5dbe2; border-radius: 8px; font: inherit; }
        .store-table-scroll { max-height: 520px; overflow: auto; overscroll-behavior: contain; border-bottom: 1px solid var(--line); }
        .mgmt-table { width: 100%; border-collapse: collapse; }
        .mgmt-table th, .mgmt-table td { padding: 11px 14px; border-bottom: 1px solid var(--line); text-align: left; font-size: 13px; }
        .mgmt-table th { color: #34495e; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; background: #eef2f6; font-weight: 800; }
        .mgmt-table thead th { position: sticky; top: 0; z-index: 1; }
        .mgmt-table tbody tr[hidden] { display: none !important; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .status-active { background: #EDF6F1; color: #027a48; }
        .mgmt-table button { padding: 7px 13px; border: 1px solid #9aa8b8; border-radius: 6px; background: #536b82; color: #fff; font: 700 12px inherit; cursor: pointer; font-size: 12px; }
        .mgmt-table button:hover { background: #102a43; border-color: #102a43; }
        .mgmt-table .edit-store { background: #102a43; border-color: #102a43; }
        .mgmt-table .edit-store:hover { background: #234d70; }
        .mgmt-table .delete-store { width: 38px; padding: 7px 0; background: #b42318; border-color: #b42318; font-size: 16px; line-height: 1; }
        .mgmt-table .delete-store:hover { background: #8f1d14; border-color: #8f1d14; }
        .mgmt-table tr.editing { background: #eef6ff; box-shadow: inset 4px 0 #b09060; }
        .mgmt-form { display: grid; grid-template-columns: 1.2fr 2fr 1fr 1fr 1fr 1fr auto; gap: 10px; margin-bottom: 22px; align-items: end; }
        .mgmt-form input, .mgmt-form select { min-width: 0; padding: 9px 10px; border: 1px solid #d5dbe2; border-radius: 7px; font: inherit; }
        .mgmt-form button { padding: 9px 14px; border: 0; border-radius: 7px; background: #102a43; color: #fff; font-weight: 700; cursor: pointer; }
        .mgmt-form > input:not([type="hidden"]), .mgmt-form > select, .mgmt-form > button { height: 48px; align-self: end; box-sizing: border-box; }
        .store-form-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; color: #526173; font-size: 12px; font-weight: 700; }
        .store-form-field input, .store-form-field select { width: 100%; height: 48px; box-sizing: border-box; }
        .mgmt-form.editing { padding: 12px; border: 2px solid #b09060; border-radius: 9px; background: #fffaf2; }
        .mgmt-form .cancel-edit { background: #718096; }
        .store-hours-fields { grid-column: span 2; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; min-width: 0; }
        .store-time-field { display: flex; flex-direction: column; align-items: stretch; gap: 5px; min-width: 0; color: #526173; font-size: 12px; font-weight: 700; }
        .store-time-field input { width: 100%; min-width: 0; height: 48px; box-sizing: border-box; }
        .mgmt-form input[type="hidden"] { display: none; }
        @media (max-width: 1000px) { .mgmt-form { grid-template-columns: repeat(2, 1fr); } .store-hours-fields { grid-column: 1 / -1; } }
        @media (max-width: 820px) { .store-container-admin { grid-template-columns: 1fr; } .store-sidebar-admin { border-right: 0; border-bottom: 1px solid var(--line); } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb"><a href="<?php echo $escape($appBaseUrl . '/admin/index.php'); ?>">Tổng quan</a> <span>/</span> <strong>Quản lý hệ thống cửa hàng</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="welcome-row"><div>
                <h1>Quản lý hệ thống cửa hàng</h1>
                <p class="page-subtitle">Xem và quản lý thông tin các cửa hàng ÉCRIN Jewelry.</p>
            </div></div>

            <!-- Tabs -->
            <div class="store-tabs">
                <button class="store-tab active" type="button" onclick="switchTab('preview', this)">🗺 Bản đồ cửa hàng</button>
                <button class="store-tab" type="button" onclick="switchTab('manage', this)">⚙ Quản lý cửa hàng</button>
            </div>

            <!-- Tab: Bản đồ (giống client) -->
            <div id="tab-preview">
                <div class="store-preview-wrap">
                    <div class="store-container-admin">
                        <aside class="store-sidebar-admin">
                            <h2>Tìm cửa hàng</h2>
                            <div class="store-search-admin">
                                <input type="search" id="store-search" placeholder="Ví dụ: Cầu Giấy">
                            </div>
                            <div class="store-list-admin" id="store-list">
                                <?php foreach ($stores as $i => $s): ?>
                                <button class="store-btn<?php echo $i === 0 ? ' active' : ''; ?>" type="button"
                                    data-id="<?php echo $s['id']; ?>"
                                    data-name="<?php echo $escape($s['name']); ?>"
                                    data-address="<?php echo $escape($s['address']); ?>"
                                    data-phone="<?php echo $escape($s['phone']); ?>"
                                    data-hours="<?php echo $escape($s['hours']); ?>">
                                    <h3><?php echo $escape($s['name']); ?></h3>
                                    <p><?php echo $escape($s['address']); ?></p>
                                </button>
                                <?php endforeach; ?>
                                <p class="store-empty-msg" id="store-empty">Không tìm thấy cửa hàng phù hợp.</p>
                            </div>
                            <nav class="store-preview-pagination" id="store-preview-pagination" aria-label="Phân trang cửa hàng"></nav>
                        </aside>
                        <div class="store-map-col">
                            <div class="store-map-frame">
                                <iframe id="google-map" title="Bản đồ cửa hàng"
                                    src="https://www.google.com/maps?q=123%20Xu%C3%A2n%20Th%E1%BB%A7y%2C%20C%E1%BA%A7u%20Gi%E1%BA%A5y%2C%20H%C3%A0%20N%E1%BB%99i&output=embed"
                                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                            </div>
                            <div class="store-detail-info" aria-live="polite">
                                <h2 id="detail-name"><?php echo $escape($firstStore['name']); ?></h2>
                                <div class="info-grid">
                                    <div class="info-box"><h3>Địa chỉ</h3><p id="detail-address"><?php echo $escape($firstStore['address']); ?></p></div>
                                    <div class="info-box"><h3>Điện thoại</h3><p id="detail-phone"><?php echo $escape($firstStore['phone']); ?></p></div>
                                    <div class="info-box"><h3>Giờ mở cửa</h3><p id="detail-hours"><?php echo $escape($firstStore['hours']); ?></p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Quản lý -->
            <div id="tab-manage" hidden>
                <div class="mgmt-panel">
                    <h2>Quản lý cửa hàng</h2>
                    <?php if ($error !== ''): ?><p class="notice error"><?php echo $escape($error); ?></p><?php endif; ?>
                    <?php if ($success !== ''): ?><p class="notice success"><?php echo $escape($success); ?></p><?php endif; ?>
                    <div class="store-table-search">
                        <label for="store-table-search-input">Tìm theo tên cửa hàng hoặc địa chỉ</label>
                        <input type="search" id="store-table-search-input" placeholder="Ví dụ: Cầu Giấy, Nguyễn Huệ">
                    </div>
                    <h3 class="store-form-title" id="store-form-title">Thêm cửa hàng</h3>
                    <form method="post" class="mgmt-form" id="store-form">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" id="store-id" value="">
                        <label class="store-form-field" for="store-name">Tên cửa hàng<input type="text" name="name" id="store-name" required></label>
                        <label class="store-form-field" for="store-address">Địa chỉ<input type="text" name="address" id="store-address" required></label>
                        <label class="store-form-field" for="store-phone">Điện thoại<input type="text" name="phone" id="store-phone"></label>
                        <div class="store-hours-fields">
                            <label class="store-time-field">Mở cửa<input type="time" id="store-open-time" value="08:00" required></label>
                            <label class="store-time-field">Đóng cửa<input type="time" id="store-close-time" value="22:00" required></label>
                        </div>
                        <input type="hidden" name="hours" id="store-hours" value="08:00 - 22:00">
                        <label class="store-form-field" for="store-status">Trạng thái<select name="status" id="store-status"><option value="active">Hoạt động</option><option value="inactive">Tạm đóng</option></select></label>
                        <button type="submit" id="store-submit">Thêm</button>
                        <button type="button" class="cancel-edit" id="cancel-edit" hidden>Hủy</button>
                    </form>
                    <div class="store-table-scroll" aria-label="Danh sách cửa hàng có thể cuộn">
                        <table class="mgmt-table">
                            <thead>
                                <tr><th>#</th><th>Tên cửa hàng</th><th>Địa chỉ</th><th>Điện thoại</th><th>Giờ mở cửa</th><th>Trạng thái</th><th>Thao tác</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stores as $s): ?>
                                <tr>
                                    <td><?php echo (int)$s['id']; ?></td>
                                    <td><strong><?php echo $escape($s['name']); ?></strong></td>
                                    <td><?php echo $escape($s['address']); ?></td>
                                    <td><?php echo $escape($s['phone']); ?></td>
                                    <td><?php echo $escape($s['hours']); ?></td>
                                    <td><span class="status-badge <?php echo $s['status'] === 'active' ? 'status-active' : ''; ?>"><?php echo $s['status'] === 'active' ? '● Hoạt động' : '● Tạm đóng'; ?></span></td>
                                    <td>
                                        <button type="button" class="edit-store" data-id="<?php echo (int)$s['id']; ?>" data-name="<?php echo $escape($s['name']); ?>" data-address="<?php echo $escape($s['address']); ?>" data-phone="<?php echo $escape($s['phone']); ?>" data-hours="<?php echo $escape($s['hours']); ?>" data-status="<?php echo $escape($s['status']); ?>">Sửa</button>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Bạn có chắc muốn xóa cửa hàng này không?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                            <button type="submit" class="delete-store" title="Xóa cửa hàng" aria-label="Xóa cửa hàng">&#128465;&#65039;</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr id="store-table-empty" hidden><td colspan="7" style="padding:18px;text-align:center;color:#667085;">Không tìm thấy cửa hàng phù hợp.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
function switchTab(tab, btn) {
    document.getElementById('tab-preview').hidden = (tab !== 'preview');
    document.getElementById('tab-manage').hidden  = (tab !== 'manage');
    document.querySelectorAll('.store-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
}

const storeForm = document.getElementById('store-form');
const storeHoursInput = document.getElementById('store-hours');
const storeOpenTime = document.getElementById('store-open-time');
const storeCloseTime = document.getElementById('store-close-time');

function syncStoreHours() {
    storeHoursInput.value = `${storeOpenTime.value} - ${storeCloseTime.value}`;
}

function setStoreHours(value) {
    const match = String(value || '').match(/(\d{2}:\d{2})\s*[-–—]\s*(\d{2}:\d{2})/);
    storeOpenTime.value = match ? match[1] : '08:00';
    storeCloseTime.value = match ? match[2] : '22:00';
    syncStoreHours();
}

storeOpenTime.addEventListener('input', syncStoreHours);
storeCloseTime.addEventListener('input', syncStoreHours);
storeForm.addEventListener('submit', syncStoreHours);

// Store map interaction
const storeBtns = document.querySelectorAll('.store-btn');
const searchInput = document.getElementById('store-search');
const emptyMsg = document.getElementById('store-empty');
const storeList = document.getElementById('store-list');
const storePagination = document.getElementById('store-preview-pagination');
const storesPerPage = 8;
let filteredStoreBtns = Array.from(storeBtns);
let currentStorePage = 1;

function normalizeStoreSearch(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .toLocaleLowerCase('vi')
        .replace(/\s+/g, ' ')
        .trim();
}

const storeTableSearchInput = document.getElementById('store-table-search-input');
const storeTableRows = Array.from(document.querySelectorAll('.mgmt-table tbody tr:not(#store-table-empty)'));
const storeTableEmpty = document.getElementById('store-table-empty');

storeTableSearchInput.addEventListener('input', () => {
    const query = normalizeStoreSearch(storeTableSearchInput.value);
    let visibleCount = 0;

    storeTableRows.forEach(row => {
        const searchableText = `${row.cells[1]?.textContent || ''} ${row.cells[2]?.textContent || ''}`;
        const matches = normalizeStoreSearch(searchableText).includes(query);
        row.hidden = !matches;
        if (matches) visibleCount += 1;
    });

    storeTableEmpty.hidden = visibleCount > 0;
});

storeBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        storeBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('detail-name').textContent    = btn.dataset.name;
        document.getElementById('detail-address').textContent = btn.dataset.address;
        document.getElementById('detail-phone').textContent   = btn.dataset.phone;
        document.getElementById('detail-hours').textContent   = btn.dataset.hours;
        document.getElementById('google-map').src =
            'https://www.google.com/maps?q=' + encodeURIComponent(btn.dataset.address) + '&output=embed';
    });
});

searchInput.addEventListener('input', () => {
    const q = normalizeStoreSearch(searchInput.value);
    filteredStoreBtns = Array.from(storeBtns).filter(btn => {
        const searchableText = [btn.dataset.name, btn.dataset.address, btn.dataset.phone, btn.dataset.hours].join(' ');
        return normalizeStoreSearch(searchableText).includes(q);
    });
    currentStorePage = 1;
    renderAdminStores();
});

function renderAdminStores() {
    const totalPages = Math.ceil(filteredStoreBtns.length / storesPerPage);
    currentStorePage = Math.min(currentStorePage, Math.max(1, totalPages));
    const start = (currentStorePage - 1) * storesPerPage;
    storeBtns.forEach(btn => {
        const index = filteredStoreBtns.indexOf(btn);
        btn.hidden = index < start || index >= start + storesPerPage;
    });
    emptyMsg.style.display = filteredStoreBtns.length === 0 ? 'block' : 'none';
    storePagination.innerHTML = '';
    storePagination.hidden = totalPages <= 1;
    if (totalPages <= 1) return;

    const addPageButton = (label, page, disabled = false, active = false, ariaLabel = label) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = label;
        button.disabled = disabled;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-label', ariaLabel);
        if (active) button.setAttribute('aria-current', 'page');
        button.addEventListener('click', () => {
            currentStorePage = page;
            storeList.scrollTop = 0;
            renderAdminStores();
        });
        storePagination.appendChild(button);
    };

    addPageButton('‹', currentStorePage - 1, currentStorePage === 1, false, 'Trang trước');
    for (let page = 1; page <= totalPages; page += 1) {
        addPageButton(String(page), page, false, page === currentStorePage, `Trang ${page}`);
    }
    addPageButton('›', currentStorePage + 1, currentStorePage === totalPages, false, 'Trang sau');
    const count = document.createElement('span');
    count.textContent = `${currentStorePage}/${totalPages}`;
    count.style.cssText = 'padding: 0 3px; color: #667085; font-size: 13px; font-weight: 600;';
    storePagination.appendChild(count);
}

renderAdminStores();

document.querySelectorAll('.edit-store').forEach(button => {
    button.addEventListener('click', () => {
        document.getElementById('tab-preview').hidden = true;
        document.getElementById('tab-manage').hidden = false;
        document.querySelectorAll('.store-tab').forEach(tab => tab.classList.remove('active'));
        document.querySelector('.store-tab[onclick*="manage"]').classList.add('active');
        document.querySelectorAll('.mgmt-table tr.editing').forEach(row => row.classList.remove('editing'));
        button.closest('tr').classList.add('editing');
        document.getElementById('store-form').classList.add('editing');
        document.getElementById('store-form-title').textContent = 'Chỉnh sửa cửa hàng';
        document.getElementById('cancel-edit').hidden = false;
        document.getElementById('store-id').value = button.dataset.id;
        document.getElementById('store-name').value = button.dataset.name;
        document.getElementById('store-address').value = button.dataset.address;
        document.getElementById('store-phone').value = button.dataset.phone;
        setStoreHours(button.dataset.hours);
        document.getElementById('store-status').value = button.dataset.status;
        document.getElementById('store-submit').textContent = 'Lưu thay đổi';
        document.getElementById('store-name').focus();
    });
});

document.getElementById('cancel-edit').addEventListener('click', () => {
    document.getElementById('store-form').reset();
    document.getElementById('store-id').value = '';
    setStoreHours('08:00 - 22:00');
    document.getElementById('store-form-title').textContent = 'Thêm cửa hàng';
    document.getElementById('store-submit').textContent = 'Thêm';
    document.getElementById('cancel-edit').hidden = true;
    document.getElementById('store-form').classList.remove('editing');
    document.querySelectorAll('.mgmt-table tr.editing').forEach(row => row.classList.remove('editing'));
});
</script>
</body>
</html>
