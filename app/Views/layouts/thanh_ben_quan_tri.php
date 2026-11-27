<?php
// Layout: sidebar quản trị. Dữ liệu ($sideMenu đã lọc theo quyền, $currentScript, $appBaseUrl, $escape)
// do BaseAdminController::shareLayoutData() cung cấp.
?>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="<?php echo $escape($appBaseUrl . '/index.php'); ?>" aria-label="ÉCRIN trang chủ">
        <img class="brand-logo" src="<?php echo $escape($appBaseUrl . '/admin/assets/ecrin-logo.png'); ?>" alt="ÉCRIN Jewelry">
    </a>
    <nav class="side-nav" aria-label="Điều hướng giao diện quản trị">
        <?php foreach ($sideMenu as $item): ?>
            <?php $active   = ($currentScript === $item['file']); ?>
            <a href="<?php echo $escape($appBaseUrl . $item['href']); ?>" class="nav-item<?php echo $active ? ' active' : ''; ?>">
                <span class="nav-icon"><?php echo $escape($item['icon']); ?></span>
                <span><?php echo $escape($item['label']); ?></span>
                <?php if ($active): ?><span class="active-dot"></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <a href="<?php echo $escape($appBaseUrl . '/admin/dang_xuat.php'); ?>" class="logout"><span>⇥</span> Đăng xuất</a>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
<script>
// Điện thoại/tablet: thêm nút ☰ vào topbar để mở/đóng sidebar (CSS ẩn sidebar ở ≤820px).
(function () {
    function init() {
        var sidebar = document.getElementById('sidebar');
        var topbar = document.querySelector('.topbar');
        var backdrop = document.getElementById('sidebar-backdrop');
        if (!sidebar || !topbar || !backdrop) return;

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'menu-toggle';
        btn.setAttribute('aria-label', 'Mở menu');
        btn.setAttribute('aria-controls', 'sidebar');
        btn.textContent = '☰';
        topbar.insertBefore(btn, topbar.firstChild);

        function setOpen(open) {
            sidebar.classList.toggle('is-open', open);
            backdrop.classList.toggle('is-open', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        btn.addEventListener('click', function () { setOpen(!sidebar.classList.contains('is-open')); });
        backdrop.addEventListener('click', function () { setOpen(false); });
        sidebar.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
</script>
