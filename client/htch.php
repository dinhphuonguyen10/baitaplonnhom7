<?php
$pageTitle = 'Hệ thống cửa hàng - ÉCRIN JEWELRY';
require_once __DIR__ . '/../config/db.php';
$clientStores = [];
$storeResult = $conn->query("SELECT id, name, address, phone, hours FROM stores WHERE status = 'active' ORDER BY id DESC");
if ($storeResult) {
    while ($store = $storeResult->fetch_assoc()) {
        $clientStores[] = $store;
    }
}
$firstClientStore = $clientStores[0] ?? ['id' => 0, 'name' => 'Chưa có cửa hàng', 'address' => '', 'phone' => '', 'hours' => ''];
include __DIR__ . '/../include/header.php';
?>
    <style>
        .store-container {
            max-width: 1200px;
            margin: 64px auto 40px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 30px;
        }

        .store-breadcrumb {
            grid-column: 1 / -1;
            margin: -18px 0 0;
        }

        .store-sidebar,
        .store-map-area {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgb(0 0 0 / 5%);
        }

        .store-sidebar {
            height: fit-content;
            padding: 24px;
        }

        .store-sidebar h1 {
            margin: 0 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
            color: var(--navy);
            font-size: 1.5rem;
        }

        .search-store {
            margin-bottom: 20px;
        }

        .search-store input {
            width: 100%;
            padding: 12px;
            border: 1px solid #eee;
            border-radius: 8px;
            font-size: 0.9rem;
        }

        .store-list {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 5px;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: #b7c3d0 #f1f4f7;
        }
        .store-list::-webkit-scrollbar { width: 8px; }
        .store-list::-webkit-scrollbar-track { border-radius: 8px; background: #f1f4f7; }
        .store-list::-webkit-scrollbar-thumb { border-radius: 8px; background: #b7c3d0; }

        .store-item {
            display: block;
            width: 100%;
            margin-bottom: 12px;
            padding: 15px;
            border: 1px solid #f5f5f5;
            border-radius: 12px;
            background: #fff;
            color: inherit;
            cursor: pointer;
            font: inherit;
            text-align: left;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .store-item:hover,
        .store-item.active {
            border-color: var(--gold);
            background: var(--cream);
        }

        .store-item h3 {
            margin: 0 0 5px;
            color: #1a153a;
            font-size: 1rem;
        }

        .store-item p {
            margin: 0;
            color: #666;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .store-map-area {
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-self: stretch;
            height: auto;
        }

        .map-placeholder {
            flex: 1 1 auto;
            min-height: 420px;
        }

        .map-placeholder iframe {
            display: block;
            width: 100%;
            height: 100%;
            min-height: 420px;
            border: 0;
        }

        .store-detail-card {
            padding: 34px 30px 40px;
            border-top: 1px solid #eee;
        }

        .store-detail-card h2 {
            margin: 0 0 15px;
            color: var(--gold);
            font-size: 1.35rem;
        }

        .store-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .info-box h3 {
            margin: 0 0 5px;
            color: #999;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .info-box p {
            margin: 0;
            color: #333;
            font-weight: 600;
        }

        .store-empty {
            display: none;
            color: #667085;
            text-align: center;
        }

        .store-pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 7px;
            margin-top: 18px;
        }

        .store-pagination button {
            min-width: 34px;
            height: 34px;
            padding: 0 9px;
            border: 1px solid #d8dee6;
            border-radius: 8px;
            background: #fff;
            color: var(--navy);
            font-weight: 700;
            cursor: pointer;
        }

        .store-pagination button:hover:not(:disabled),
        .store-pagination button.is-active {
            border-color: var(--navy);
            background: var(--navy);
            color: #fff;
        }

        .store-pagination button:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .store-page-count {
            color: #667085;
            font-size: .8rem;
            font-weight: 600;
        }

        @media (max-width: 992px) {
            .store-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <main class="store-container">
        <nav class="breadcrumb-container store-breadcrumb" aria-label="Điều hướng đường dẫn">
            <a href="<?php echo htmlspecialchars($appBaseUrl . '/index.php', ENT_QUOTES, 'UTF-8'); ?>">Trang chủ</a>
            <span class="separator">&gt;</span>
            <strong class="current" aria-current="page">Hệ thống cửa hàng</strong>
        </nav>
        <aside class="store-sidebar">
            <h1>Tìm cửa hàng</h1>
            <div class="search-store">
                <label for="store-search">Tìm theo khu vực</label>
                <input type="search" placeholder="Ví dụ: Cầu Giấy" id="store-search">
            </div>
            <div class="store-list" id="store-list">
                <?php foreach ($clientStores as $i => $store): ?>
                <button class="store-item<?php echo $i === 0 ? ' active' : ''; ?>" type="button" data-id="<?php echo (int) $store['id']; ?>">
                    <h3><?php echo htmlspecialchars($store['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p><?php echo htmlspecialchars($store['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                </button>
                <?php endforeach; ?>
                <p class="store-empty" id="store-empty">Không tìm thấy cửa hàng phù hợp.</p>
            </div>
            <nav class="store-pagination" id="store-pagination" aria-label="Phân trang cửa hàng"></nav>
        </aside>

        <section class="store-map-area" aria-label="Bản đồ và thông tin cửa hàng">
            <div class="map-placeholder">
                <iframe id="google-map" title="Bản đồ vị trí cửa hàng Écrin Jewelry"
                    src="https://www.google.com/maps?q=<?php echo rawurlencode($firstClientStore['address']); ?>&amp;output=embed"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
            <div class="store-detail-card" aria-live="polite">
                <h2 id="detail-name"><?php echo htmlspecialchars($firstClientStore['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <div class="store-info-grid">
                    <div class="info-box">
                        <h3>Địa chỉ</h3>
                        <p id="detail-address"><?php echo htmlspecialchars($firstClientStore['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="info-box">
                        <h3>Điện thoại</h3>
                        <p id="detail-phone"><?php echo htmlspecialchars($firstClientStore['phone'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <div class="info-box">
                        <h3>Giờ mở cửa</h3>
                        <p id="detail-hours"><?php echo htmlspecialchars($firstClientStore['hours'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        const stores = <?php echo json_encode($clientStores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        const storeItems = Array.from(document.querySelectorAll('.store-item'));
        const searchInput = document.getElementById('store-search');
        const emptyMessage = document.getElementById('store-empty');
        const pagination = document.getElementById('store-pagination');
        const storesPerPage = 8;
        let filteredStoreItems = [...storeItems];
        let currentStorePage = 1;

        storeItems.forEach(item => {
            item.addEventListener('click', () => {
                const store = stores.find(location => Number(location.id) === Number(item.dataset.id));
                if (!store) return;

                storeItems.forEach(storeItem => storeItem.classList.toggle('active', storeItem === item));
                document.getElementById('detail-name').textContent = store.name;
                document.getElementById('detail-address').textContent = store.address;
                document.getElementById('detail-phone').textContent = store.phone;
                document.getElementById('detail-hours').textContent = store.hours;
                document.getElementById('google-map').src =
                    `https://www.google.com/maps?q=${encodeURIComponent(store.address)}&output=embed`;
            });
        });

        function renderStorePagination() {
            const totalPages = Math.ceil(filteredStoreItems.length / storesPerPage);
            pagination.innerHTML = '';
            pagination.hidden = totalPages <= 1;
            if (totalPages <= 1) return;

            const previous = document.createElement('button');
            previous.type = 'button';
            previous.textContent = '‹';
            previous.disabled = currentStorePage === 1;
            previous.setAttribute('aria-label', 'Trang trước');
            previous.addEventListener('click', () => {
                currentStorePage -= 1;
                renderStores();
            });
            pagination.appendChild(previous);

            for (let page = 1; page <= totalPages; page += 1) {
                const pageButton = document.createElement('button');
                pageButton.type = 'button';
                pageButton.textContent = String(page);
                pageButton.className = page === currentStorePage ? 'is-active' : '';
                pageButton.setAttribute('aria-label', `Trang ${page}`);
                pageButton.setAttribute('aria-current', page === currentStorePage ? 'page' : 'false');
                pageButton.addEventListener('click', () => {
                    currentStorePage = page;
                    renderStores();
                });
                pagination.appendChild(pageButton);
            }

            const next = document.createElement('button');
            next.type = 'button';
            next.textContent = '›';
            next.disabled = currentStorePage === totalPages;
            next.setAttribute('aria-label', 'Trang sau');
            next.addEventListener('click', () => {
                currentStorePage += 1;
                renderStores();
            });
            pagination.appendChild(next);

            const pageCount = document.createElement('span');
            pageCount.className = 'store-page-count';
            pageCount.textContent = `${currentStorePage}/${totalPages}`;
            pagination.appendChild(pageCount);
        }

        function renderStores() {
            const totalPages = Math.max(1, Math.ceil(filteredStoreItems.length / storesPerPage));
            currentStorePage = Math.min(currentStorePage, totalPages);
            const firstIndex = (currentStorePage - 1) * storesPerPage;
            document.getElementById('store-list').scrollTop = 0;

            storeItems.forEach(item => {
                item.hidden = !filteredStoreItems.includes(item) ||
                    filteredStoreItems.indexOf(item) < firstIndex ||
                    filteredStoreItems.indexOf(item) >= firstIndex + storesPerPage;
            });

            emptyMessage.style.display = filteredStoreItems.length === 0 ? 'block' : 'none';
            renderStorePagination();
        }

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLocaleLowerCase('vi');
            filteredStoreItems = storeItems.filter(item => item.textContent.toLocaleLowerCase('vi').includes(query));
            currentStorePage = 1;
            renderStores();
        });

        renderStores();
    </script>

<?php include __DIR__ . '/../include/footer.php'; ?>
