<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$appBaseUrl = preg_replace('~/(?:client|admin|api)(?:/.*)?$~', '', $scriptDirectory);
$appBaseUrl = rtrim($appBaseUrl, '/');
$bodyClass = isset($bodyClass) && is_string($bodyClass) ? $bodyClass : '';
$customerUsername = ($_SESSION['role'] ?? '') === 'customer' && isset($_SESSION['user_id'], $_SESSION['username'])
    ? (string) $_SESSION['username']
    : null;
$accountUsername = $customerUsername;
$accountRole = $customerUsername !== null ? 'customer' : null;
$isCustomerLoggedIn = $customerUsername !== null && $customerUsername !== '';

// Lấy danh mục sản phẩm từ CSDL để đồng nhất với Admin
$navCategories = [];
if (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $navCatRes = $conn->query("SELECT id, name, slug FROM categories ORDER BY id ASC");
        if ($navCatRes) {
            while ($cRow = $navCatRes->fetch_assoc()) {
                $navCategories[] = $cRow;
            }
        }
    }
}

$initialWishlistCount = 0;
if ($isCustomerLoggedIn && isset($_SESSION['user_id']) && isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
    $wlUid = (int)$_SESSION['user_id'];
    $wlRes = $conn->query("SELECT COUNT(*) AS total FROM wishlists WHERE user_id = {$wlUid}");
    if ($wlRes && ($wlRow = $wlRes->fetch_assoc())) {
        $initialWishlistCount = (int)$wlRow['total'];
    }
}

