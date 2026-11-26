<?php
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
            background:var(--navy); color:#fff;
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
    <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
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
                    <a href="<?php echo $escape($appBaseUrl . '/admin/tro_chuyen.php'); ?>" class="ar-add-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Quay lại Chat
                    </a>
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
                    <?php foreach (array_unique(array_column($templateRows, 'type')) as $typeOption): ?>
                        <option><?php echo $escape($typeOption); ?></option>
                    <?php endforeach; ?>
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
                        <tr><td colspan="6" class="ar-empty">Chưa có mẫu tin nhắn nào.</td></tr>
                        <?php else: ?>
                        <?php foreach ($templateRows as $idx => $row): [$tpl, $type, $color, $isActive, $updatedAt] = [$row['tpl'], $row['type'], $row['color'], $row['isActive'], $row['updatedAt']]; ?>
                        <tr data-idx="<?php echo $idx; ?>"
                            data-title="<?php echo $escape($tpl['title'] ?? ''); ?>"
                            data-type="<?php echo $escape($type); ?>"
                            data-status="<?php echo $escape($tpl['status'] ?? 'inactive'); ?>">
                            <td>
                                <a class="ar-title-link" href="<?php echo $escape($appBaseUrl . '/admin/sua_mau_tra_loi_tu_dong.php?id=' . rawurlencode((string) ($tpl['id'] ?? ''))); ?>">
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
                                        <input type="checkbox" class="ar-toggle-input" data-id="<?php echo $escape($tpl['id'] ?? ''); ?>"
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
                                <a class="ar-action-btn" href="<?php echo $escape($appBaseUrl . '/admin/sua_mau_tra_loi_tu_dong.php?id=' . rawurlencode((string) ($tpl['id'] ?? ''))); ?>" style="text-decoration:none">
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

<script>
(() => {
    const api = <?php echo json_encode($appBaseUrl . '/api/chat/tro_chuyen_quan_tri.php', JSON_UNESCAPED_SLASHES); ?>;

    // ── Helpers ──────────────────────────────────────────────────
    const request = async (url, options) => {
        const r = await fetch(url, options);
        const d = await r.json().catch(() => null);
        if (!d) throw new Error('Phản hồi không hợp lệ từ máy chủ.');
        if (d.status !== 'success') throw new Error(d.message || 'Lỗi không xác định.');
        return d;
    };

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
            const active = checkbox.checked;
            const row = checkbox.closest('tr');
            const label = row.querySelector('.ar-status-label');
            const showStatus = isActive => {
                label.textContent = isActive ? 'Đang hoạt động' : 'Chưa kích hoạt';
                label.classList.toggle('is-active', isActive);
                row.dataset.status = isActive ? 'active' : 'inactive';
            };
            showStatus(active);

            checkbox.disabled = true;
            try {
                await request(api, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': <?php echo json_encode($csrfToken); ?> },
                    body: JSON.stringify({ action: 'set_template_status', template_id: checkbox.dataset.id, active })
                });
            } catch (e) {
                checkbox.checked = !active;
                showStatus(!active);
                alert('Không thể lưu mẫu: ' + e.message);
            } finally {
                checkbox.disabled = false;
            }
        });
    });

})();
</script>
</body>
</html>
