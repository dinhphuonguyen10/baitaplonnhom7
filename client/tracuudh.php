<?php
$pageTitle = "Đơn hàng của tôi - ÉCRIN JEWELRY";
$bodyClass = 'my-orders-page';
include __DIR__ . '/../include/header.php';

// Yêu cầu đăng nhập
if (!$isCustomerLoggedIn) {
    echo '<main style="min-height:60vh;display:flex;align-items:center;justify-content:center;">
        <div style="text-align:center;padding:40px;">
            <h2 style="color:#1a153a;margin-bottom:16px;">Vui lòng đăng nhập</h2>
            <p style="color:#666;margin-bottom:24px;">Bạn cần đăng nhập để xem đơn hàng của mình.</p>
            <button onclick="promptCustomerLogin()" style="background:var(--navy);color:#fff;border:none;padding:14px 36px;border-radius:12px;font-size:1rem;font-weight:700;cursor:pointer;">Đăng nhập ngay</button>
        </div>
    </main>';
    include __DIR__ . '/../include/footer.php';
    exit;
}
if (!isset($_SESSION['order_cancel_csrf']) || !is_string($_SESSION['order_cancel_csrf'])) {
    $_SESSION['order_cancel_csrf'] = bin2hex(random_bytes(32));
}
?>

<style>
/* ===== MY ORDERS PAGE - Shopee / TikTok Style ===== */
.my-orders-page .header .search { display: none; }

.my-orders-page {
    background:
        radial-gradient(circle at 12% 0%, rgba(196,164,90,.10), transparent 32%),
        var(--cream, #faf9f6);
}

.my-orders-page main {
    min-height: calc(100vh - 260px);
    padding: 24px 20px 56px;
}

.orders-wrapper {
    max-width: 960px;
    margin: 0 auto;
}

/* Breadcrumb */
.orders-breadcrumb {
    margin-bottom: 20px;
}

.orders-filter-bar {
    display: flex;
    align-items: end;
    gap: 12px;
    flex-wrap: wrap;
    background: #fff;
    padding: 14px;
    margin-bottom: 16px;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,.04);
}

.orders-filter-field {
    display: grid;
    gap: 5px;
    color: #555;
    font-size: .82rem;
    font-weight: 700;
}

