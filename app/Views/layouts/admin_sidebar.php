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
        <a href="<?php echo $escape($appBaseUrl . '/admin/logout.php'); ?>" class="logout"><span>⇥</span> Đăng xuất</a>
    </div>
</aside>
