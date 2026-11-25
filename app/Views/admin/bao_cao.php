<?php
// Trang quản trị: Báo cáo và thống kê (view). Logic nằm trong App\Controllers\Admin\BaoCaoController
// và các lớp báo cáo App\Services\Reports\* (BaoCaoDoanhThu, BaoCaoKhachHang).
if ($reportView === 'list'):
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Báo cáo và thống kê - ÉCRIN Jewelry</title>
        <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
        <style>
            .report-index-heading { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
            .report-index-search { width: 100%; height: 42px; margin-bottom: 18px; padding: 0 14px; border: 1px solid #dce2e9; border-radius: 8px; background: #fff; color: var(--text); }
            .report-index-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; gap: 16px; }
            .report-index-card { overflow: hidden; border: 1px solid #e5e9ef; border-radius: 10px; background: #fff; box-shadow: var(--shadow); }
            .report-index-card h2 { display: flex; align-items: center; gap: 10px; margin: 0; padding: 18px 20px; border-bottom: 1px solid #e9edf2; color: var(--navy); font-size: 18px; }
            .report-index-icon { display: grid; width: 30px; height: 30px; place-items: center; border-radius: 8px; background: #e8f3ff; color: #1685ff; font-size: 19px; }
            .report-index-list { padding: 6px 20px 12px; }
            .report-index-item { display: block; position: relative; padding: 14px 8px 14px 24px; border-bottom: 1px dashed #e4e8ed; color: #23364b; transition: color .15s, background .15s; }
            .report-index-item:last-child { border-bottom: 0; }
            .report-index-item::before { position: absolute; top: 21px; left: 8px; width: 6px; height: 6px; border-radius: 50%; background: #a4adb8; content: ''; }
            .report-index-item:hover, .report-index-item:focus-visible { border-radius: 6px; background: #f4f8fc; color: #087fe8; outline: none; }
            .report-index-item strong { display: block; font-size: 15px; font-weight: 500; }
            .report-saved-item { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px dashed #e4e8ed; }
            .report-saved-item:last-child { border-bottom: 0; }
            .report-saved-link { flex: 1; min-width: 0; color: #23364b; }
            .report-saved-link:hover { color: #087fe8; }
            .report-saved-link strong { display: block; overflow: hidden; font-size: 15px; font-weight: 500; text-overflow: ellipsis; white-space: nowrap; }
            .report-exported-card { grid-column: 1 / -1; }
            .report-exported-info small { display: block; margin-top: 3px; color: #6b7a8c; font-size: 13px; line-height: 1.5; }
            .report-exported-empty { margin: 0; padding: 10px 8px; color: #6b7a8c; font-size: 14px; }
            .report-saved-actions { display: flex; flex: 0 0 auto; align-items: center; gap: 5px; }
            .report-row-action { display: inline-grid; width: 30px; height: 30px; place-items: center; border: 0; border-radius: 5px; background: transparent; color: #8793a2; cursor: pointer; font-size: 18px; }
            .report-row-action:hover { background: #eef5fc; color: #087fe8; }
            .report-row-action svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-linecap: round; stroke-linejoin: round; stroke-width: 1.7; }
            .report-row-delete { display: inline-flex; margin: 0; }
            .report-notice { margin: 0 0 16px; padding: 11px 14px; border: 1px solid #cfe7d7; border-radius: 7px; background: #f0faf3; color: #28643a; }
            .report-notice.error { border-color: #f3c4c4; background: #fff2f2; color: #9c2f2f; }
            .report-modal[hidden] { display: none; }
            .report-modal { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .report-modal-card { width: min(100%, 560px); overflow: hidden; border-radius: 9px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .report-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 17px 24px; border-bottom: 1px solid #e6e9ed; }
            .report-modal-header h2 { margin: 0; color: #17283d; font-size: 24px; }
            .report-modal-close { border: 0; background: transparent; color: #98a2af; cursor: pointer; font-size: 28px; line-height: 1; }
            .report-modal-body { padding: 22px 24px 24px; }
            .report-modal-label { display: block; margin-bottom: 8px; color: #4b5a6b; font-size: 15px; }
            .report-modal-label b { color: #e34747; }
            .report-title-wrap { position: relative; }
            .report-title-input { width: 100%; height: 44px; padding: 0 60px 0 12px; border: 1px solid #cfd5dc; border-radius: 7px; color: #17283d; }
            .report-title-count { position: absolute; top: 14px; right: 12px; color: #8995a4; font-size: 14px; }
            .report-type-label { display: block; margin: 21px 0 10px; color: #4b5a6b; font-size: 15px; }
            .report-type-options { display: flex; flex-wrap: wrap; gap: 14px; }
            .report-type-option { position: relative; width: 200px; min-height: 125px; padding: 15px; border: 1px solid #d5dbe2; border-radius: 8px; cursor: pointer; text-align: center; }
            .report-type-option:has(input:checked) { border-color: #0784f9; background: #f1f8ff; color: #0784f9; }
            .report-type-option input { position: absolute; width: 1px; height: 1px; opacity: 0; }
            .report-type-option .type-icon { display: block; margin-bottom: 5px; color: #0784f9; font-size: 27px; }
            .report-type-option strong, .report-type-option small { display: block; }
            .report-type-option strong { font-size: 17px; }
            .report-type-option small { margin-top: 5px; color: #64758a; font-size: 14px; line-height: 1.4; }
            .report-modal-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 15px 20px; border-top: 1px solid #e6e9ed; }
            .report-modal-footer button { min-width: 82px; height: 39px; padding: 0 15px; border: 1px solid #0784f9; border-radius: 7px; background: #fff; color: #0784f9; cursor: pointer; font-weight: 600; }
            .report-modal-footer button[type=submit] { background: #0784f9; color: #fff; }
            .report-modal-footer button[type=submit]:hover { background: #006fd6; }
            .report-exported-item[hidden] { display: none; }
            .report-index-empty { display: none; grid-column: 1 / -1; padding: 24px; color: var(--muted); text-align: center; }
            .report-export-modal { position: fixed; inset: 0; z-index: 99999; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .report-export-card { width: min(100%, 480px); overflow: hidden; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .report-export-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 12px; }
            .report-export-header h2 { margin: 0; color: #111827; font-size: 22px; font-weight: 700; }
            .report-export-close { border: 0; background: transparent; color: #9ca3af; cursor: pointer; font-size: 27px; line-height: 1; padding: 4px; }
            .report-export-close:hover { color: #4b5563; }
            .report-export-body { padding: 10px 24px 28px; color: #1f2937; font-size: 17px; line-height: 1.5; }
            .report-export-body p { margin: 0; }
            .report-export-name-label { display: block; margin: 16px 0 6px; font-size: 14px; font-weight: 600; color: #1f2937; }
            .report-export-name { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; font-size: 15px; box-sizing: border-box; }
            .report-export-name:focus { border-color: #0084ff; outline: none; box-shadow: 0 0 0 3px rgba(0,132,255,.15); }
            .report-export-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding: 0 24px 24px; }
            .report-export-footer button, .report-export-footer a { display: inline-flex; min-width: 82px; height: 38px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; font-size: 16px; font-weight: 500; text-decoration: none; cursor: pointer; box-sizing: border-box; transition: all .15s ease; }
            .report-export-footer .report-export-cancel { border: 1px solid #0084ff; background: #fff; color: #0084ff; }
            .report-export-footer .report-export-cancel:hover { background: #f0f7ff; }
            .report-export-footer .report-export-confirm { border: 1px solid #0084ff; background: #0084ff; color: #fff; }
            .report-export-footer .report-export-confirm:hover { background: #0070d8; border-color: #0070d8; }
            .report-index-export-btn { min-height: 40px; padding: 0 16px; border: 1px solid #0784f9; border-radius: 7px; background: #fff; color: #0784f9; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 16px; transition: all .15s ease; }
            .report-index-export-btn:hover { background: #f0f7ff; border-color: #006fd6; color: #006fd6; }
            @media (max-width: 850px) { .report-index-grid { grid-template-columns: 1fr; } }
            @media (max-width: 560px) { .report-index-heading { align-items: flex-start; flex-direction: column; } .report-index-card h2 { padding: 15px; } .report-index-list { padding-right: 12px; padding-left: 12px; } .report-modal-header { padding: 15px; } .report-modal-body { padding: 18px 15px; } .report-type-option { width: calc(50% - 7px); min-width: 130px; } }
        </style>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.css?v=' . (int) @filemtime(APP_ROOT . '/admin/bao_cao.css')); ?>">
    </head>
    <body>
    <div class="admin-layout">
        <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb">Trang chủ <span>/</span> <strong>Báo cáo và thống kê</strong></div>
                <div class="admin-profile">
                    <div class="avatar">QL</div>
                    <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
                </div>
            </header>
            <section class="page-content">
                <div class="report-index-heading">
                    <h1>Danh sách báo cáo</h1>
                </div>
                <?php if (is_array($reportNotice) && ($reportNotice['message'] ?? '') !== ''): ?>
                    <p class="report-notice<?php echo ($reportNotice['type'] ?? '') === 'error' ? ' error' : ''; ?>" role="status"><?php echo $escape($reportNotice['message']); ?></p>
                <?php endif; ?>
                <?php if ($reportError !== ''): ?><p class="report-notice error" role="alert"><?php echo $escape($reportError); ?></p><?php endif; ?>
                <input type="search" class="report-index-search" id="report-search" placeholder="Tìm kiếm báo cáo đã xuất" aria-label="Tìm kiếm báo cáo đã xuất">
                <div class="report-index-grid" id="report-list">
                    <section class="report-index-card" aria-labelledby="sales-reports-heading">
                        <h2 id="sales-reports-heading"><span class="report-index-icon" aria-hidden="true">$</span>BÁO CÁO BÁN HÀNG</h2>
                        <div class="report-index-list">
                            <?php foreach ($salesReportLinks as [$section, $title, $anchor]): ?>
                                <a class="report-index-item" data-report-name="<?php echo $escape($title); ?>" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . http_build_query(['view' => 'sales', 'section' => $section]) . $anchor); ?>">
                                    <strong><?php echo $escape($title); ?></strong>
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($savedReports as $savedReport): ?>
                                <?php if ($savedReport['report_type'] !== 'sales') continue; ?>
                                <div class="report-saved-item">
                                    <a class="report-saved-link" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . http_build_query(['view' => 'sales', 'report_id' => (int) $savedReport['id']])); ?>">
                                        <strong><?php echo $escape($savedReport['title']); ?></strong>
                                    </a>
                                    <div class="report-saved-actions">
                                        <button class="report-row-action report-edit" type="button" data-id="<?php echo (int) $savedReport['id']; ?>" data-title="<?php echo $escape($savedReport['title']); ?>" data-type="sales" aria-label="Sửa <?php echo $escape($savedReport['title']); ?>" title="Sửa">✎</button>
                                        <form method="post" class="report-row-delete" onsubmit="return confirm('Bạn có chắc muốn xóa báo cáo này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                                            <input type="hidden" name="action" value="delete_saved_report">
                                            <input type="hidden" name="report_id" value="<?php echo (int) $savedReport['id']; ?>">
                                            <button class="report-row-action" type="submit" aria-label="Xóa <?php echo $escape($savedReport['title']); ?>" title="Xóa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path></svg></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section class="report-index-card" aria-labelledby="customer-reports-heading">
                        <h2 id="customer-reports-heading"><span class="report-index-icon" aria-hidden="true">♙</span>BÁO CÁO KHÁCH HÀNG</h2>
                        <div class="report-index-list">
                            <?php foreach ($customerReportLinks as [$report, $title]): ?>
                                <a class="report-index-item" data-report-name="<?php echo $escape($title); ?>" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . http_build_query(['view' => 'customer', 'customer_report' => $report])); ?>">
                                    <strong><?php echo $escape($title); ?></strong>
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($savedReports as $savedReport): ?>
                                <?php if ($savedReport['report_type'] !== 'customer') continue; ?>
                                <div class="report-saved-item">
                                    <a class="report-saved-link" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . http_build_query(['view' => 'customer', 'report_id' => (int) $savedReport['id']])); ?>">
                                        <strong><?php echo $escape($savedReport['title']); ?></strong>
                                    </a>
                                    <div class="report-saved-actions">
                                        <button class="report-row-action report-edit" type="button" data-id="<?php echo (int) $savedReport['id']; ?>" data-title="<?php echo $escape($savedReport['title']); ?>" data-type="customer" aria-label="Sửa <?php echo $escape($savedReport['title']); ?>" title="Sửa">✎</button>
                                        <form method="post" class="report-row-delete" onsubmit="return confirm('Bạn có chắc muốn xóa báo cáo này?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                                            <input type="hidden" name="action" value="delete_saved_report">
                                            <input type="hidden" name="report_id" value="<?php echo (int) $savedReport['id']; ?>">
                                            <button class="report-row-action" type="submit" aria-label="Xóa <?php echo $escape($savedReport['title']); ?>" title="Xóa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path></svg></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <section class="report-index-card report-exported-card" aria-labelledby="exported-reports-heading">
                        <h2 id="exported-reports-heading"><span class="report-index-icon" aria-hidden="true">⇩</span>BÁO CÁO ĐÃ XUẤT</h2>
                        <div class="report-index-list">
                            <?php if (empty($exportedReports)): ?>
                                <p class="report-exported-empty">Chưa có báo cáo nào được xuất. Mở một báo cáo, chọn “Xuất báo cáo” và đặt tên để lưu vào danh sách này.</p>
                            <?php endif; ?>
                            <?php if (!empty($exportedReports)): ?>
                                <div class="rx-head" aria-hidden="true">
                                    <span class="rx-grid">
                                        <span>Tên báo cáo</span><span>Loại</span><span>Mục báo cáo</span><span>Khoảng thời gian</span><span>Xuất lúc</span>
                                    </span>
                                    <span class="rx-actions-space"></span>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($exportedReports as $exported): ?>
                                <div class="report-saved-item report-exported-item">
                                    <a class="report-saved-link report-exported-info" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . \App\Services\BaoCaoDaXuatService::detailQuery($exported)); ?>" title="Xem chi tiết báo cáo">
                                        <span class="rx-cell rx-name"><strong><?php echo $escape($exported['name']); ?></strong></span>
                                        <span class="rx-cell"><?php echo $escape($exported['type_label']); ?></span>
                                        <span class="rx-cell"><?php echo $escape($exported['section_label'] !== '' ? $exported['section_label'] : '—'); ?></span>
                                        <span class="rx-cell"><?php echo $escape(date('d/m/Y', strtotime($exported['from'] ?: 'now'))); ?> – <?php echo $escape(date('d/m/Y', strtotime($exported['to'] ?: 'now'))); ?></span>
                                        <span class="rx-cell"><?php echo $escape(date('d/m/Y H:i', strtotime($exported['last_exported_at']))); ?></span>
                                    </a>
                                    <div class="report-saved-actions">
                                        <form method="post" class="report-row-delete" onsubmit="return confirm('Bạn có chắc muốn xóa báo cáo đã xuất này khỏi danh sách?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                                            <input type="hidden" name="action" value="delete_exported_report">
                                            <input type="hidden" name="report_id" value="<?php echo (int) $exported['id']; ?>">
                                            <button class="report-row-action" type="submit" aria-label="Xóa <?php echo $escape($exported['name']); ?>" title="Xóa"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3"></path></svg></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <p class="report-index-empty" id="report-search-empty">Không tìm thấy báo cáo đã xuất phù hợp.</p>
                        </div>
                    </section>
                </div>
            </section>
        </main>
    </div>
    <div class="report-modal" id="report-modal" hidden>
        <form class="report-modal-card" method="post" id="report-form">
            <div class="report-modal-header">
                <h2 id="report-modal-heading">Thêm mới báo cáo</h2>
                <button class="report-modal-close" type="button" aria-label="Đóng">×</button>
            </div>
            <div class="report-modal-body">
                <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                <input type="hidden" name="action" id="report-action" value="create_saved_report">
                <input type="hidden" name="report_id" id="report-id" value="">
                <label class="report-modal-label" for="report-title">Tên báo cáo<b>*</b></label>
                <div class="report-title-wrap">
                    <input class="report-title-input" type="text" name="title" id="report-title" maxlength="250" required>
                    <span class="report-title-count" id="report-title-count">0/250</span>
                </div>
                <span class="report-type-label">Loại báo cáo</span>
                <div class="report-type-options">
                    <label class="report-type-option">
                        <input type="radio" name="report_type" value="sales" checked>
                        <span class="type-icon" aria-hidden="true">$</span>
                        <strong>Bán hàng</strong>
                        <small>Báo cáo doanh thu theo thời gian</small>
                    </label>
                    <label class="report-type-option">
                        <input type="radio" name="report_type" value="customer">
                        <span class="type-icon" aria-hidden="true">♙</span>
                        <strong>Khách hàng</strong>
                        <small>Báo cáo khách hàng theo giá trị đơn hàng</small>
                    </label>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-modal-cancel">Hủy</button>
                <button type="submit" id="report-submit">Thêm mới</button>
            </div>
        </form>
    </div>
    </div>
    <div class="report-export-modal" id="list-export-modal" style="display:none">
        <section class="report-export-card" role="dialog" aria-modal="true" aria-labelledby="list-export-heading" tabindex="-1">
            <header class="report-export-header">
                <h2 id="list-export-heading">Xuất báo cáo</h2>
                <button class="report-export-close" type="button" aria-label="Đóng" id="list-export-close">×</button>
            </header>
            <div class="report-export-body">
                <p>Bạn có muốn xuất báo cáo? Báo cáo này sẽ được xuất ra file excel</p>
                <label class="report-export-name-label" for="list-export-name">Tên báo cáo</label>
                <input class="report-export-name" type="text" id="list-export-name" maxlength="100" value="<?php echo $escape('Báo cáo bán hàng'); ?>" placeholder="Nhập tên báo cáo">
            </div>
            <footer class="report-export-footer">
                <button type="button" class="report-export-cancel" id="list-export-cancel">Hủy</button>
                <a class="report-export-confirm" id="list-export-confirm" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?view=sales&section=revenue&export=xlsx'); ?>" onclick="var n=document.getElementById('list-export-name').value.trim();var u=new window.URL(this.href,window.location.href);if(n){u.searchParams.set('filename',n);}else{u.searchParams.delete('filename');}this.href=u.pathname+u.search;setTimeout(() => { document.getElementById('list-export-modal').style.display='none'; }, 200);">Xuất báo cáo</a>
            </footer>
        </section>
    </div>
    <script>
        // Ô tìm kiếm chỉ lọc danh sách "Báo cáo đã xuất"; hai thẻ báo cáo bán hàng / khách hàng luôn hiển thị.
        const reportSearch = document.getElementById('report-search');
        const exportedItems = Array.from(document.querySelectorAll('.report-exported-item'));
        const emptyMessage = document.getElementById('report-search-empty');
        reportSearch.addEventListener('input', () => {
            const query = reportSearch.value.trim().toLocaleLowerCase('vi');
            let visibleCount = 0;
            exportedItems.forEach((item) => {
                const visible = item.textContent.toLocaleLowerCase('vi').includes(query);
                item.hidden = !visible;
                if (visible) visibleCount++;
            });
            emptyMessage.style.display = exportedItems.length > 0 && visibleCount === 0 ? 'block' : 'none';
        });

        const reportModal = document.getElementById('report-modal');
        const reportForm = document.getElementById('report-form');
        const reportTitle = document.getElementById('report-title');
        const reportTitleCount = document.getElementById('report-title-count');
        const reportAction = document.getElementById('report-action');
        const reportModalHeading = document.getElementById('report-modal-heading');
        const reportSubmit = document.getElementById('report-submit');
        const reportId = document.getElementById('report-id');
        const reportTypeInputs = Array.from(reportForm.querySelectorAll('input[name="report_type"]'));
        const closeReportModal = () => {
            reportModal.hidden = true;
            document.body.style.overflow = '';
        };
        const openReportModal = (mode, report = null) => {
            const isEdit = mode === 'edit';
            reportAction.value = isEdit ? 'update_saved_report' : 'create_saved_report';
            reportId.value = isEdit ? report.id : '';
            reportModalHeading.textContent = isEdit ? 'Sửa tên báo cáo' : 'Thêm mới báo cáo';
            reportSubmit.textContent = isEdit ? 'Lưu' : 'Thêm mới';
            reportTitle.value = isEdit ? report.title : '';
            reportTypeInputs.forEach((input) => { input.checked = input.value === (isEdit ? report.type : 'sales'); });
            reportTitleCount.textContent = `${reportTitle.value.length}/250`;
            reportModal.hidden = false;
            document.body.style.overflow = 'hidden';
            reportTitle.focus();
        };
        document.querySelectorAll('.report-edit').forEach((button) => {
            button.addEventListener('click', () => openReportModal('edit', {
                id: button.dataset.id,
                title: button.dataset.title,
                type: button.dataset.type
            }));
        });
        reportTitle.addEventListener('input', () => { reportTitleCount.textContent = `${reportTitle.value.length}/250`; });
        reportModal.querySelectorAll('.report-modal-close, .report-modal-cancel').forEach((button) => {
            button.addEventListener('click', closeReportModal);
        });
        reportModal.addEventListener('click', (event) => {
            if (event.target === reportModal) closeReportModal();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !reportModal.hidden) closeReportModal();
        });


        const listExportModal = document.getElementById('list-export-modal');
        const listExportOpen = document.getElementById('list-export-open');
        const listExportClose = document.getElementById('list-export-close');
        const listExportCancel = document.getElementById('list-export-cancel');

        if (listExportOpen && listExportModal) {
            listExportOpen.addEventListener('click', () => {
                listExportModal.style.display = 'grid';
            });
            const closeListExport = () => { listExportModal.style.display = 'none'; };
            if (listExportClose) listExportClose.addEventListener('click', closeListExport);
            if (listExportCancel) listExportCancel.addEventListener('click', closeListExport);
            listExportModal.addEventListener('click', (e) => {
                if (e.target === listExportModal) closeListExport();
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && listExportModal.style.display !== 'none') closeListExport();
            });
        }
    </script>
    </body>
    </html>
    <?php
elseif ($reportView === 'customer'):
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $escape($customerTitle); ?> - ÉCRIN Jewelry</title>
        <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
        <style>
            .customer-report-panel { margin-bottom: 18px; padding: 20px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
            .customer-table-wrap { overflow-x: auto; }
            .customer-table { width: 100%; border-collapse: collapse; }
            .customer-table th, .customer-table td { border-bottom: 1px solid #eef1f5; text-align: left; }
            .customer-table td:first-child { font-weight: 600; }
            .customer-detail-heading .report-back-button { display: inline-grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #718198; font-size: 22px; }
            .customer-detail-heading .report-back-button:hover { color: #0784f9; }
            .customer-detail-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
            .customer-detail-heading h1 { font-size: 26px; }
            .customer-detail-actions { display: flex; align-items: center; gap: 18px; margin-left: auto; }
            .customer-detail-actions a, .customer-detail-actions button { padding: 0; border: 0; background: transparent; color: #34465b; cursor: pointer; font-size: 15px; font-weight: 600; }
            .customer-detail-actions form { margin: 0; }
            .customer-detail-actions .report-save-button { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 20px; border: 0; border-radius: 8px; background: var(--navy); color: #fff; font-size: 16px; font-weight: 600; }
            .customer-detail-actions .report-save-button:hover { background: var(--navy-soft); color: #fff; }
            .customer-filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; }
            .customer-filterbar > select, .customer-filterbar > input { min-height: 39px; padding: 8px 10px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: var(--text); }
            .customer-filterbar > select { min-width: 150px; }
            .customer-filterbar > input { width: 145px; }
            .customer-filterbar .report-button { min-height: 39px; padding: 0 14px; border: 1px solid var(--navy); border-radius: 7px; background: var(--navy); color: #fff; font-weight: 600; cursor: pointer; }
            .customer-filterbar .report-button:hover { background: var(--navy-soft); color: #fff; }
            .customer-compare-label { display: inline-flex; min-height: 39px; align-items: center; padding: 0 12px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #526276; font-size: 14px; }
            .customer-table-panel { overflow: hidden; padding: 18px 0 0; }
            .customer-table-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 18px 14px; }
            .customer-table th { padding: 12px 14px; background: #f2f4f6; color: #46576a; font-size: 14px; white-space: nowrap; }
            .customer-table td { padding: 12px 14px; font-size: 15px; white-space: nowrap; }
            .customer-table tbody tr:nth-child(even) { background: #f3f5f7; }
            .customer-table tbody tr[hidden] { display: none; }
            .customer-table-footer { display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 12px 18px; color: #526276; font-size: 14px; }
            .customer-table-footer select, .customer-table-footer button { min-height: 32px; border: 1px solid #dce2e9; border-radius: 5px; background: #fff; color: #526276; }
            .customer-table-footer button { min-width: 32px; cursor: pointer; }
            .customer-table-footer button:disabled { opacity: .4; cursor: default; }
            .customer-empty { padding: 18px 0; color: var(--muted); text-align: center; }
            .customer-error { margin-bottom: 16px; padding: 12px 15px; border: 1px solid #f3c4c4; border-radius: 8px; background: #fff2f2; color: #9c2f2f; }
            .customer-report-panel .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; clip-path: inset(50%); }
            .report-export-modal { position: fixed; inset: 0; z-index: 99999; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .report-export-card { width: min(100%, 480px); overflow: hidden; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .report-export-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 12px; }
            .report-export-header h2 { margin: 0; color: #111827; font-size: 22px; font-weight: 700; }
            .report-export-close { border: 0; background: transparent; color: #9ca3af; cursor: pointer; font-size: 27px; line-height: 1; padding: 4px; }
            .report-export-close:hover { color: #4b5563; }
            .report-export-body { padding: 10px 24px 28px; color: #1f2937; font-size: 17px; line-height: 1.5; }
            .report-export-body p { margin: 0; }
            .report-export-name-label { display: block; margin: 16px 0 6px; font-size: 14px; font-weight: 600; color: #1f2937; }
            .report-export-name { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; font-size: 15px; box-sizing: border-box; }
            .report-export-name:focus { border-color: #0084ff; outline: none; box-shadow: 0 0 0 3px rgba(0,132,255,.15); }
            .report-export-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding: 0 24px 24px; }
            .report-export-footer button, .report-export-footer a { display: inline-flex; min-width: 82px; height: 38px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; font-size: 16px; font-weight: 500; text-decoration: none; cursor: pointer; box-sizing: border-box; transition: all .15s ease; }
            .report-export-footer .report-export-cancel { border: 1px solid #0084ff; background: #fff; color: #0084ff; }
            .report-export-footer .report-export-cancel:hover { background: #f0f7ff; }
            .report-export-footer .report-export-confirm { border: 1px solid #0084ff; background: #0084ff; color: #fff; }
            .report-export-footer .report-export-confirm:hover { background: #0070d8; border-color: #0070d8; }
            @media (max-width: 650px) { .customer-detail-heading { flex-wrap: wrap; } .customer-detail-actions { width: 100%; justify-content: flex-end; } .customer-filterbar > select, .customer-filterbar > input { width: 100%; } .customer-table-footer { justify-content: flex-start; flex-wrap: wrap; } }
        </style>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.css?v=' . (int) @filemtime(APP_ROOT . '/admin/bao_cao.css')); ?>">
    </head>
    <body>
    <div class="admin-layout">
        <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb">Trang chủ <span>/</span> <a href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php'); ?>">Báo cáo</a> <span>/</span> <strong><?php echo $escape($customerTitle); ?></strong></div>
                <div class="admin-profile">
                    <div class="avatar">QL</div>
                    <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
                </div>
            </header>
            <section class="page-content">
                <div class="customer-detail-heading">
                    <a class="report-back-button" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php'); ?>" aria-label="Quay lại danh sách báo cáo">←</a>
                    <h1><?php echo $escape($customerTitle); ?></h1>
                    <div class="customer-detail-actions">
                        <?php if ($customerError === ''): ?><button type="button" onclick="document.getElementById('export-confirmation').style.display='grid'">⇧ Xuất báo cáo</button><?php endif; ?>
                        <?php if ($currentSavedReport): ?><form method="post">
                            <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                            <input type="hidden" name="action" value="save_report_state">
                            <input type="hidden" name="report_id" value="<?php echo $savedReportId ? (int) $savedReportId : ''; ?>">
                            <input type="hidden" name="report_type" value="customer">
                            <input type="hidden" name="title" value="<?php echo $escape($customerTitle); ?>">
                            <input type="hidden" name="report_config" value="<?php echo $escape(json_encode(['view' => 'customer', 'customer_report' => $customerReport, 'period' => 'custom', 'from' => $startDate, 'to' => $endDate], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
                            <button type="submit" class="report-save-button">▣ Lưu báo cáo</button>
                        </form><?php endif; ?>
                    </div>
                </div>
                <?php if (is_array($reportNotice) && ($reportNotice['message'] ?? '') !== ''): ?><p class="report-notice<?php echo ($reportNotice['type'] ?? '') === 'error' ? ' error' : ''; ?>" role="status"><?php echo $escape($reportNotice['message']); ?></p><?php endif; ?>
                <?php if ($customerError !== ''): ?><p class="customer-error" role="alert"><?php echo $escape($customerError); ?></p><?php endif; ?>
                <form method="get" class="customer-filterbar">
                        <input type="hidden" name="view" value="customer">
                        <input type="hidden" name="customer_report" value="<?php echo $escape($customerReport); ?>">
                        <?php if ($savedReportId): ?><input type="hidden" name="report_id" value="<?php echo (int) $savedReportId; ?>"><?php endif; ?>
                        <span class="customer-compare-label">Theo thời gian</span>
                        <label class="sr-only" for="customer-period">Khoảng thời gian</label>
                        <select name="period" id="customer-period">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                            <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                            <option value="last_7" <?php echo $period === 'last_7' ? 'selected' : ''; ?>>7 ngày qua (<?php echo $escape($customerRange); ?>)</option>
                            <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                            <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                            <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>30 ngày gần nhất</option>
                            <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn ngày</option>
                        </select>
                        <label class="sr-only" for="customer-from">Từ ngày</label>
                        <input type="date" name="from" id="customer-from" value="<?php echo $escape($startDate); ?>">
                        <label class="sr-only" for="customer-to">Đến ngày</label>
                        <input type="date" name="to" id="customer-to" value="<?php echo $escape($endDate); ?>">
                        <button type="submit" class="report-button">Áp dụng</button>
                    </form>
                <section class="customer-report-panel customer-table-panel">
                    <div class="customer-table-toolbar">
                        <strong><?php echo $escape($customerTitle); ?></strong>
                    </div>
                    <?php if ($customerDetailRows === []): ?>
                        <p class="customer-empty">Không có dữ liệu khách hàng trong khoảng thời gian này.</p>
                    <?php else: ?>
                        <div class="customer-table-wrap">
                            <table class="customer-table" id="customer-data-table">
                                <thead><tr><?php foreach ($customerDetailHeaders as $header): ?><th><?php echo $escape($header); ?> <span aria-hidden="true">↕</span></th><?php endforeach; ?></tr></thead>
                                <tbody>
                                    <?php foreach ($customerDetailRows as $row): ?>
                                        <tr><?php foreach ($row as $cell): ?><td><?php echo $escape($cell); ?></td><?php endforeach; ?></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="customer-table-footer">
                            <span id="customer-table-range"></span>
                            <label for="customer-page-size">Hiển thị</label>
                            <select id="customer-page-size"><option>20</option><option selected>50</option><option>100</option></select>
                            <span>Kết quả</span>
                            <button type="button" id="customer-page-prev" aria-label="Trang trước">‹</button>
                            <span id="customer-page-label"></span>
                            <button type="button" id="customer-page-next" aria-label="Trang sau">›</button>
                        </div>
                    <?php endif; ?>
                </section>
            </section>
        </main>
    </div>
    <?php if ($customerError === ''): ?>
        <div class="report-export-modal" id="export-confirmation" style="display:none">
            <section class="report-export-card" role="dialog" aria-modal="true" aria-labelledby="export-confirmation-title" tabindex="-1">
                <header class="report-export-header">
                    <h2 id="export-confirmation-title">Xuất báo cáo</h2>
                    <button class="report-export-close" type="button" aria-label="Đóng" onclick="document.getElementById('export-confirmation').style.display='none'">×</button>
                </header>
                <div class="report-export-body">
                    <p>Bạn có muốn xuất báo cáo? Báo cáo này sẽ được xuất ra file excel</p>
                    <label class="report-export-name-label" for="customer-export-name">Tên báo cáo</label>
                    <input class="report-export-name" type="text" id="customer-export-name" maxlength="100" value="<?php echo $escape($customerTitle); ?>" placeholder="Nhập tên báo cáo">
                </div>
                <footer class="report-export-footer">
                    <button type="button" class="report-export-cancel" onclick="document.getElementById('export-confirmation').style.display='none'">Hủy</button>
                    <a class="report-export-confirm" id="customer-export-xlsx" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . $customerExportQueryXlsx); ?>" onclick="var n=document.getElementById('customer-export-name').value.trim();var u=new window.URL(this.href,window.location.href);if(n){u.searchParams.set('filename',n);}else{u.searchParams.delete('filename');}this.href=u.pathname+u.search;setTimeout(() => { document.getElementById('export-confirmation').style.display='none'; }, 200);">Xuất báo cáo</a>
                </footer>
            </section>
        </div>
    <?php endif; ?>
    <script>
        const customerExportModal = document.getElementById('export-confirmation');
        if (customerExportModal) {
            customerExportModal.addEventListener('click', (e) => {
                if (e.target === customerExportModal) customerExportModal.style.display = 'none';
            });
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && customerExportModal.style.display !== 'none') {
                    customerExportModal.style.display = 'none';
                }
            });
        }
        const syncCustomerExportLinks = () => {
            const periodVal = document.getElementById('customer-period')?.value || 'this_month';
            const fromVal = document.getElementById('customer-from')?.value || '';
            const toVal = document.getElementById('customer-to')?.value || '';
            const xlsxBtn = document.getElementById('customer-export-xlsx');
            if (xlsxBtn) {
                const u = new URL(xlsxBtn.href, window.location.origin);
                if (periodVal) u.searchParams.set('period', periodVal);
                if (fromVal) u.searchParams.set('from', fromVal);
                if (toVal) u.searchParams.set('to', toVal);
                xlsxBtn.href = u.pathname + u.search;
            }
        };

        const customerPeriod = document.getElementById('customer-period');
        customerPeriod.addEventListener('change', () => {
            syncCustomerExportLinks();
            if (customerPeriod.value === 'custom') return;
            customerPeriod.form.requestSubmit();
        });
        document.getElementById('customer-from').addEventListener('change', () => {
            customerPeriod.value = 'custom';
            syncCustomerExportLinks();
        });
        document.getElementById('customer-to').addEventListener('change', () => {
            customerPeriod.value = 'custom';
            syncCustomerExportLinks();
        });
        const customerTable = document.getElementById('customer-data-table');
        if (customerTable) {
            const rows = Array.from(customerTable.tBodies[0].rows);
            const pageSize = document.getElementById('customer-page-size');
            const range = document.getElementById('customer-table-range');
            const pageLabel = document.getElementById('customer-page-label');
            const previous = document.getElementById('customer-page-prev');
            const next = document.getElementById('customer-page-next');
            let page = 0;
            const renderCustomerPage = () => {
                const size = Number(pageSize.value);
                const pages = Math.max(1, Math.ceil(rows.length / size));
                page = Math.min(page, pages - 1);
                const start = page * size;
                const end = Math.min(start + size, rows.length);
                const visible = new Set(rows.slice(start, end));
                rows.forEach((row) => { row.hidden = !visible.has(row); });
                range.textContent = `Từ ${start + 1} đến ${end} trên tổng ${rows.length}`;
                pageLabel.textContent = `${page + 1}/${pages}`;
                previous.disabled = page === 0;
                next.disabled = page >= pages - 1;
            };
            pageSize.addEventListener('change', () => { page = 0; renderCustomerPage(); });
            previous.addEventListener('click', () => { page--; renderCustomerPage(); });
            next.addEventListener('click', () => { page++; renderCustomerPage(); });
            renderCustomerPage();
        }
    </script>
    </body>
    </html>
    <?php
elseif ($reportView === 'exported'):
    $exportedSnapshot = $exportedEntry['snapshot'];
    $fmtDate = static function (string $d, string $format = 'd/m/Y'): string {
        $t = strtotime($d);

        return $t ? date($format, $t) : $d;
    };
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $escape($exportedEntry['name']); ?> - Báo cáo đã xuất</title>
        <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
        <style>
            .exp-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 10px; }
            .exp-heading h1 { margin: 0; font-size: 26px; color: var(--navy); }
            .exp-back { display: inline-grid; width: 40px; height: 40px; flex: 0 0 40px; place-items: center; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #718198; font-size: 22px; text-decoration: none; }
            .exp-back:hover { color: #0784f9; }
            .exp-note { margin: 0 0 18px; padding: 10px 14px; border: 1px solid #cfe0f5; border-radius: 8px; background: #f2f8ff; color: #1d4f91; font-size: 14px; }
            .exp-warn { border-color: #f3dcb6; background: #fff8ec; color: #8a5a10; }
            .exp-panel { margin-bottom: 18px; padding: 20px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
            .exp-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; margin: 0; }
            .exp-meta div { min-width: 0; }
            .exp-meta dt { margin: 0 0 3px; color: #6b7a8c; font-size: 13px; }
            .exp-meta dd { margin: 0; color: var(--navy); font-weight: 600; overflow-wrap: anywhere; }
            .exp-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; }
            .exp-kpi { padding: 12px 14px; border: 1px solid #e5e9ef; border-radius: 8px; background: #fafbfc; }
            .exp-kpi span { display: block; color: #6b7a8c; font-size: 13px; }
            .exp-kpi strong { display: block; margin-top: 4px; color: var(--navy); font-size: 18px; }
            .exp-table-wrap { overflow-x: auto; }
            .exp-table { width: 100%; border-collapse: collapse; }
            .exp-table th, .exp-table td { padding: 12px 14px; border-bottom: 1px solid var(--line); text-align: left; color: var(--navy); }
            .exp-table th { background: #fafbfc; font-size: 13px; font-weight: 700; white-space: nowrap; }
            .exp-export-btn { margin-left: auto; min-height: 40px; padding: 0 16px; border: 1px solid #0784f9; border-radius: 7px; background: #fff; color: #0784f9; font: 600 15px inherit; cursor: pointer; }
            .exp-export-btn:hover { background: #f0f7ff; }
            .exp-modal { position: fixed; inset: 0; z-index: 99999; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
            .exp-modal-card { width: min(100%, 480px); padding: 20px 24px 24px; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
            .exp-modal-card header { display: flex; align-items: center; justify-content: space-between; }
            .exp-modal-card h2 { margin: 0; font-size: 22px; color: #111827; }
            .exp-modal-close { border: 0; background: transparent; color: #9ca3af; font-size: 27px; line-height: 1; cursor: pointer; }
            .exp-modal-card p { margin: 12px 0 0; color: #1f2937; font-size: 16px; }
            .exp-modal-card label { display: block; margin: 16px 0 6px; font-size: 14px; font-weight: 600; color: #1f2937; }
            .exp-modal-card input { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; font-size: 15px; box-sizing: border-box; }
            .exp-modal-card footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 22px; }
            .exp-modal-card footer button, .exp-modal-card footer a { display: inline-flex; height: 38px; min-width: 82px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; border: 1px solid #0084ff; font-size: 16px; text-decoration: none; cursor: pointer; box-sizing: border-box; }
            .exp-cancel { background: #fff; color: #0084ff; }
            .exp-confirm { background: #0084ff; color: #fff; }
            .exp-empty { margin: 0; padding: 18px 0; text-align: center; color: #6b7a8c; }
        </style>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.css?v=' . (int) @filemtime(APP_ROOT . '/admin/bao_cao.css')); ?>">
    </head>
    <body>
    <div class="admin-layout">
        <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
        <main class="main-content">
            <header class="topbar">
                <div class="breadcrumb">Trang chủ <span>/</span> <a href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php'); ?>">Báo cáo</a> <span>/</span> <strong><?php echo $escape($exportedEntry['name']); ?></strong></div>
                <div class="admin-profile">
                    <div class="avatar">QL</div>
                    <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
                </div>
            </header>
            <section class="page-content">
                <div class="exp-heading">
                    <a class="exp-back" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php'); ?>" aria-label="Quay lại danh sách báo cáo">←</a>
                    <h1><?php echo $escape($exportedEntry['name']); ?></h1>
                    <?php if ($exportedSnapshot !== null): ?>
                        <button type="button" class="exp-export-btn" onclick="document.getElementById('exp-export-modal').style.display='grid'">⇧ Xuất lại báo cáo</button>
                    <?php endif; ?>
                </div>
                <?php if ($exportedSnapshot === null): ?>
                    <p class="exp-note exp-warn">Báo cáo này được xuất trước khi hệ thống lưu lại dữ liệu nên không có bản lưu để hiển thị. Hãy xuất lại báo cáo từ danh sách báo cáo để lưu dữ liệu.</p>
                <?php else: ?>
                    <p class="exp-note">Đây là dữ liệu tại thời điểm xuất (<?php echo $escape($fmtDate((string) $exportedEntry['created_at'], 'd/m/Y H:i')); ?>), không thay đổi theo dữ liệu hiện tại của cửa hàng.</p>
                    <div class="exp-panel">
                        <dl class="exp-meta">
                            <div><dt>Loại báo cáo</dt><dd><?php echo $escape($exportedEntry['type_label']); ?></dd></div>
                            <div><dt>Báo cáo gốc</dt><dd><?php echo $escape($exportedSnapshot['title'] ?? ''); ?></dd></div>
                            <div><dt>Khoảng thời gian</dt><dd><?php echo $escape($fmtDate((string) ($exportedSnapshot['from'] ?? ''))); ?> – <?php echo $escape($fmtDate((string) ($exportedSnapshot['to'] ?? ''))); ?></dd></div>
                            <?php if (isset($exportedSnapshot['category'])): ?><div><dt>Danh mục</dt><dd><?php echo $escape($exportedSnapshot['category']); ?></dd></div><?php endif; ?>
                            <div><dt>Xuất lúc</dt><dd><?php echo $escape($fmtDate((string) $exportedEntry['created_at'], 'd/m/Y H:i')); ?></dd></div>
                            <div><dt>Người xuất</dt><dd><?php echo $escape($exportedEntry['exported_by'] ?: '—'); ?></dd></div>
                        </dl>
                    </div>
                    <?php if (!empty($exportedSnapshot['summary'])): ?>
                        <div class="exp-panel">
                            <div class="exp-summary">
                                <?php foreach ($exportedSnapshot['summary'] as [$kpiLabel, $kpiValue]): ?>
                                    <div class="exp-kpi"><span><?php echo $escape($kpiLabel); ?></span><strong><?php echo $escape($kpiValue); ?></strong></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="exp-panel">
                        <?php if (empty($exportedSnapshot['rows'])): ?>
                            <p class="exp-empty">Báo cáo này không có dữ liệu chi tiết tại thời điểm xuất.</p>
                        <?php else: ?>
                            <div class="exp-table-wrap">
                                <table class="exp-table">
                                    <thead><tr><?php foreach ($exportedSnapshot['headers'] ?? [] as $header): ?><th><?php echo $escape($header); ?></th><?php endforeach; ?></tr></thead>
                                    <tbody>
                                        <?php foreach ($exportedSnapshot['rows'] as $row): ?>
                                            <tr><?php foreach ($row as $cell): ?><td><?php echo $escape($cell); ?></td><?php endforeach; ?></tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <?php if ($exportedSnapshot !== null): ?>
        <div class="exp-modal" id="exp-export-modal" style="display:none">
            <section class="exp-modal-card" role="dialog" aria-modal="true" aria-labelledby="exp-export-title">
                <header><h2 id="exp-export-title">Xuất lại báo cáo</h2>
                    <button type="button" class="exp-modal-close" aria-label="Đóng" onclick="document.getElementById('exp-export-modal').style.display='none'">×</button></header>
                <p>Tệp Excel sẽ chứa đúng dữ liệu đã lưu tại thời điểm xuất trước đó.</p>
                <label for="exp-export-name">Tên báo cáo</label>
                <input type="text" id="exp-export-name" maxlength="100" value="<?php echo $escape($exportedEntry['name']); ?>" placeholder="Nhập tên báo cáo">
                <footer>
                    <button type="button" class="exp-cancel" onclick="document.getElementById('exp-export-modal').style.display='none'">Hủy</button>
                    <a class="exp-confirm" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . http_build_query(['view' => 'exported', 'id' => (int) $exportedEntry['id'], 'export' => 'xlsx'])); ?>" onclick="var n=document.getElementById('exp-export-name').value.trim();var u=new window.URL(this.href,window.location.href);if(n){u.searchParams.set('filename',n);}else{u.searchParams.delete('filename');}this.href=u.pathname+u.search;setTimeout(() => { document.getElementById('exp-export-modal').style.display='none'; }, 200);">Xuất báo cáo</a>
                </footer>
            </section>
        </div>
        <script>
            (function () {
                var modal = document.getElementById('exp-export-modal');
                modal.addEventListener('click', function (e) { if (e.target === modal) modal.style.display = 'none'; });
                document.addEventListener('keydown', function (e) { if (e.key === 'Escape') modal.style.display = 'none'; });
            })();
        </script>
    <?php endif; ?>
    </body>
    </html>
    <?php
else:
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $escape($salesTitle); ?> - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .report-panel { margin-bottom: 20px; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .report-panel h2 { margin: 0; color: var(--navy); font-size: 19px; }
        .report-panel-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
        .report-panel-header p { margin: 6px 0 0; color: var(--muted); font-size: 14px; }
        .report-filters { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; }
        .report-field { display: grid; gap: 6px; min-width: 150px; }
        .report-field label { color: #64758a; font-size: 14px; font-weight: 600; }
        .report-field select, .report-field input { min-height: 39px; padding: 8px 10px; border: 1px solid #dce2e9; border-radius: 7px; color: var(--text); background: #fff; }
        .report-filter-actions { display: flex; gap: 8px; }
        .report-button { display: inline-flex; min-height: 39px; align-items: center; justify-content: center; padding: 0 14px; border: 1px solid var(--navy); border-radius: 7px; background: var(--navy); color: #fff; font-weight: 600; cursor: pointer; }
        .report-button:hover { background: var(--navy-soft); color: #fff; }
        .report-button-secondary { border-color: #dce2e9; background: #fff; color: var(--navy); }
        .report-button-secondary:hover { background: #f5f7fa; color: var(--navy); }
        .report-error { margin: 0 0 18px; padding: 12px 15px; border: 1px solid #f3c4c4; border-radius: 8px; background: #fff2f2; color: #9c2f2f; }
        .report-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
        .report-stat { min-width: 0; padding: 18px; border: 1px solid #e9edf2; border-radius: 11px; background: #fff; box-shadow: var(--shadow); }
        .report-stat-label { color: #8190a0; font-size: 14px; }
        .report-stat-value { display: block; margin-top: 9px; color: var(--navy); font-size: clamp(20px, 2.2vw, 28px); font-weight: 700; overflow-wrap: anywhere; }
        .report-stat-note { display: block; margin-top: 7px; color: #98a2af; font-size: 13px; }
        .report-columns { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(270px, 1fr); gap: 18px; }
        .report-empty { margin: 12px 0 0; color: var(--muted); font-size: 15px; }
        .report-status-list { display: grid; }
        .report-status-row { display: flex; justify-content: space-between; gap: 12px; padding: 11px 0; border-bottom: 1px solid #eef1f5; color: #687789; font-size: 14px; }
        .report-status-row:last-child { border-bottom: 0; }
        .report-status-row strong { color: var(--navy); }
        .report-table-wrap { overflow-x: auto; }
        .report-table { width: 100%; border-collapse: collapse; }
        .report-table th, .report-table td { padding: 12px 10px; border-bottom: 1px solid #eef1f5; text-align: left; }
        .report-table th { color: #7d8a99; font-size: 13px; font-weight: 600; }
        .report-table td { color: #35465a; font-size: 14px; }
        .report-table td:last-child, .report-table th:last-child { text-align: right; }
        .report-product-name { min-width: 180px; font-weight: 600; }
        .report-detail-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; }
        .report-back-button { display: inline-grid; width: 40px; height: 40px; place-items: center; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #718198; font-size: 22px; }
        .report-detail-heading h1 { font-size: 26px; }
        .report-detail-actions { display: flex; align-items: center; gap: 18px; margin-left: auto; }
        .report-detail-actions a, .report-detail-actions button { padding: 0; border: 0; background: transparent; color: #34465b; cursor: pointer; font-size: 15px; font-weight: 600; }
        .report-detail-actions a:hover, .report-detail-actions button:hover { color: #0784f9; }
        .report-detail-actions form { margin: 0; }
        .report-detail-actions .report-save-button { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; padding: 0 20px; border: 0; border-radius: 8px; background: var(--navy); color: #fff; font-size: 16px; font-weight: 600; }
        .report-detail-actions .report-save-button:hover { background: var(--navy-soft); color: #fff; }
        .report-detail-filterbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 20px; }
        .report-detail-filterbar .report-field { min-width: 0; }
        .report-detail-filterbar .report-field select, .report-detail-filterbar .report-field input { min-height: 39px; }
        .report-detail-filterbar .report-field:first-of-type select { min-width: 140px; }
        .report-compare-label, .report-grain-label { display: inline-flex; min-height: 39px; align-items: center; padding: 0 12px; border: 1px solid #dce2e9; border-radius: 7px; background: #fff; color: #526276; font-size: 14px; white-space: nowrap; }
        .report-table-panel { overflow: hidden; padding: 18px 0 0; }
        .report-table-wrap { overflow-x: auto; }
        .report-table-detail { min-width: 720px; }
        .report-table-detail thead { background: #f2f4f6; }
        .report-table-detail th { padding: 12px 14px; color: #46576a; font-size: 14px; white-space: nowrap; }
        .report-table-detail td { padding: 12px 14px; color: #29394c; font-size: 15px; white-space: nowrap; }
        .report-table-detail tbody:not(.report-table-summary) tr:nth-child(even) { background: #f3f5f7; }
        .report-table-detail .report-table-summary td { background: #fff; font-weight: 700; }
        .report-table-detail .report-table-summary td:last-child { color: #17283d; }
        .report-table-detail tbody tr[hidden] { display: none; }
        .report-table-footer { display: flex; align-items: center; justify-content: flex-end; gap: 12px; padding: 12px 18px; color: #526276; font-size: 14px; }
        .report-table-footer select, .report-table-footer button { min-height: 32px; border: 1px solid #dce2e9; border-radius: 5px; background: #fff; color: #526276; }
        .report-table-footer button { min-width: 32px; cursor: pointer; }
        .report-table-footer button:disabled { opacity: .4; cursor: default; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; clip-path: inset(50%); }
        .report-export-modal { position: fixed; inset: 0; z-index: 99999; display: grid; place-items: center; padding: 16px; background: rgba(15, 25, 38, .48); }
        .report-export-card { width: min(100%, 480px); overflow: hidden; border-radius: 8px; background: #fff; box-shadow: 0 18px 60px rgba(0,0,0,.2); }
        .report-export-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px 12px; }
        .report-export-header h2 { margin: 0; color: #111827; font-size: 22px; font-weight: 700; }
        .report-export-close { border: 0; background: transparent; color: #9ca3af; cursor: pointer; font-size: 27px; line-height: 1; padding: 4px; }
        .report-export-close:hover { color: #4b5563; }
        .report-export-body { padding: 10px 24px 28px; color: #1f2937; font-size: 17px; line-height: 1.5; }
        .report-export-body p { margin: 0; }
        .report-export-name-label { display: block; margin: 16px 0 6px; font-size: 14px; font-weight: 600; color: #1f2937; }
        .report-export-name { width: 100%; height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font: inherit; font-size: 15px; box-sizing: border-box; }
        .report-export-name:focus { border-color: #0084ff; outline: none; box-shadow: 0 0 0 3px rgba(0,132,255,.15); }
        .report-export-footer { display: flex; justify-content: flex-end; align-items: center; gap: 12px; padding: 0 24px 24px; }
        .report-export-footer button, .report-export-footer a { display: inline-flex; min-width: 82px; height: 38px; align-items: center; justify-content: center; padding: 0 20px; border-radius: 6px; font-size: 16px; font-weight: 500; text-decoration: none; cursor: pointer; box-sizing: border-box; transition: all .15s ease; }
        .report-export-footer .report-export-cancel { border: 1px solid #0084ff; background: #fff; color: #0084ff; }
        .report-export-footer .report-export-cancel:hover { background: #f0f7ff; }
        .report-export-footer .report-export-confirm { border: 1px solid #0084ff; background: #0084ff; color: #fff; }
        .report-export-footer .report-export-confirm:hover { background: #0070d8; border-color: #0070d8; }
        @media (max-width: 1050px) { .report-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 820px) { .report-columns { grid-template-columns: 1fr; } }
        @media (max-width: 560px) {
            .report-panel { padding: 16px; }
            .report-stats { gap: 9px; }
            .report-stat { padding: 14px; }
            .report-filters, .report-field { width: 100%; }
            .report-filter-actions { width: 100%; }
            .report-filter-actions > * { flex: 1; }
            .report-panel-header { flex-direction: column; }
            .report-detail-heading { flex-wrap: wrap; }
            .report-detail-actions { width: 100%; justify-content: flex-end; }
            .report-table-footer { justify-content: flex-start; flex-wrap: wrap; }
        }
    </style>
<link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.css?v=' . (int) @filemtime(APP_ROOT . '/admin/bao_cao.css')); ?>">
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <a href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php'); ?>">Báo cáo</a> <span>/</span> <strong><?php echo $escape($salesTitle); ?></strong></div>
            <div class="admin-profile">
                <div class="avatar">QL</div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small>Quản lý</small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="report-detail-heading">
                <a class="report-back-button" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php'); ?>" aria-label="Quay lại danh sách báo cáo">←</a>
                <h1><?php echo $escape($salesTitle); ?></h1>
                <div class="report-detail-actions">
                    <?php if ($reportError === ''): ?>
                        <button type="button" onclick="document.getElementById('export-confirmation').style.display='grid'">⇧ Xuất báo cáo</button>
                    <?php endif; ?>
                    <?php if ($currentSavedReport): ?><form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($reportsCsrf); ?>">
                        <input type="hidden" name="action" value="save_report_state">
                        <input type="hidden" name="report_id" value="<?php echo $savedReportId ? (int) $savedReportId : ''; ?>">
                        <input type="hidden" name="report_type" value="sales">
                        <input type="hidden" name="title" value="<?php echo $escape($salesTitle); ?>">
                        <input type="hidden" name="report_config" value="<?php echo $escape(json_encode(['view' => 'sales', 'section' => $salesSection, 'period' => 'custom', 'from' => $startDate, 'to' => $endDate, 'category_id' => $categoryId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
                        <button type="submit" class="report-save-button">▣ Lưu báo cáo</button>
                    </form><?php endif; ?>
                </div>
            </div>
            <?php if (is_array($reportNotice) && ($reportNotice['message'] ?? '') !== ''): ?><p class="report-notice<?php echo ($reportNotice['type'] ?? '') === 'error' ? ' error' : ''; ?>" role="status"><?php echo $escape($reportNotice['message']); ?></p><?php endif; ?>
            <?php if ($reportError !== ''): ?><p class="report-error" role="alert"><?php echo $escape($reportError); ?></p><?php endif; ?>
            <form method="get" class="report-detail-filterbar">
                <input type="hidden" name="view" value="sales">
                <input type="hidden" name="section" value="<?php echo $escape($salesSection); ?>">
                <?php if ($savedReportId): ?><input type="hidden" name="report_id" value="<?php echo (int) $savedReportId; ?>"><?php endif; ?>
                <span class="report-compare-label">Theo ngày tạo đơn</span>
                <div class="report-field">
                    <label class="sr-only" for="report-period">Khoảng thời gian</label>
                    <select name="period" id="report-period">
                        <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                        <option value="yesterday" <?php echo $period === 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                        <option value="last_7" <?php echo $period === 'last_7' ? 'selected' : ''; ?>>7 ngày qua (<?php echo $escape($selectedPeriodLabel); ?>)</option>
                        <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                        <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                        <option value="last_30" <?php echo $period === 'last_30' ? 'selected' : ''; ?>>30 ngày gần nhất</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Tùy chọn ngày</option>
                    </select>
                </div>
                <div class="report-field">
                    <label class="sr-only" for="report-from">Từ ngày</label>
                    <input type="date" name="from" id="report-from" value="<?php echo $escape($startDate); ?>">
                </div>
                <div class="report-field">
                    <label class="sr-only" for="report-to">Đến ngày</label>
                    <input type="date" name="to" id="report-to" value="<?php echo $escape($endDate); ?>">
                </div>
                <div class="report-field">
                    <label class="sr-only" for="report-category">Danh mục</label>
                    <select name="category_id" id="report-category">
                        <option value="0">Tất cả danh mục</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo (int) $id; ?>" <?php echo $categoryId === $id ? 'selected' : ''; ?>><?php echo $escape($name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="report-button" type="submit">Áp dụng</button>
                <a class="report-button report-button-secondary" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . http_build_query(array_filter(['view' => 'sales', 'section' => $salesSection, 'report_id' => $savedReportId]))); ?>">Đặt lại</a>
            </form>
            <section class="report-panel report-table-panel" id="<?php echo $escape($salesSectionTargets[$salesSection]); ?>">
                <?php if ($detailRows === []): ?>
                    <p class="report-empty"><?php echo $salesSection === 'low_stock' ? 'Hiện không có sản phẩm nào sắp hết hàng (tồn kho từ 1 đến ' . \App\Services\Reports\BaoCaoDoanhThu::LOW_STOCK_THRESHOLD . ').' : 'Không có dữ liệu trong khoảng thời gian này.'; ?></p>
                <?php else: ?>
                    <div class="report-table-wrap">
                        <table class="report-table report-table-detail" id="report-data-table">
                            <thead><tr><?php foreach ($detailHeaders as $header): ?><th><?php echo $escape($header); ?> <span aria-hidden="true">↕</span></th><?php endforeach; ?></tr></thead>
                            <?php if ($salesSection === 'revenue'): ?>
                                <tbody class="report-table-summary">
                                    <tr>
                                        <td><?php echo $escape($selectedPeriodLabel); ?></td>
                                        <td><?php echo number_format((int) $summary['total_orders']); ?></td>
                                        <td><?php echo number_format((int) $summary['items_sold']); ?></td>
                                        <td><?php echo number_format((float) $summary['revenue'], 0, ',', '.'); ?> đ</td>
                                    </tr>
                                </tbody>
                            <?php endif; ?>
                            <tbody>
                                <?php foreach ($detailRows as $row): ?>
                                    <tr><?php foreach ($row as $cell): ?><td><?php echo $escape($cell); ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="report-table-footer">
                        <span id="table-range"></span>
                        <label for="table-page-size">Hiển thị</label>
                        <select id="table-page-size"><option>20</option><option selected>50</option><option>100</option></select>
                        <span>Kết quả</span>
                        <button type="button" id="table-prev" aria-label="Trang trước">‹</button>
                        <span id="table-page"></span>
                        <button type="button" id="table-next" aria-label="Trang sau">›</button>
                    </div>
                <?php endif; ?>
            </section>
        </section>
    </main>
</div>
<?php if ($reportError === ''): ?>
    <div class="report-export-modal" id="export-confirmation" style="display:none">
        <section class="report-export-card" role="dialog" aria-modal="true" aria-labelledby="export-confirmation-title" tabindex="-1">
            <header class="report-export-header">
                <h2 id="export-confirmation-title">Xuất báo cáo</h2>
                <button class="report-export-close" type="button" aria-label="Đóng" onclick="document.getElementById('export-confirmation').style.display='none'">×</button>
            </header>
            <div class="report-export-body">
                <p>Bạn có muốn xuất báo cáo? Báo cáo này sẽ được xuất ra file excel</p>
                <label class="report-export-name-label" for="sales-export-name">Tên báo cáo</label>
                <input class="report-export-name" type="text" id="sales-export-name" maxlength="100" value="<?php echo $escape($salesTitle); ?>" placeholder="Nhập tên báo cáo">
            </div>
            <footer class="report-export-footer">
                <button type="button" class="report-export-cancel" onclick="document.getElementById('export-confirmation').style.display='none'">Hủy</button>
                <a class="report-export-confirm" id="sales-export-xlsx" href="<?php echo $escape($appBaseUrl . '/admin/bao_cao.php?' . $exportQueryXlsx); ?>" onclick="var n=document.getElementById('sales-export-name').value.trim();var u=new window.URL(this.href,window.location.href);if(n){u.searchParams.set('filename',n);}else{u.searchParams.delete('filename');}this.href=u.pathname+u.search;setTimeout(() => { document.getElementById('export-confirmation').style.display='none'; }, 200);">Xuất báo cáo</a>
            </footer>
        </section>
    </div>
<?php endif; ?>
<script>
    const salesExportModal = document.getElementById('export-confirmation');
    if (salesExportModal) {
        salesExportModal.addEventListener('click', (e) => {
            if (e.target === salesExportModal) salesExportModal.style.display = 'none';
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && salesExportModal.style.display !== 'none') {
                salesExportModal.style.display = 'none';
            }
        });
    }

    const syncSalesExportLinks = () => {
        const periodVal = document.getElementById('report-period')?.value || 'this_month';
        const fromVal = document.getElementById('report-from')?.value || '';
        const toVal = document.getElementById('report-to')?.value || '';
        const catVal = document.getElementById('report-category')?.value || '0';
        const xlsxBtn = document.getElementById('sales-export-xlsx');
        if (xlsxBtn) {
            const u = new URL(xlsxBtn.href, window.location.origin);
            if (periodVal) u.searchParams.set('period', periodVal);
            if (fromVal) u.searchParams.set('from', fromVal);
            if (toVal) u.searchParams.set('to', toVal);
            if (catVal) u.searchParams.set('category_id', catVal);
            xlsxBtn.href = u.pathname + u.search;
        }
    };

    const reportPeriod = document.getElementById('report-period');
    reportPeriod.addEventListener('change', () => {
        syncSalesExportLinks();
        if (reportPeriod.value === 'custom') return;
        reportPeriod.form.requestSubmit();
    });
    document.getElementById('report-from').addEventListener('change', () => {
        reportPeriod.value = 'custom';
        syncSalesExportLinks();
    });
    document.getElementById('report-to').addEventListener('change', () => {
        reportPeriod.value = 'custom';
        syncSalesExportLinks();
    });
    document.getElementById('report-category').addEventListener('change', (event) => {
        syncSalesExportLinks();
        event.currentTarget.form.requestSubmit();
    });

    const reportTable = document.getElementById('report-data-table');
    if (reportTable) {
        const allRows = Array.from(reportTable.querySelectorAll('tbody:not(.report-table-summary) tr'));
        const pageSize = document.getElementById('table-page-size');
        const pageRange = document.getElementById('table-range');
        const pageLabel = document.getElementById('table-page');
        const previousButton = document.getElementById('table-prev');
        const nextButton = document.getElementById('table-next');
        let currentPage = 0;
        const renderPage = () => {
            const size = Number(pageSize.value);
            const pageCount = Math.max(1, Math.ceil(allRows.length / size));
            currentPage = Math.min(currentPage, pageCount - 1);
            const start = currentPage * size;
            const end = Math.min(start + size, allRows.length);
            const visibleRows = new Set(allRows.slice(start, end));
            allRows.forEach((row) => { row.hidden = !visibleRows.has(row); });
            pageRange.textContent = `Từ ${start + 1} đến ${end} trên tổng ${allRows.length}`;
            pageLabel.textContent = `${currentPage + 1}/${pageCount}`;
            previousButton.disabled = currentPage === 0;
            nextButton.disabled = currentPage >= pageCount - 1;
        };
        pageSize.addEventListener('change', () => { currentPage = 0; renderPage(); });
        previousButton.addEventListener('click', () => { currentPage--; renderPage(); });
        nextButton.addEventListener('click', () => { currentPage++; renderPage(); });
        renderPage();
    }

</script>
</body>
</html>
<?php endif; ?>
