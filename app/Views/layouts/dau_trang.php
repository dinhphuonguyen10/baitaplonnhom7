<?php
// Layout: phần đầu trang cửa hàng. Dữ liệu (tài khoản, menu danh mục, logo...) do
// BaseClientController::shareLayoutData() cung cấp; View không tự truy vấn.
use App\Core\View;
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : "ÉCRIN JEWELRY"; ?></title>
    <link rel="stylesheet" href="<?php echo View::e(View::asset('assets/style.css')); ?>">
    <script>window.APP_BASE_URL = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script>window.CUSTOMER_USERNAME = <?php echo json_encode($customerUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.ACCOUNT_USERNAME = <?php echo json_encode($accountUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.ACCOUNT_ROLE = <?php echo json_encode($accountRole, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.ASSET_VERSION = <?php echo json_encode((string)time()); ?>;</script>
</head>

<body class="<?php echo View::e($bodyClass); ?>">

    <!-- HEADER WRAPPER (STICKY) -->
    <div class="sticky-header-wrapper">
        <!-- HEADER -->
        <header class="header">

        <!-- Logo ảnh -->
        <div class="logo">
            <a href="<?php echo View::e($appBaseUrl . '/index.php'); ?>" aria-label="Écrin Jewelry - Trang chủ">
                <img src="<?php echo View::e($headerLogoUrl); ?>" alt="Écrin Jewelry" class="logo-img">
                <span class="logo-wordmark"><strong>ÉCRIN</strong><small>JEWELRY</small></span>
            </a>
        </div>
        <!-- Ô tìm kiếm -->
        <div class="search-wrapper">
            <input type="search" placeholder="Bạn đang tìm món trang sức nào?" class="search" aria-label="Tìm kiếm sản phẩm">
        </div>

        <!-- Đăng nhập / đăng ký -->
        <div class="auth-cart-wrapper" style="display: flex; align-items: center; gap: 15px;">
            <div class="auth" id="auth-container">
                <?php if ($accountRole === 'admin'): ?>
                    <span style="font-weight: bold; color: var(--navy);">Xin chào, <?php echo View::e($accountUsername); ?></span> |
                    <a class="auth-btn" href="<?php echo View::e($appBaseUrl . '/admin/index.php'); ?>">Quản trị</a> |
                    <a class="auth-btn" href="<?php echo View::e($appBaseUrl . '/admin/dang_xuat.php'); ?>">Đăng xuất</a>
                <?php elseif ($accountRole === 'customer'): ?>
                    <a class="auth-btn profile-link" href="<?php echo View::e($appBaseUrl . '/client/ho_so.php'); ?>" title="Hồ sơ của tôi">
                        <span>Xin chào, <?php echo View::e($accountUsername); ?></span>
                        <svg class="profile-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"></path></svg>
                    </a>
                <?php else: ?>
                    <span class="auth-btn register-trigger" style="cursor: pointer;">Đăng ký</span> /
                    <span class="auth-btn login-trigger" style="cursor: pointer;">Đăng nhập</span>
                <?php endif; ?>
            </div>

            <div class="wishlist-wrapper">
                <a class="wishlist-button" href="<?php echo View::e($appBaseUrl . '/client/yeu_thich.php'); ?>" aria-label="Sản phẩm yêu thích" title="Sản phẩm yêu thích">
                    <svg class="wishlist-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                    <span class="wishlist-text">Yêu thích</span>
                    <span class="wishlist-count" <?php echo $initialWishlistCount > 0 ? '' : 'style="display: none;"'; ?>><?php echo $initialWishlistCount; ?></span>
                </a>
            </div>

            <div class="cart-wrapper">
                <a class="cart-button" href="<?php echo View::e($appBaseUrl . '/client/gio_hang.php'); ?>" aria-label="Mở giỏ hàng">
                    <svg class="cart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.4L22 8H6" /><circle cx="10" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /></svg>
                    <span class="cart-text">Giỏ hàng</span>
                    <span class="cart-count" <?php echo $isCustomerLoggedIn ? '' : 'style="display:none;" aria-hidden="true"'; ?>><?php echo $isCustomerLoggedIn ? '0' : ''; ?></span>
                </a>
            </div>
        </div>


    </header>

    <!-- MENU -->
    <nav class="menu" aria-label="Điều hướng chính">
        <a href="<?php echo View::e($appBaseUrl . '/index.php'); ?>"<?php echo $navCurrentPage === 'index.php' ? ' class="is-active" aria-current="page"' : ''; ?>>Trang chủ</a>
        <div class="dropdown">
            <a href="javascript:void(0)" class="dropdown-toggle<?php echo in_array($navCurrentPage, $navCategoryPages, true) ? ' is-active' : ''; ?>"<?php echo in_array($navCurrentPage, $navCategoryPages, true) ? ' aria-current="page"' : ''; ?>>Danh mục sản phẩm ▾</a>
            <div class="dropdown-menu">
                <?php if (!empty($navCategories)): ?>
                    <?php foreach ($navCategoryLinks as $catItem): ?>
                        <div class="dropdown-item">
                            <a href="<?php echo View::e($catItem['href']); ?>"><?php echo View::e($catItem['name']); ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isCustomerLoggedIn): ?>
            <a href="<?php echo View::e($appBaseUrl . '/client/tracuudh.php'); ?>" class="nav-my-orders-link<?php echo $navCurrentPage === 'tracuudh.php' ? ' is-active' : ''; ?>"<?php echo $navCurrentPage === 'tracuudh.php' ? ' aria-current="page"' : ''; ?>>Đơn hàng của tôi</a>
        <?php endif; ?>
        <a href="<?php echo View::e($appBaseUrl . '/client/htch.php'); ?>"<?php echo $navCurrentPage === 'htch.php' ? ' class="is-active" aria-current="page"' : ''; ?>>Hệ thống cửa hàng</a>
        <a href="<?php echo View::e($appBaseUrl . '/client/tro_chuyen.php'); ?>" class="nav-chat-link<?php echo $navCurrentPage === 'tro_chuyen.php' ? ' is-active' : ''; ?>"<?php echo $navCurrentPage === 'tro_chuyen.php' ? ' aria-current="page"' : ''; ?>>
            <svg class="nav-chat-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"></path>
            </svg>Chat với cửa hàng
        </a>
    </nav>
    </div>
    <div class="sticky-header-spacer" aria-hidden="true"></div>
