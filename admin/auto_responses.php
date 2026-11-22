<?php
require_once __DIR__ . '/../include/admin_guard.php';
$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isManager   = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel   = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Nhân viên ÉCRIN';
$adminCurrentPage = 'auto_responses.php';

$configPath = __DIR__ . '/../config/auto_responses.json';
$config = [];
if (file_exists($configPath)) {
    $decoded = json_decode(file_get_contents($configPath), true);
    if (is_array($decoded) && isset($decoded['templates'])) {
        $config = $decoded;
    }
}
if (empty($config['templates'])) {
    $config = ['templates' => []];
}

$typeColors = [
    'Chào mừng'    => ['bg' => '#E6F4FF', 'text' => '#0958D9', 'dot' => '#1890FF'],
    'Hành trình đơn'=> ['bg' => '#F9F0FF', 'text' => '#531DAB', 'dot' => '#722ED1'],
    'Chăm sóc'     => ['bg' => '#F6FFED', 'text' => '#237804', 'dot' => '#52C41A'],
    'Tương tác'    => ['bg' => '#FFF7E6', 'text' => '#AD4E00', 'dot' => '#FA8C16'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mẫu tin nhắn tự động - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        /* ── Page layout ─────────────────────────────────── */
        .page-content { padding-top: 14px; }
        .ar-page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:24px; flex-wrap:wrap; }
        .ar-page-title h1 { margin:0 0 4px; }
        .ar-page-title p  { margin:0; color:#6B7A90; font-size:13px; }

        /* ── Add button ─────────────────────────────────── */
        .ar-add-btn {
            display:inline-flex; align-items:center; gap:8px;
            padding:10px 20px; border:0; border-radius:10px; cursor:pointer;
            background:linear-gradient(135deg,#1D3461,#2A5298); color:#fff;
            font-size:13px; font-weight:600; white-space:nowrap;
            box-shadow:0 2px 8px rgba(16,35,56,.18);
            transition:transform .15s, filter .15s;
            text-decoration:none;
        }
        .ar-add-btn:hover { transform:translateY(-2px); filter:brightness(1.08); }
        .ar-add-btn svg  { width:16px; height:16px; }

        /* ── Filter bar ─────────────────────────────────── */
        .ar-filters {
            display:flex; align-items:center; gap:10px; margin-bottom:18px; flex-wrap:wrap;
        }
        .ar-search {
            flex:1; min-width:220px; max-width:380px;
            height:38px; padding:0 12px 0 36px;
            border:1.5px solid #E2E6EC; border-radius:9px;
            font-size:13px; color:#1E293B; outline:0; background:#F8F9FB;
        }
        .ar-search:focus { border-color:#C4A45A; box-shadow:0 0 0 3px rgba(196,164,90,.12); }
        .ar-search-wrap { position:relative; flex:1; min-width:220px; max-width:380px; }
        .ar-search-wrap svg { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:#9CA3AF; pointer-events:none; }
        .ar-filter-select {
            height:38px; padding:0 32px 0 12px; border:1.5px solid #E2E6EC; border-radius:9px;
            font-size:13px; color:#374151; background:#fff; outline:0; cursor:pointer;
            appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7A90' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat:no-repeat; background-position:right 10px center;
        }
        .ar-filter-select:focus { border-color:#C4A45A; }

        /* ── Table ──────────────────────────────────────── */
        .ar-table-wrap {
            background:#fff; border:1px solid var(--line); border-radius:14px;
            box-shadow:var(--shadow); overflow:hidden;
        }
        .ar-table { width:100%; border-collapse:collapse; }
        .ar-table thead th {
            padding:12px 16px; text-align:left; font-size:12px; font-weight:700;
            color:#6B7A90; text-transform:uppercase; letter-spacing:.04em;
            background:#F8F9FB; border-bottom:1px solid #E9ECF0;
            white-space:nowrap;
        }
        .ar-table tbody tr { border-bottom:1px solid #F1F3F5; transition:background .12s; }
        .ar-table tbody tr:last-child { border-bottom:0; }
        .ar-table tbody tr:hover { background:#FAFBFC; }
        .ar-table td { padding:14px 16px; vertical-align:middle; font-size:13px; }

        /* ── Title cell ─────────────────────────────────── */
        .ar-title-link {
            color:#1D3461; font-weight:600; cursor:pointer; text-decoration:none;
            transition:color .12s;
        }
        .ar-title-link:hover { color:#2A5298; text-decoration:underline; }

        /* ── Type badge ─────────────────────────────────── */
        .ar-type-badge {
            display:inline-flex; align-items:center; gap:5px;
            padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600;
            white-space:nowrap;
        }
        .ar-type-dot { width:7px; height:7px; border-radius:50%; flex:0 0 7px; }

        /* ── Status ─────────────────────────────────────── */
        .ar-status-wrap { display:flex; align-items:center; gap:8px; }
        .ar-toggle {
            position:relative; width:38px; height:22px; cursor:pointer;
        }
        .ar-toggle input { opacity:0; width:0; height:0; }
        .ar-toggle-track {
            position:absolute; inset:0; border-radius:11px; background:#D1D5DB;
            transition:background .2s;
        }
        .ar-toggle input:checked + .ar-toggle-track { background:#1D3461; }
        .ar-toggle-thumb {
            position:absolute; left:3px; top:3px; width:16px; height:16px;
            border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.2);
            transition:left .2s;
        }
        .ar-toggle input:checked ~ .ar-toggle-thumb { left:19px; }
        .ar-status-label { font-size:12px; color:#6B7A90; }
        .ar-status-label.is-active { color:#237804; font-weight:600; }

        /* ── Action btns ─────────────────────────────────── */
        .ar-action-btn {
            display:inline-flex; align-items:center; gap:5px;
            padding:5px 12px; border:1.5px solid #E2E6EC; border-radius:7px;
            background:#fff; color:#374151; font-size:12px; font-weight:600;
            cursor:pointer; transition:all .15s; white-space:nowrap;
        }
        .ar-action-btn:hover { border-color:#C4A45A; color:#1D3461; background:#FAF8F3; }
        .ar-action-btn svg { width:13px; height:13px; }

        /* ── Empty state ─────────────────────────────────── */
        .ar-empty { padding:48px; text-align:center; color:#9CA3AF; font-size:14px; }

        /* ── Edit modal ─────────────────────────────────── */
        .ar-overlay {
            position:fixed; inset:0; background:rgba(13,24,38,.5); z-index:900;
            display:flex; align-items:center; justify-content:center;
            opacity:0; pointer-events:none; transition:opacity .22s ease;
        }
        .ar-overlay.is-open { opacity:1; pointer-events:all; }
        .ar-modal {
            width:min(620px,95vw); max-height:90vh; overflow-y:auto;
            background:#fff; border-radius:18px; box-shadow:0 24px 64px rgba(13,24,38,.22);
            padding:36px 36px 28px;
            transform:translateY(28px) scale(.97);
            transition:transform .22s ease;
        }
        .ar-overlay.is-open .ar-modal { transform:none; }
        .ar-modal-head { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; }
        .ar-modal-head h2 { font-size:18px; color:#1D3461; margin:0 0 4px; }
        .ar-modal-head p  { margin:0; font-size:12px; color:#9CA3AF; }
        .ar-modal-close {
            width:32px; height:32px; border:0; border-radius:50%; background:#F3F4F6;
            color:#6B7A90; font-size:18px; cursor:pointer; display:grid; place-items:center;
            flex:0 0 32px; transition:background .15s;
        }
        .ar-modal-close:hover { background:#E5E7EB; color:#1D3461; }
        .ar-modal-field { margin-bottom:20px; }
        .ar-modal-field label {
            display:block; font-size:12px; font-weight:700; color:#4B5563;
            text-transform:uppercase; letter-spacing:.04em; margin-bottom:7px;
        }
        .ar-modal-field input[type=text],
        .ar-modal-field textarea {
            width:100%; box-sizing:border-box; padding:11px 14px;
            border:1.5px solid #E2E6EC; border-radius:10px;
            font:14px 'Roboto', Arial, sans-serif; color:#1E293B;
            outline:0; transition:border-color .15s; resize:vertical;
        }
        .ar-modal-field input[type=text]:focus,
        .ar-modal-field textarea:focus { border-color:#C4A45A; box-shadow:0 0 0 3px rgba(196,164,90,.12); }
        .ar-modal-field textarea { min-height:120px; line-height:1.6; }
        .ar-modal-hint { font-size:11px; color:#9CA3AF; margin-top:5px; }
        .ar-modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:8px; }
        .ar-modal-status { font-size:12px; margin-right:auto; padding:6px 0; }
        .ar-modal-status.ok  { color:#237804; }
        .ar-modal-status.err { color:#A34B42; }
        .ar-btn {
            padding:10px 24px; border:0; border-radius:9px; font-size:13px; font-weight:600;
            cursor:pointer; transition:transform .15s, filter .15s;
        }
        .ar-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }
        .ar-btn-save   { background:linear-gradient(135deg,#1D3461,#2A5298); color:#fff; }
        .ar-btn-cancel { background:#F1F3F5; color:#374151; }

        /* ── Responsive ─────────────────────────────────── */
        @media(max-width:768px) {
            .ar-table thead th:nth-child(4),
            .ar-table td:nth-child(4),
            .ar-table thead th:nth-child(5),
            .ar-table td:nth-child(5) { display:none; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> Quản lý chat <span>/</span> <strong>Mẫu tin nhắn</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>

        <section class="page-content">
            <!-- Page Head -->
            <div class="ar-page-head">
                <div class="ar-page-title">
                    <p class="eyebrow">CHĂM SÓC KHÁCH HÀNG</p>
                    <h1>Mẫu tin nhắn</h1>
                    <p>Thiết lập các mẫu tin nhắn tự động gửi đến khách hàng theo từng sự kiện.</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                    <a href="<?php echo $escape($appBaseUrl . '/admin/chat.php'); ?>" class="ar-add-btn" style="background:linear-gradient(135deg,#4B3B6B,#7B5EA7);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Quay lại Chat
                    </a>
                    <button class="ar-add-btn" id="btn-add-tpl">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Thêm mẫu mới
                    </button>
                </div>
            </div>

            <!-- Filter bar -->
            <div class="ar-filters">
                <div class="ar-search-wrap">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input class="ar-search" id="ar-search" type="search" placeholder="Tìm kiếm mẫu tin nhắn...">
                </div>
                <select class="ar-filter-select" id="ar-filter-type">
                    <option value="">Tất cả loại</option>
                    <option>Chào mừng</option>
                    <option>Tương tác</option>
                    <option>Chăm sóc</option>
                    <option>Hành trình đơn</option>
                </select>
                <select class="ar-filter-select" id="ar-filter-status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Chưa kích hoạt</option>
                </select>
            </div>

            <!-- Table -->
            <div class="ar-table-wrap">
                <table class="ar-table" id="ar-table">
                    <thead>
                        <tr>
                            <th>Tiêu đề</th>
                            <th>Loại mẫu tin</th>
                            <th>Trường hợp sử dụng</th>
                            <th>Ngày cập nhật</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="ar-tbody">
                        <?php if (empty($config['templates'])): ?>
                        <tr><td colspan="6" class="ar-empty">Chưa có mẫu tin nhắn nào. Nhấn "Thêm mẫu mới" để bắt đầu.</td></tr>
                        <?php else: ?>
                        <?php foreach ($config['templates'] as $idx => $tpl):
                            $type  = $tpl['type'] ?? 'Chào mừng';
                            $color = $typeColors[$type] ?? ['bg'=>'#F0F0F0','text'=>'#333','dot'=>'#999'];
                            $isActive = ($tpl['status'] ?? '') === 'active';
                            $updatedAt = $tpl['updated_at'] ? date('d/m/Y H:i', strtotime($tpl['updated_at'])) : '---';
                        ?>
                        <tr data-idx="<?php echo $idx; ?>"
                            data-title="<?php echo $escape($tpl['title'] ?? ''); ?>"
                            data-type="<?php echo $escape($type); ?>"
                            data-status="<?php echo $escape($tpl['status'] ?? 'inactive'); ?>">
                            <td>
                                <a class="ar-title-link" href="<?php echo $escape($appBaseUrl . '/admin/auto_response_edit.php?id=' . rawurlencode((string) ($tpl['id'] ?? ''))); ?>">
                                    <?php echo $escape($tpl['title'] ?? ''); ?>
                                </a>
                            </td>
                            <td>
                                <span class="ar-type-badge" style="background:<?php echo $escape($color['bg']); ?>;color:<?php echo $escape($color['text']); ?>;">
                                    <span class="ar-type-dot" style="background:<?php echo $escape($color['dot']); ?>;"></span>
                                    <?php echo $escape($type); ?>
                                </span>
                            </td>
                            <td style="color:#6B7A90;max-width:280px;"><?php echo $escape($tpl['trigger_label'] ?? ''); ?></td>
                            <td style="color:#9CA3AF;white-space:nowrap;"><?php echo $escape($updatedAt); ?></td>
                            <td>
                                <div class="ar-status-wrap">
                                    <label class="ar-toggle" title="<?php echo $isActive ? 'Tắt' : 'Bật'; ?>">
                                        <input type="checkbox" class="ar-toggle-input" data-idx="<?php echo $idx; ?>"
                                            <?php echo $isActive ? 'checked' : ''; ?>>
                                        <span class="ar-toggle-track"></span>
                                        <span class="ar-toggle-thumb"></span>
                                    </label>
                                    <span class="ar-status-label <?php echo $isActive ? 'is-active' : ''; ?>">
                                        <?php echo $isActive ? 'Đang hoạt động' : 'Chưa kích hoạt'; ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <a class="ar-action-btn" href="<?php echo $escape($appBaseUrl . '/admin/auto_response_edit.php?id=' . rawurlencode((string) ($tpl['id'] ?? ''))); ?>" style="text-decoration:none">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    Chỉnh sửa
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<!-- Edit / Add Modal -->
<div class="ar-overlay" id="ar-overlay" role="dialog" aria-modal="true" aria-label="Chỉnh sửa mẫu tin nhắn">
    <div class="ar-modal">
        <div class="ar-modal-head">
            <div>
                <h2 id="ar-modal-title">Chỉnh sửa mẫu tin nhắn</h2>
                <p id="ar-modal-subtitle">Thay đổi nội dung và cài đặt mẫu.</p>
            </div>
            <button class="ar-modal-close" id="ar-modal-close" aria-label="Đóng">×</button>
        </div>

        <input type="hidden" id="ar-editing-idx" value="">

        <div class="ar-modal-field">
            <label>Tiêu đề mẫu tin</label>
            <input type="text" id="ar-f-title" placeholder="VD: Lời chào mở đầu" maxlength="100">
        </div>
        <div class="ar-modal-field">
            <label>Loại mẫu tin</label>
            <select class="ar-filter-select" id="ar-f-type" style="width:100%;height:42px;padding:0 32px 0 12px;">
                <option>Chào mừng</option>
                <option>Tương tác</option>
                <option>Chăm sóc</option>
                <option>Hành trình đơn</option>
            </select>
        </div>
        <div class="ar-modal-field">
            <label>Trường hợp sử dụng</label>
            <input type="text" id="ar-f-trigger" placeholder="VD: Khách hàng mới (nhắn tin lần đầu tiên)" maxlength="150">
            <p class="ar-modal-hint">Mô tả ngắn gọn khi nào tin nhắn này được gửi.</p>
        </div>
        <div class="ar-modal-field">
            <label>Nội dung tin nhắn</label>
            <textarea id="ar-f-message" rows="5" placeholder="Nhập nội dung tin nhắn tự động..."></textarea>
            <p class="ar-modal-hint">Tin nhắn này sẽ được gửi tự động bởi hệ thống dưới tên ÉCRIN Jewelry.</p>
        </div>
        <div class="ar-modal-field" style="display:flex;align-items:center;gap:12px;">
            <label class="ar-toggle" style="margin:0;" title="Bật/tắt mẫu này">
                <input type="checkbox" id="ar-f-status">
                <span class="ar-toggle-track"></span>
                <span class="ar-toggle-thumb"></span>
            </label>
            <span style="font-size:13px;color:#374151;font-weight:600;">Kích hoạt mẫu tin nhắn này</span>
        </div>

        <div class="ar-modal-actions">
            <span class="ar-modal-status" id="ar-modal-status"></span>
            <button class="ar-btn ar-btn-cancel" id="ar-cancel">Hủy</button>
            <button class="ar-btn ar-btn-save" id="ar-save">Lưu thay đổi</button>
        </div>
    </div>
</div>

<script>
(() => {
    const api = <?php echo json_encode($appBaseUrl . '/api/chat/admin_chat.php', JSON_UNESCAPED_SLASHES); ?>;
    const overlay  = document.getElementById('ar-overlay');
    const modalStatus = document.getElementById('ar-modal-status');

    // ── Template data from PHP ───────────────────────────────────
    let templates = <?php echo json_encode(array_values($config['templates']), JSON_UNESCAPED_UNICODE); ?>;

    // ── Helpers ──────────────────────────────────────────────────
    const request = async (url, options) => {
        const r = await fetch(url, options);
        const d = await r.json().catch(() => null);
        if (!d) throw new Error('Phản hồi không hợp lệ từ máy chủ.');
        if (d.status !== 'success') throw new Error(d.message || 'Lỗi không xác định.');
        return d;
    };

    async function saveAll() {
        return request(api, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_auto_responses', responses: { templates } })
        });
    }

    // ── Filter / search ───────────────────────────────────────────
    const searchInput  = document.getElementById('ar-search');
    const filterType   = document.getElementById('ar-filter-type');
    const filterStatus = document.getElementById('ar-filter-status');
    const tbody        = document.getElementById('ar-tbody');

    function applyFilters() {
        const q  = searchInput.value.trim().toLowerCase();
        const ft = filterType.value;
        const fs = filterStatus.value;
        document.querySelectorAll('#ar-tbody tr[data-idx]').forEach(row => {
            const title  = (row.dataset.title  || '').toLowerCase();
            const type   = row.dataset.type    || '';
            const status = row.dataset.status  || '';
            const show = (!q || title.includes(q))
                      && (!ft || type === ft)
                      && (!fs || status === fs);
            row.style.display = show ? '' : 'none';
        });
    }
    searchInput.addEventListener('input', applyFilters);
    filterType.addEventListener('change', applyFilters);
    filterStatus.addEventListener('change', applyFilters);

    // ── Toggle status (inline) ────────────────────────────────────
    document.querySelectorAll('.ar-toggle-input').forEach(checkbox => {
        checkbox.addEventListener('change', async () => {
            const idx = parseInt(checkbox.dataset.idx, 10);
            const newStatus = checkbox.checked ? 'active' : 'inactive';
            templates[idx].status = newStatus;
            if (newStatus === 'active') {
                templates[idx].activated_at = new Date().toISOString().replace('T', ' ').slice(0, 19);
            }
            templates[idx].updated_at = new Date().toISOString().replace('T', ' ').slice(0, 19);

            const row = checkbox.closest('tr');
            const label = row.querySelector('.ar-status-label');
            label.textContent = newStatus === 'active' ? 'Đang hoạt động' : 'Chưa kích hoạt';
            label.classList.toggle('is-active', newStatus === 'active');
            row.dataset.status = newStatus;

            try { await saveAll(); } catch (e) { console.error(e); }
        });
    });

    // ── Open modal ────────────────────────────────────────────────
    const typeColors = {
        'Chào mừng':     { bg: '#E6F4FF', text: '#0958D9', dot: '#1890FF' },
        'Hành trình đơn':{ bg: '#F9F0FF', text: '#531DAB', dot: '#722ED1' },
        'Chăm sóc':      { bg: '#F6FFED', text: '#237804', dot: '#52C41A' },
        'Tương tác':     { bg: '#FFF7E6', text: '#AD4E00', dot: '#FA8C16' },
    };

    function openModal(tpl, idx) {
        document.getElementById('ar-editing-idx').value = idx !== null ? String(idx) : '';
        document.getElementById('ar-modal-title').textContent   = idx !== null ? 'Chỉnh sửa mẫu tin nhắn' : 'Thêm mẫu tin nhắn mới';
        document.getElementById('ar-modal-subtitle').textContent = idx !== null
            ? (tpl.trigger_label || '')
            : 'Điền thông tin cho mẫu tin nhắn mới.';
        document.getElementById('ar-f-title').value   = tpl.title   || '';
        document.getElementById('ar-f-type').value    = tpl.type    || 'Chào mừng';
        document.getElementById('ar-f-trigger').value = tpl.trigger_label || '';
        document.getElementById('ar-f-message').value = tpl.message || '';
        document.getElementById('ar-f-status').checked = (tpl.status === 'active');
        modalStatus.textContent = '';
        modalStatus.className   = 'ar-modal-status';
        overlay.classList.add('is-open');
        document.getElementById('ar-f-title').focus();
    }

    document.querySelectorAll('[data-action="edit"]').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            const idx = parseInt(btn.dataset.idx, 10);
            openModal(templates[idx], idx);
        });
    });

    document.getElementById('btn-add-tpl').addEventListener('click', () => {
        openModal({ title:'', type:'Chào mừng', trigger_label:'', message:'', status:'inactive' }, null);
    });

    // ── Close modal ───────────────────────────────────────────────
    const closeModal = () => overlay.classList.remove('is-open');
    document.getElementById('ar-modal-close').addEventListener('click', closeModal);
    document.getElementById('ar-cancel').addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    // ── Save modal ────────────────────────────────────────────────
    document.getElementById('ar-save').addEventListener('click', async () => {
        const idxStr  = document.getElementById('ar-editing-idx').value;
        const isNew   = idxStr === '';
        const idx     = isNew ? null : parseInt(idxStr, 10);
        const title   = document.getElementById('ar-f-title').value.trim();
        const type    = document.getElementById('ar-f-type').value;
        const trigger = document.getElementById('ar-f-trigger').value.trim();
        const message = document.getElementById('ar-f-message').value.trim();
        const status  = document.getElementById('ar-f-status').checked ? 'active' : 'inactive';

        if (!title || !message) {
            modalStatus.className   = 'ar-modal-status err';
            modalStatus.textContent = 'Vui lòng nhập tiêu đề và nội dung.';
            return;
        }

        const now = new Date().toISOString().replace('T', ' ').slice(0, 19);
        const tpl = {
            id: isNew ? 'tpl_' + Date.now() : (templates[idx].id || 'tpl_' + Date.now()),
            title, type,
            type_color: (typeColors[type] || {}).dot || '#1890FF',
            trigger_label: trigger,
            message, status,
            updated_at: now,
            activated_at: status === 'active' ? now : (isNew ? null : templates[idx].activated_at || null)
        };

        if (isNew) templates.push(tpl);
        else templates[idx] = tpl;

        modalStatus.className   = 'ar-modal-status';
        modalStatus.textContent = 'Đang lưu...';
        document.getElementById('ar-save').disabled = true;

        try {
            await saveAll();
            modalStatus.className   = 'ar-modal-status ok';
            modalStatus.textContent = '✔ Đã lưu thành công!';
            setTimeout(() => { closeModal(); location.reload(); }, 900);
        } catch (e) {
            modalStatus.className   = 'ar-modal-status err';
            modalStatus.textContent = 'Lỗi khi lưu: ' + e.message;
        } finally {
            document.getElementById('ar-save').disabled = false;
        }
    });
})();
</script>
</body>
</html>