.orders-filter-field input {
    min-height: 40px;
    min-width: 150px;
    border: 1px solid #ddd;
    border-radius: 9px;
    padding: 8px 10px;
    color: var(--navy, #0b2033);
    font: inherit;
}

.orders-filter-field--search input { min-width: 220px; }

.orders-filter-reset {
    min-height: 40px;
    padding: 8px 14px;
    border: 1px solid #d6dbe0;
    border-radius: 9px;
    background: #fff;
    color: var(--navy, #0b2033);
    font-weight: 700;
    cursor: pointer;
}

.orders-filter-reset:hover { border-color: var(--gold, #c4a45a); }

/* Page Header */
.orders-page-header {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
}

.orders-page-header h1 {
    font-size: clamp(1.6rem, 3.5vw, 2.2rem);
    color: var(--navy, #0b2033);
    margin: 0;
    letter-spacing: -.03em;
}

.orders-page-header .orders-icon {
    width: 38px;
    height: 38px;
    background: var(--navy, #0b2033);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}

/* ===== TABS ===== */
.orders-tabs {
    display: flex;
    background: #fff;
    border-radius: 16px 16px 0 0;
    box-shadow: 0 2px 12px rgba(0,0,0,.04);
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
    position: sticky;
    top: 0;
    z-index: 10;
    border-bottom: 2px solid #f0f0f0;
}

.orders-tabs::-webkit-scrollbar { display: none; }

.orders-tab {
    flex: 1;
    min-width: 120px;
    padding: 16px 12px;
    text-align: center;
    font-size: .9rem;
    font-weight: 600;
    color: #888;
    cursor: pointer;
    border: none;
    background: none;
    position: relative;
    transition: color .25s;
    white-space: nowrap;
}

.orders-tab:hover {
    color: var(--navy, #0b2033);
    background: rgba(196,164,90,.04);
}

.orders-tab.active {
    color: var(--navy, #0b2033);
}

.orders-tab.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 20%;
    right: 20%;
    height: 3px;
    background: linear-gradient(90deg, var(--gold, #c4a45a), var(--navy, #0b2033));
    border-radius: 3px 3px 0 0;
}

.orders-tab .tab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    font-size: .72rem;
    font-weight: 700;
    background: #eee;
    color: #888;
    border-radius: 10px;
    margin-left: 6px;
    vertical-align: middle;
}

.orders-tab.active .tab-badge {
    background: var(--navy, #0b2033);
    color: #fff;
}

/* ===== ORDERS CONTAINER ===== */
.orders-content {
    background: #fff;
    border-radius: 0 0 16px 16px;
    min-height: 300px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,.05);
}

/* Loading */
.orders-loading {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.orders-loading .spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #eee;
    border-top-color: var(--gold, #c4a45a);
    border-radius: 50%;
    animation: spin .7s linear infinite;
    margin: 0 auto 16px;
}

@keyframes spin { to { transform: rotate(360deg); } }

/* Empty state */
.orders-empty {
    text-align: center;
    padding: 60px 20px;
}

.orders-empty-icon {
    font-size: 3.5rem;
    margin-bottom: 16px;
    opacity: .6;
}

.orders-empty h3 {
    color: #555;
    margin: 0 0 8px;
    font-size: 1.1rem;
}

.orders-empty p {
    color: #999;
    margin: 0 0 20px;
    font-size: .9rem;
}

.orders-empty .btn-shop-now {
    display: inline-block;
    background: var(--navy, #0b2033);
    color: #fff;
    padding: 12px 32px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 700;
    font-size: .9rem;
    transition: .2s;
}

.orders-empty .btn-shop-now:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(11,32,51,.18);
}

/* ===== ORDER CARD ===== */
.order-card {
    border: 1px solid #f0f0f0;
    border-radius: 14px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: box-shadow .2s, transform .15s;
}

.order-card:hover {
    box-shadow: 0 4px 18px rgba(0,0,0,.06);
    transform: translateY(-1px);
}

/* Card Header */
.order-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #fafafa;
    border-bottom: 1px solid #f0f0f0;
}

.order-card-shop {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: var(--navy, #0b2033);
    font-size: .9rem;
}

.order-card-shop .shop-icon {
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.order-status-badge {
    font-size: .78rem;
    font-weight: 700;
    padding: 5px 14px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.status-processing {
    background: #fff3e0;
    color: #e65100;
}

.status-shipping {
    background: #e3f2fd;
    color: #1565c0;
}

.status-delivered {
    background: #e8f5e9;
    color: #2e7d32;
}

.status-cancelled {
    background: #fce4ec;
    color: #c62828;
}

/* Product rows */
.order-product-row {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px 18px;
    border-bottom: 1px solid #f8f8f8;
}

.order-product-row:last-of-type {
    border-bottom: none;
}

.order-product-img {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #f0f0f0;
    flex-shrink: 0;
    background: #fafafa;
}

.order-product-info {
    flex: 1;
    min-width: 0;
}

.order-product-info .product-name {
    font-weight: 600;
    color: #333;
    font-size: .92rem;
    margin: 0 0 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.order-product-info .product-variant {
    font-size: .8rem;
    color: #999;
    margin: 0 0 4px;
}

.order-product-info .product-qty {
    font-size: .82rem;
    color: #777;
}

.order-product-price {
    font-weight: 700;
    color: var(--navy, #0b2033);
    font-size: .95rem;
    white-space: nowrap;
}

.order-review-link, .order-reviewed-label {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 92px; padding: 8px 12px; border-radius: 9px;
    font-size: .78rem; font-weight: 700; text-decoration: none; white-space: nowrap;
    transition: all .2s ease;
}
.order-review-link { color: #fff; background: #c94f5c; border: 1px solid #c94f5c; }
.order-review-link:hover { background: #ad3d4a; transform: translateY(-1px); box-shadow: 0 2px 6px rgba(201,79,92,.3); }
.order-reviewed-label { color: #278553; background: #edf8f1; border: 1px solid #bce6cc; cursor: pointer; }
a.order-reviewed-label:hover { color: #fff; background: #278553; border-color: #278553; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(39,133,83,.25); }
.order-product-review-action { margin-left: auto; }
@media (max-width: 560px) {
    .order-product-row { flex-wrap: wrap; }
    .order-product-review-action { margin-left: 86px; }
}

/* Card Footer */
.order-card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 18px;
    background: #fafafa;
    border-top: 1px solid #f0f0f0;
    flex-wrap: wrap;
    gap: 10px;
}

.order-card-total {
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-card-total .total-label {
    color: #777;
    font-size: .85rem;
}

.order-card-total .total-amount {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--gold, #c4a45a);
}

.order-card-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.order-card-actions button {
    padding: 8px 20px;
    border-radius: 10px;
    font-size: .82rem;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
    border: 1.5px solid transparent;
}

.btn-order-cancel {
    background: #fff;
    border-color: #ddd !important;
    color: #666;
}

.btn-order-cancel:hover {
    border-color: #c62828 !important;
    color: #c62828;
    background: #fff5f5;
}

.btn-order-detail {
    background: var(--navy, #0b2033);
    color: #fff;
    border-color: var(--navy, #0b2033) !important;
}

.btn-order-detail:hover {
    background: #142a3f;
    transform: translateY(-1px);
}

.btn-order-rebuy {
    background: var(--gold, #c4a45a);
    color: #fff;
    border-color: var(--gold, #c4a45a) !important;
}

.btn-order-rebuy:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
}

/* ===== ORDER DETAIL MODAL ===== */
.order-detail-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,.45);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.order-detail-overlay.show {
    display: flex;
}

.order-detail-modal {
    background: #fff;
    border-radius: 20px;
    max-width: 640px;
    width: 100%;
    max-height: 85vh;
    overflow-y: auto;
    padding: 32px;
    position: relative;
    box-shadow: 0 20px 50px rgba(0,0,0,.18);
    animation: modalSlideUp .3s ease;
}

@keyframes modalSlideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border: none;
    background: #f5f5f5;
    border-radius: 50%;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #666;
    transition: .2s;
}

.modal-close-btn:hover {
    background: #eee;
    color: #333;
}

.modal-header {
    margin-bottom: 24px;
}

.modal-header h2 {
    margin: 0 0 4px;
    color: var(--navy, #0b2033);
    font-size: 1.3rem;
}

.modal-header .modal-order-date {
    color: #888;
    font-size: .85rem;
}

.modal-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 20px;
    font-size: .88rem;
    color: #555;
}

.modal-info-grid > div {
    padding: 10px 14px;
    background: #fafafa;
    border-radius: 10px;
}

.modal-info-grid strong {
    display: block;
    font-size: .78rem;
    color: #999;
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: .04em;
}

.modal-info-grid .full-width {
    grid-column: 1 / -1;
}

/* Tracking steps in modal */
.modal-tracking {
    margin: 20px 0;
    padding: 20px;
    background: #fafafa;
    border-radius: 12px;
}

.modal-tracking h4 {
    margin: 0 0 16px;
    font-size: .9rem;
    color: var(--navy, #0b2033);
}

.tracking-timeline {
    display: flex;
    justify-content: space-between;
    position: relative;
    padding: 0 4px;
}

.tracking-timeline::before {
    content: '';
    position: absolute;
    top: 16px;
    left: 8%;
    right: 8%;
    height: 2px;
    background: #e0e0e0;
}

.tracking-step {
    position: relative;
    text-align: center;
    z-index: 1;
    width: 16.6667%;
}

.tracking-step-dot {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #fff;
    border: 2px solid #e0e0e0;
    margin: 0 auto 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    font-weight: 700;
    color: #ccc;
    transition: .3s;
}

.tracking-step.done .tracking-step-dot {
    background: var(--navy, #0b2033);
    border-color: var(--navy, #0b2033);
    color: #fff;
}

.tracking-step-label {
    font-size: .68rem;
    color: #bbb;
    line-height: 1.3;
    font-weight: 600;
}

.tracking-step.done .tracking-step-label {
    color: var(--navy, #0b2033);
}

.order-history {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #eee;
}

.order-history h4 { margin: 0 0 12px; color: var(--navy, #0b2033); }
.order-history-list { display: grid; gap: 10px; }
.order-history-event { border-left: 3px solid var(--gold, #c4a45a); padding: 2px 0 4px 12px; }
.order-history-event strong { display: block; color: var(--navy, #0b2033); }
.order-history-event span, .order-history-event p { color: #777; font-size: .82rem; margin: 3px 0 0; }
.order-cost-summary { display: grid; gap: 8px; max-width: 360px; margin: 18px 0 0 auto; }
.order-cost-row { display: flex; justify-content: space-between; gap: 16px; color: #666; font-size: .9rem; }
.order-cost-row.total { padding-top: 10px; border-top: 1px solid #eee; color: var(--navy, #0b2033); font-size: 1.1rem; font-weight: 800; }

/* ===== PAGINATION ===== */
.orders-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #f0f0f0;
}

.orders-pagination button {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    background: #fff;
    color: var(--navy, #0b2033);
    font-weight: 700;
    font-size: .85rem;
    cursor: pointer;
    transition: .2s;
}

.orders-pagination button:hover:not(:disabled),
.orders-pagination button.active {
    background: var(--navy, #0b2033);
    border-color: var(--navy, #0b2033);
    color: #fff;
}

.orders-pagination button:disabled {
    opacity: .4;
    cursor: not-allowed;
}

.orders-pagination .page-info {
    font-size: .82rem;
    color: #999;
    font-weight: 600;
    margin-left: 8px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 700px) {
    .my-orders-page main {
        padding: 16px 10px 36px;
    }

    .orders-tab {
        min-width: 90px;
        padding: 13px 8px;
        font-size: .8rem;
    }

    .orders-content {
        padding: 14px;
    }

    .order-card-top,
    .order-card-bottom {
        padding: 12px 14px;
    }

    .order-product-row {
        padding: 12px 14px;
    }

    .order-product-img {
        width: 56px;
        height: 56px;
    }

    .order-detail-modal {
        padding: 20px;
        border-radius: 16px;
    }

    .modal-info-grid {
        grid-template-columns: 1fr;
    }

    .tracking-step-label {
        font-size: .58rem;
    }

    .tracking-step-dot {
        width: 26px;
        height: 26px;
        font-size: .6rem;
    }

    .order-card-actions button {
        padding: 7px 14px;
        font-size: .78rem;
    }
}
</style>

<main>
    <div class="orders-wrapper">
        <nav class="breadcrumb-container orders-breadcrumb" aria-label="Điều hướng đường dẫn">
            <a href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Trang chủ</a>
            <span class="separator">&gt;</span>
            <strong class="current" aria-current="page">Đơn hàng của tôi</strong>
        </nav>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <div class="orders-page-header" style="margin-bottom: 0;">
                <div class="orders-icon">📦</div>
                <h1>Đơn hàng của tôi</h1>
            </div>
        </div>

        <div class="orders-filter-bar" role="search">
            <label class="orders-filter-field orders-filter-field--search" for="order-search">
                Tìm mã đơn hàng
                <input id="order-search" type="search" inputmode="numeric" maxlength="11" placeholder="Ví dụ: #123">
            </label>
            <label class="orders-filter-field" for="order-date-from">
                Từ ngày
                <input id="order-date-from" type="date">
            </label>
            <label class="orders-filter-field" for="order-date-to">
                Đến ngày
                <input id="order-date-to" type="date">
            </label>
            <button type="button" class="orders-filter-reset" id="orders-filter-reset">Xóa bộ lọc</button>
        </div>

        <div class="orders-tabs" role="tablist">
            <button class="orders-tab active" role="tab" data-status="all" aria-selected="true">
                Tất cả <span class="tab-badge" id="badge-all">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="pending" aria-selected="false">
                Chờ xác nhận <span class="tab-badge" id="badge-pending">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="confirmed" aria-selected="false">
                Đã xác nhận <span class="tab-badge" id="badge-confirmed">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="preparing" aria-selected="false">
                Đang chuẩn bị <span class="tab-badge" id="badge-preparing">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="shipping" aria-selected="false">
                Đang giao hàng <span class="tab-badge" id="badge-shipping">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="delivered" aria-selected="false">
                Đã giao hàng <span class="tab-badge" id="badge-delivered">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="completed" aria-selected="false">
                Hoàn thành <span class="tab-badge" id="badge-completed">0</span>
            </button>
            <button class="orders-tab" role="tab" data-status="cancelled" aria-selected="false">
                Đã hủy <span class="tab-badge" id="badge-cancelled">0</span>
            </button>
        </div>

        <!-- Orders Content -->
        <div class="orders-content" id="orders-content">
            <div class="orders-loading">
                <div class="spinner"></div>
                <p>Đang tải đơn hàng...</p>
            </div>
        </div>
    </div>
</main>

<!-- Order Detail Modal -->
<div class="order-detail-overlay" id="order-detail-overlay">
    <div class="order-detail-modal" id="order-detail-modal">
        <button class="modal-close-btn" id="modal-close" aria-label="Đóng">&times;</button>
        <div id="modal-body"></div>
    </div>
</div>

<script>
(function() {
    const BASE = window.APP_BASE_URL || '';
    const ORDERS_PER_PAGE = 5;
    const ORDER_CANCEL_CSRF = <?php echo json_encode($_SESSION['order_cancel_csrf'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    let currentTab = 'all';
    let currentPage = 1;
    let ordersCache = {};
    let searchTimer = null;
    let latestRequestId = 0;

    const cancelledStatuses = ['Đã hủy', 'Canceled', 'cancelled', 'huy'];

    function getStatusGroup(status) {
        if (cancelledStatuses.includes(status)) return 'cancelled';
        if (['Đã giao hàng', 'Đã giao', 'Hoàn thành'].includes(status)) return 'delivered';
        if (['Đang giao hàng', 'Đang vận chuyển', 'Đang giao'].includes(status)) return 'shipping';
        return 'processing';
    }

    function getStatusBadgeClass(status) {
        return 'status-' + getStatusGroup(status);
    }

    function getStatusLabel(status) {
        return status;
    }

    function getProductImageUrl(path) {
        if (!path || typeof path !== 'string') return BASE + '/assets/banner/logo.png';
        const trimmed = path.trim();
        if (/^https?:\/\//i.test(trimmed)) return trimmed;
        const clean = trimmed.replace(/^(?:\.\.\/)+/, '');
        return BASE + (clean.startsWith('/') ? clean : '/' + clean);
    }

    function formatMoney(val) {
        return Number(val).toLocaleString('vi-VN') + 'đ';
    }

    function formatDate(dateStr) {
        const d = new Date(String(dateStr || '').replace(' ', 'T'));
        if (Number.isNaN(d.getTime())) return '—';
        return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    function renderReviewAction(item, isDelivered) {
        if (!isDelivered || !item.product_id) return '';
        const reviewUrl = `${BASE}/client/product.php?id=${encodeURIComponent(item.product_id)}#product-reviews`;
        if (Number(item.is_reviewed) === 1) {
            return `<a class="order-reviewed-label order-product-review-action" href="${reviewUrl}" title="Bấm để xem đánh giá của bạn">✓ Đã đánh giá</a>`;
        }
        return `<a class="order-review-link order-product-review-action" href="${reviewUrl}">Đánh giá</a>`;
    }

    function escHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    // ===== FETCH ORDERS =====
    async function fetchOrders(status, page) {
        const params = new URLSearchParams({
            status,
            page: String(page),
            per_page: String(ORDERS_PER_PAGE),
            q: document.getElementById('order-search').value.trim(),
            from: document.getElementById('order-date-from').value,
            to: document.getElementById('order-date-to').value
        });
        const resp = await fetch(`${BASE}/api/orders/my_orders.php?${params}`);
        const data = await resp.json();
        if (data.status !== 'success') throw new Error(data.message || 'Lỗi tải đơn hàng');
        return data;
    }

    // ===== UPDATE BADGES =====
    function updateBadges(counts) {
        document.getElementById('badge-all').textContent = counts.all || 0;
        document.getElementById('badge-pending').textContent = counts.pending || 0;
        document.getElementById('badge-confirmed').textContent = counts.confirmed || 0;
        document.getElementById('badge-preparing').textContent = counts.preparing || 0;
        document.getElementById('badge-shipping').textContent = counts.shipping || 0;
        document.getElementById('badge-delivered').textContent = counts.delivered || 0;
        document.getElementById('badge-completed').textContent = counts.completed || 0;
        document.getElementById('badge-cancelled').textContent = counts.cancelled || 0;
    }

    // ===== RENDER EMPTY STATE =====
    function renderEmpty(container) {
        const msgs = {
            all: { icon: '📦', title: 'Chưa có đơn hàng nào', desc: 'Hãy khám phá sản phẩm và đặt đơn hàng đầu tiên nhé!' },
            pending: { icon: '⏳', title: 'Không có đơn hàng chờ xác nhận', desc: 'Đơn hàng mới đặt sẽ hiển thị ở đây.' },
            confirmed: { icon: '✓', title: 'Không có đơn hàng đã xác nhận', desc: 'Đơn hàng đã xác nhận sẽ hiển thị ở đây.' },
            preparing: { icon: '📦', title: 'Không có đơn hàng đang chuẩn bị', desc: 'Đơn hàng đang được chuẩn bị sẽ hiển thị ở đây.' },
            shipping: { icon: '🚚', title: 'Không có đơn hàng đang giao', desc: 'Các đơn hàng đang vận chuyển sẽ hiển thị ở đây.' },
            delivered: { icon: '📬', title: 'Chưa có đơn hàng đã giao', desc: 'Đơn hàng đã giao sẽ hiển thị ở đây.' },
            completed: { icon: '✅', title: 'Chưa có đơn hàng hoàn thành', desc: 'Đơn hàng đã hoàn tất sẽ hiển thị ở đây.' },
            cancelled: { icon: '🚫', title: 'Không có đơn hàng đã hủy', desc: 'Rất vui vì bạn chưa phải hủy đơn nào!' },
        };
        const m = msgs[currentTab] || msgs.all;
        const hasFilters = document.getElementById('order-search').value.trim()
            || document.getElementById('order-date-from').value
            || document.getElementById('order-date-to').value
            || currentTab !== 'all';
        container.innerHTML = `
            <div class="orders-empty">
                <div class="orders-empty-icon">${m.icon}</div>
                <h3>${m.title}</h3>
                <p>${hasFilters ? 'Không tìm thấy đơn hàng phù hợp với bộ lọc hiện tại.' : m.desc}</p>
                ${hasFilters ? '<button type="button" class="orders-filter-reset" onclick="clearOrderFilters()">Xóa bộ lọc</button>' : `<a href="${BASE}/index.php" class="btn-shop-now">Mua sắm ngay</a>`}
            </div>
        `;
    }

    function renderOrderCard(order) {
        const canCancel = ['Chờ xác nhận', 'Pending'].includes(order.status);
        const isDelivered = ['Đã giao hàng', 'Đã giao', 'Hoàn thành'].includes(order.status);

        let productsHtml = '';
        const items = order.items || [];
        const visibleItems = items.slice(0, 2);
        const hiddenCount = items.length - visibleItems.length;

        visibleItems.forEach(item => {
            const quantity = Number(item.quantity) || 0;
            const totalPrice = Number(item.price) * quantity;
            const imgSrc = getProductImageUrl(item.image);
            productsHtml += `
                <div class="order-product-row">
                    <img class="order-product-img" src="${escHtml(imgSrc)}" alt="" onerror="this.onerror=null;this.src='${BASE}/assets/banner/logo.png'">
                    <div class="order-product-info">
                        <p class="product-name">${escHtml(item.product_name)}</p>
                        <p class="product-qty">${formatMoney(item.price)} × ${quantity}</p>
                    </div>
                    <div class="order-product-price">${formatMoney(totalPrice)}</div>
                    ${renderReviewAction(item, isDelivered)}
                </div>
            `;
        });

        if (hiddenCount > 0) {
            productsHtml += `
                <div style="padding: 8px 18px; font-size: .82rem; color: #999; text-align: center;">
                    + ${hiddenCount} sản phẩm khác
                </div>
            `;
        }

        const totalFormatted = formatMoney(order.total_price);
        const dateFormatted = formatDate(order.created_at);

        return `
            <div class="order-card" data-order-id="${order.id}">
                <div class="order-card-top">
                    <div class="order-card-shop">
                        <span class="shop-icon">🏪</span>
                        Đơn hàng #${order.id} &nbsp;|&nbsp; <span style="font-weight:400;color:#888;font-size:.82rem;">${dateFormatted}</span>
                    </div>
                    <span class="order-status-badge ${getStatusBadgeClass(order.status)}">
                        ${escHtml(getStatusLabel(order.status))}
                    </span>
                </div>

                ${productsHtml}

                <div class="order-card-bottom">
                    <div>
                        <div class="order-card-total">
                            <span class="total-label">${items.length} sản phẩm &nbsp;•&nbsp; Tổng:</span>
                            <span class="total-amount">${totalFormatted}</span>
                        </div>
                        <div class="total-label">Thanh toán: ${escHtml(order.payment_method || 'COD')} — ${escHtml(order.payment_status || 'Chưa thanh toán')}</div>
                        <div class="total-label">Phí vận chuyển: ${formatMoney(order.shipping_fee)} · Giảm giá: −${formatMoney(order.discount_amount)}</div>
                    </div>
                    <div class="order-card-actions">
                        ${canCancel ? `<button class="btn-order-cancel" onclick="cancelOrder(${order.id})">Hủy đơn</button>` : ''}
                        ${isDelivered ? `<button class="btn-order-rebuy" onclick="rebuyOrder(${order.id})">Mua lại</button>` : ''}
                        <button class="btn-order-detail" onclick="showOrderDetail(${order.id})">Xem chi tiết</button>
                    </div>
                </div>
            </div>
        `;
    }

    // ===== RENDER PAGINATION =====
    function renderPagination(container, totalPages) {
        if (totalPages <= 1) return;

        let html = '<div class="orders-pagination">';
        html += `<button ${currentPage <= 1 ? 'disabled' : ''} onclick="goToPage(${currentPage - 1})">‹</button>`;

        for (let p = 1; p <= totalPages; p++) {
            html += `<button class="${p === currentPage ? 'active' : ''}" onclick="goToPage(${p})">${p}</button>`;
        }

        html += `<button ${currentPage >= totalPages ? 'disabled' : ''} onclick="goToPage(${currentPage + 1})">›</button>`;
        html += `<span class="page-info">${currentPage}/${totalPages}</span>`;
        html += '</div>';
        container.insertAdjacentHTML('beforeend', html);
    }

    async function loadAndRender(status, page) {
        const requestId = ++latestRequestId;
        const container = document.getElementById('orders-content');
        container.innerHTML = '<div class="orders-loading"><div class="spinner"></div><p>Đang tải đơn hàng...</p></div>';

        try {
            const data = await fetchOrders(status, page);
            if (requestId !== latestRequestId) return;
            const orders = data.orders || [];
            updateBadges(data.counts || {});

            orders.forEach(o => { ordersCache[o.id] = o; });

            if (orders.length === 0) {
                renderEmpty(container);
                return;
            }

            currentPage = data.pagination.page;
            container.innerHTML = orders.map(renderOrderCard).join('');
            renderPagination(container, data.pagination.total_pages);

        } catch (err) {
            if (requestId !== latestRequestId) return;
            container.innerHTML = `
                <div class="orders-empty">
                    <div class="orders-empty-icon">⚠️</div>
                    <h3>Không thể tải đơn hàng</h3>
                    <p>${escHtml(err.message)}</p>
                    <button onclick="loadAndRender('${currentTab}', ${currentPage})" style="margin-top:12px;background:var(--navy);color:#fff;border:none;padding:10px 24px;border-radius:10px;font-weight:700;cursor:pointer;">Thử lại</button>
                </div>
            `;
        }
    }

    // ===== TAB SWITCHING =====
    document.querySelectorAll('.orders-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.orders-tab').forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');
            currentTab = tab.dataset.status;
            currentPage = 1;
            loadAndRender(currentTab, 1);
        });
    });

    function applyOrderFilters() {
        currentPage = 1;
        loadAndRender(currentTab, currentPage);
    }

    document.getElementById('order-search').addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(applyOrderFilters, 300);
    });
    document.getElementById('order-date-from').addEventListener('change', applyOrderFilters);
    document.getElementById('order-date-to').addEventListener('change', applyOrderFilters);
    document.getElementById('orders-filter-reset').addEventListener('click', () => window.clearOrderFilters());

    window.clearOrderFilters = function() {
        document.getElementById('order-search').value = '';
        document.getElementById('order-date-from').value = '';
        document.getElementById('order-date-to').value = '';
        document.querySelectorAll('.orders-tab').forEach(tab => {
            const selected = tab.dataset.status === 'all';
            tab.classList.toggle('active', selected);
            tab.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
        currentTab = 'all';
        currentPage = 1;
        loadAndRender(currentTab, currentPage);
    };

    window.goToPage = function(page) {
        currentPage = page;
        loadAndRender(currentTab, page);
        document.querySelector('.orders-tabs')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.cancelOrder = async function(orderId) {
        if (!confirm('Bạn có chắc muốn hủy đơn hàng #' + orderId + '?')) return;
        const reason = window.prompt('Vui lòng nhập lý do hủy đơn hàng (tối đa 500 ký tự):');
        if (reason === null) return;
        if (!reason.trim()) {
            alert('Vui lòng nhập lý do hủy đơn hàng.');
            return;
        }
        try {
            const resp = await fetch(`${BASE}/api/orders/cancel_order.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId, reason: reason.trim(), csrf_token: ORDER_CANCEL_CSRF })
            });
            const data = await resp.json();
            if (data.status === 'success') {
                if (typeof showToast === 'function') {
                    showToast('Đã hủy đơn hàng thành công!');
                } else {
                    alert('Đã hủy đơn hàng thành công!');
                }
                loadAndRender(currentTab, currentPage);
            } else {
                alert('Lỗi: ' + (data.message || 'Không thể hủy đơn'));
            }
        } catch (e) {
            console.error(e);
            alert('Lỗi kết nối khi hủy đơn');
        }
    };

    window.rebuyOrder = function(orderId) {
        const order = ordersCache[orderId];
        if (!order || !order.items) return;

        let addedCount = 0;
        order.items.forEach(item => {
            if (typeof addItemToCart === 'function') {
                const added = addItemToCart({
                    product_id: item.product_id,
                    name: item.product_name,
                    price: Number(item.price),
                    image: item.image,
                    quantity: Number(item.quantity),
                    stock: 999
                });
                if (added) addedCount++;
            }
        });

        if (addedCount > 0) {
            if (typeof showToast === 'function') {
                showToast(`Đã thêm ${addedCount} sản phẩm vào giỏ hàng!`);
            }
        } else {
            alert('Không thể thêm sản phẩm vào giỏ hàng. Vui lòng thử lại.');
        }
    };

    window.showOrderDetail = async function(orderId) {
        const overlay = document.getElementById('order-detail-overlay');
        const modalBody = document.getElementById('modal-body');
        modalBody.innerHTML = '<div class="orders-loading"><div class="spinner"></div><p>Đang tải chi tiết đơn hàng...</p></div>';
        overlay.classList.add('show');
        try {
            const resp = await fetch(`${BASE}/api/orders/order_detail.php?order_id=${encodeURIComponent(orderId)}`);
            const data = await resp.json();
            if (data.status !== 'success') throw new Error(data.message || 'Không thể tải chi tiết đơn hàng.');
            const order = data.order;
            ordersCache[order.id] = order;
            const items = order.items || [];
            const itemSubtotal = items.reduce((sum, item) => sum + Number(item.price) * Number(item.quantity), 0);
            const isDelivered = ['Đã giao hàng', 'Đã giao', 'Hoàn thành'].includes(order.status);
            const itemHtml = items.map(item => {
                const quantity = Number(item.quantity) || 0;
                const lineTotal = Number(item.price) * quantity;
                return `
                    <div class="order-product-row">
                        <img class="order-product-img" src="${escHtml(getProductImageUrl(item.image))}" alt="" onerror="this.onerror=null;this.src='${BASE}/assets/banner/logo.png'">
                        <div class="order-product-info">
                            <p class="product-name">${escHtml(item.product_name)}</p>
                            <p class="product-qty">${formatMoney(item.price)} × ${quantity}</p>
                        </div>
                        <div class="order-product-price">${formatMoney(lineTotal)}</div>
                        ${renderReviewAction(item, isDelivered)}
                    </div>
                `;
            }).join('');
            const history = order.status_history || [];
            const historyHtml = history.length
                ? history.map(event => {
                    const newStatus = event.new_status || event.status;
                    const transition = event.old_status
                        ? `${event.old_status} → ${newStatus}`
                        : newStatus;
                    return `
                    <div class="order-history-event">
                        <strong>${escHtml(transition)}</strong>
                        <span>${formatDate(event.created_at)} · ${escHtml(event.actor_name)}</span>
                        ${event.note ? `<p>${escHtml(event.note)}</p>` : ''}
                    </div>
                `;
                }).join('')
                : '<p class="order-history-event">Đơn hàng cũ chưa có dữ liệu lịch sử trạng thái.</p>';
            const cancellationHtml = order.cancellation_reason
                ? `<div class="full-width"><strong>Lý do hủy</strong>${escHtml(order.cancellation_reason)}${order.cancelled_at ? `<p>${formatDate(order.cancelled_at)}</p>` : ''}</div>`
                : '';

            modalBody.innerHTML = `
                <div class="modal-header">
                    <h2>Đơn hàng #${order.id}</h2>
                    <div class="modal-order-date">Ngày đặt: ${formatDate(order.created_at)}</div>
                </div>
                <div class="modal-info-grid">
                    <div><strong>Người nhận</strong>${escHtml(order.full_name)}</div>
                    <div><strong>Số điện thoại</strong>${escHtml(order.phone)}</div>
                    <div><strong>Email</strong>${escHtml(order.email || 'Chưa cung cấp')}</div>
                    <div><strong>Trạng thái</strong><span class="order-status-badge ${getStatusBadgeClass(order.status)}" style="font-size:.75rem;">${escHtml(order.status)}</span></div>
                    <div class="full-width"><strong>Địa chỉ nhận hàng</strong>${escHtml(order.address)}</div>
                    ${cancellationHtml}
                    <div><strong>Phương thức thanh toán</strong>${escHtml(order.payment_method || 'COD')}</div>
                    <div><strong>Trạng thái thanh toán</strong>${escHtml(order.payment_status || 'Chưa thanh toán')}</div>
                </div>
                <div class="order-history">
                    <h4>Lịch sử trạng thái</h4>
                    <div class="order-history-list">${historyHtml}</div>
                </div>
                <div style="margin-top:20px;">
                    <h4 style="margin:0 0 12px;color:var(--navy);font-size:.95rem;">Sản phẩm đã đặt</h4>
                    ${itemHtml || '<p>Đơn hàng chưa có chi tiết sản phẩm.</p>'}
                </div>
                <div class="order-cost-summary">
                    <div class="order-cost-row"><span>Tạm tính sản phẩm</span><strong>${formatMoney(itemSubtotal)}</strong></div>
                    <div class="order-cost-row"><span>Phí vận chuyển</span><strong>${formatMoney(order.shipping_fee)}</strong></div>
                    <div class="order-cost-row"><span>Giảm giá</span><strong>−${formatMoney(order.discount_amount)}</strong></div>
                    <div class="order-cost-row total"><span>Tổng thanh toán</span><strong>${formatMoney(order.total_price)}</strong></div>
                </div>
            `;
        } catch (err) {
            modalBody.innerHTML = `<div class="orders-empty"><div class="orders-empty-icon">⚠️</div><h3>Không thể tải chi tiết đơn hàng</h3><p>${escHtml(err.message)}</p></div>`;
        }
    };

    // ===== MODAL CLOSE =====
    document.getElementById('modal-close').addEventListener('click', () => {
        document.getElementById('order-detail-overlay').classList.remove('show');
    });

    document.getElementById('order-detail-overlay').addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            e.currentTarget.classList.remove('show');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            document.getElementById('order-detail-overlay').classList.remove('show');
        }
    });

    // ===== INITIAL LOAD =====
    loadAndRender('all', 1);

})();
</script>

<?php
include __DIR__ . '/../include/footer.php';
?>