/* ── Đọc logo tùy chỉnh nếu có, fallback về logo mặc định ── */
$_logoConfigFile = __DIR__ . '/../storage/banner/logo.json';
$_logoSrc = 'assets/banner/logo.png';
if (is_file($_logoConfigFile)) {
    $_logoData = json_decode((string) file_get_contents($_logoConfigFile), true);
    if (is_array($_logoData) && !empty($_logoData['src'])) {
        $_logoSrc = (string) $_logoData['src'];
    }
}
$headerLogoUrl = $appBaseUrl . '/' . ltrim($_logoSrc, '/');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : "ÉCRIN JEWELRY"; ?></title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($appBaseUrl . '/assets/style.css?v=' . filemtime(__DIR__ . '/../assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <script>window.APP_BASE_URL = <?php echo json_encode($appBaseUrl, JSON_UNESCAPED_SLASHES); ?>;</script>
    <script>window.CUSTOMER_USERNAME = <?php echo json_encode($customerUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.ACCOUNT_USERNAME = <?php echo json_encode($accountUsername, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.ACCOUNT_ROLE = <?php echo json_encode($accountRole, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;</script>
    <script>window.ASSET_VERSION = <?php echo json_encode((string)time()); ?>;</script>
</head>

<body class="<?php echo htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- HEADER WRAPPER (STICKY) -->
    <div class="sticky-header-wrapper">
        <!-- HEADER -->
        <header class="header">

        <!-- Logo ảnh -->
        <div class="logo">
            <a href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Écrin Jewelry - Trang chủ">
                <img src="<?php echo htmlspecialchars($headerLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="Écrin Jewelry" class="logo-img">
                <span class="logo-wordmark"><strong>ÉCRIN</strong><small>JEWELRY</small></span>
            </a>
        </div>
        <!-- Ô tìm kiếm -->
        <input type="search" placeholder="Bạn đang tìm món trang sức nào?" class="search" aria-label="Tìm kiếm sản phẩm">

        <!-- Đăng nhập / đăng ký -->
        <div class="auth-cart-wrapper" style="display: flex; align-items: center; gap: 15px;">
            <div class="auth" id="auth-container">
                <?php if ($accountRole === 'admin'): ?>
                    <span style="font-weight: bold; color: var(--navy);">Xin chào, <?php echo htmlspecialchars($accountUsername, ENT_QUOTES, 'UTF-8'); ?></span> |
                    <a class="auth-btn" href="<?php echo htmlspecialchars($appBaseUrl . '/admin/index.php', ENT_QUOTES, 'UTF-8'); ?>">Quản trị</a> |
                    <a class="auth-btn" href="<?php echo htmlspecialchars($appBaseUrl . '/admin/logout.php', ENT_QUOTES, 'UTF-8'); ?>">Đăng xuất</a>
                <?php elseif ($accountRole === 'customer'): ?>
                    <span style="font-weight: bold; color: var(--navy);">Xin chào, <?php echo htmlspecialchars($accountUsername, ENT_QUOTES, 'UTF-8'); ?></span> |
                    <span class="auth-btn logout-btn" style="cursor: pointer;">Đăng xuất</span>
                <?php else: ?>
                    <span class="auth-btn register-trigger" style="cursor: pointer;">Đăng ký</span> /
                    <span class="auth-btn login-trigger" style="cursor: pointer;">Đăng nhập</span>
                <?php endif; ?>
            </div>

            <div class="wishlist-wrapper">
                <a class="wishlist-button" href="<?php echo htmlspecialchars($appBaseUrl . '/client/wishlist.php', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Sản phẩm yêu thích" title="Sản phẩm yêu thích">
                    <svg class="wishlist-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg>
                    <span class="wishlist-text">Yêu thích</span>
                    <span class="wishlist-count" <?php echo $initialWishlistCount > 0 ? '' : 'style="display: none;"'; ?>><?php echo $initialWishlistCount; ?></span>
                </a>
            </div>

            <div class="cart-wrapper">
                <a class="cart-button" href="<?php echo htmlspecialchars($appBaseUrl . '/client/cart.php', ENT_QUOTES, 'UTF-8'); ?>" aria-label="Mở giỏ hàng">
                    <svg class="cart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.1 10.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 1.9-1.4L22 8H6" /><circle cx="10" cy="20" r="1.3" /><circle cx="18" cy="20" r="1.3" /></svg>
                    <span class="cart-text">Giỏ hàng</span>
                    <span class="cart-count" <?php echo $isCustomerLoggedIn ? '' : 'style="display:none;" aria-hidden="true"'; ?>><?php echo $isCustomerLoggedIn ? '0' : ''; ?></span>
                </a>
            </div>
        </div>


    </header>

    <!-- MENU -->
    <nav class="menu" aria-label="Điều hướng chính">
        <a href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>" style="font-weight: bold; color:#0d1b2a">Trang chủ</a>
        <div class="dropdown">
            <a href="javascript:void(0)" class="dropdown-toggle">Danh mục sản phẩm ▾</a>
            <div class="dropdown-menu">
                <?php if (!empty($navCategories)): ?>
                    <?php 
                    $pageMap = [
                        'bong-tai' => '/client/bong_tai.php',
                        'day-chuyen' => '/client/day_chuyen.php',
                        'nhan' => '/client/nhan.php',
                        'vong-tay' => '/client/vong_tay.php',
                        'set-qua-tang' => '/client/category.php?slug=set-qua-tang',
                    ];
                    foreach ($navCategories as $catItem): 
                        if (in_array($catItem['slug'], ['combo', 'suggest', 'featured'], true)) continue;
                        $catHref = isset($pageMap[$catItem['slug']]) 
                            ? ($appBaseUrl . $pageMap[$catItem['slug']])
                            : ($appBaseUrl . '/client/category.php?slug=' . urlencode($catItem['slug']));
                    ?>
                        <div class="dropdown-item">
                            <a href="<?php echo htmlspecialchars($catHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($catItem['name'], ENT_QUOTES, 'UTF-8'); ?></a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="dropdown-item">
                        <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/bong_tai.php', ENT_QUOTES, 'UTF-8'); ?>">Bông tai</a>
                    </div>
                    <div class="dropdown-item">
                        <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/day_chuyen.php', ENT_QUOTES, 'UTF-8'); ?>">Dây chuyền</a>
                    </div>
                    <div class="dropdown-item">
                        <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/nhan.php', ENT_QUOTES, 'UTF-8'); ?>">Nhẫn</a>
                    </div>
                     <div class="dropdown-item">
                        <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/vong_tay.php', ENT_QUOTES, 'UTF-8'); ?>">Vòng tay</a>
                    </div>
                     <div class="dropdown-item">
                         <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/category.php?slug=set-qua-tang', ENT_QUOTES, 'UTF-8'); ?>">Set quà tặng</a>
                     </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isCustomerLoggedIn): ?>
            <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/tracuudh.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-my-orders-link">Đơn hàng của tôi</a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/htch.php', ENT_QUOTES, 'UTF-8'); ?>">Hệ thống cửa hàng</a>
        <a href="<?php echo htmlspecialchars($appBaseUrl . '/client/chat.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-chat-link">
            <svg class="nav-chat-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: -2px; margin-right: 5px;">
                <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"></path>
            </svg>Chat với cửa hàng
        </a>
    </nav>
    </div>
    <div class="sticky-header-spacer" aria-hidden="true"></div>
