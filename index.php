<?php
$pageTitle = "ÉCRIN JEWELRY - Trang chủ";
$bodyClass = 'home-page';
include __DIR__ . '/include/header.php';

$bannerConfigFile = __DIR__ . '/storage/banner/banners.json';
$defaultBanners = [
    ['src' => 'assets/banner/baner1.png', 'alt' => 'Bộ sưu tập trang sức mới Écrin Jewelry'],
    ['src' => 'assets/banner/baner2.png', 'alt' => 'Trang sức Écrin trên nền lụa xanh thanh lịch'],
    ['src' => 'assets/banner/baner3.png', 'alt' => 'Bộ trang sức hoa Écrin Jewelry'],
    ['src' => 'assets/banner/baner4.png', 'alt' => 'Nhẫn và bông tai Écrin Jewelry'],
];
$banners = $defaultBanners;
if (is_file($bannerConfigFile)) {
    $savedBanners = json_decode((string) file_get_contents($bannerConfigFile), true);
    if (is_array($savedBanners) && count($savedBanners) >= 1) {
        $banners = [];
        foreach ($savedBanners as $index => $banner) {
            $src = is_array($banner) ? (string) ($banner['src'] ?? '') : '';
            $alt = is_array($banner) ? (string) ($banner['alt'] ?? '') : '';
            if ($src === '') {
                $src = $defaultBanners[$index]['src'];
            }
            if ($alt === '') {
                $alt = $defaultBanners[$index]['alt'];
            }
            $banners[] = ['src' => $src, 'alt' => $alt];
        }
    }
}
?>

    <!-- BANNER -->
    <section class="banner" aria-label="Bộ sưu tập Écrin Jewelry">
        <div class="banner-left">
            <div class="slideshow">
                <?php foreach ($banners as $bannerIndex => $banner): ?>
                    <img src="<?php echo htmlspecialchars($banner['src'], ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($banner['alt'], ENT_QUOTES, 'UTF-8'); ?>"
                         class="slide<?php echo $bannerIndex === 0 ? ' active' : ''; ?>">
                <?php endforeach; ?>
            </div>
            <button type="button" class="banner-arrow prev" aria-label="Ảnh trước">‹</button>
            <button type="button" class="banner-arrow next" aria-label="Ảnh tiếp theo">›</button>
            <div class="banner-dots" aria-label="Chọn ảnh">
                <?php foreach ($banners as $bannerIndex => $banner): ?>
                    <button type="button" class="dot<?php echo $bannerIndex === 0 ? ' active' : ''; ?>"
                            aria-label="Ảnh <?php echo $bannerIndex + 1; ?>"
                            <?php echo $bannerIndex === 0 ? 'aria-current="true"' : ''; ?>></button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CATEGORY -->
    <section class="category home-categories" aria-label="Danh mục sản phẩm">
        <?php
        $categoryIcons = [
            'bong-tai' => 'assets/bôngtai/bong1.jpg',
            'day-chuyen' => 'assets/dây chuyền/dâychuyen/day1.jpg',
            'nhan' => 'assets/nhẫn/nhan1.png',
            'vong-tay' => 'assets/vòng tay/vong1.png',
            'set-qua-tang' => 'assets/quà/set1.jpg',
        ];
        $categoryLinks = [
            'bong-tai' => 'client/bong_tai.php',
            'day-chuyen' => 'client/day_chuyen.php',
            'nhan' => 'client/nhan.php',
            'vong-tay' => 'client/vong_tay.php',
        ];

        $displayCats = [];
        if (!empty($navCategories)) {
            foreach ($navCategories as $cat) {
                if (!in_array($cat['slug'], ['combo', 'suggest', 'featured'], true)) {
                    $displayCats[] = $cat;
                }
            }
        }

        if (!empty($displayCats)):
            foreach ($displayCats as $cat):
                $cSlug = $cat['slug'];
                $cName = $cat['name'];
                $categoryImage = trim((string)($cat['image'] ?? ''));
                $cImg = $categoryImage !== ''
                    ? (preg_match('~^https?://~i', $categoryImage)
                        ? $categoryImage
                        : $appBaseUrl . '/' . ltrim($categoryImage, '/'))
                    : ($categoryIcons[$cSlug] ?? 'assets/banner/logo.png');
                $cLink = $categoryLinks[$cSlug] ?? ('client/category.php?slug=' . urlencode($cSlug));
        ?>
            <a href="<?php echo htmlspecialchars($cLink, ENT_QUOTES, 'UTF-8'); ?>" class="item">
                <img src="<?php echo htmlspecialchars($cImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?>" class="item-icon" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($appBaseUrl . '/assets/banner/logo.png', ENT_QUOTES, 'UTF-8'); ?>';">
                <span><?php echo htmlspecialchars($cName, ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
        <?php 
            endforeach;
        endif;
        ?>
    </section>

    <section class="category-page" id="search-results-section" hidden>
        <div class="category-header">
            <h1 class="page-title-left">KẾT QUẢ TÌM KIẾM</h1>
        </div>
        <div class="search-filter-bar" id="search-filter-bar" aria-label="Bộ lọc kết quả tìm kiếm">
            <label>
                Danh mục
                <select id="search-filter-category">
                    <option value="">Tất cả danh mục</option>
                    <?php foreach ($navCategories as $navCategory): ?>
                        <?php if (in_array($navCategory['slug'], ['combo', 'suggest', 'featured'], true)) continue; ?>
                        <option value="<?php echo htmlspecialchars($navCategory['slug'], ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($navCategory['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Giá từ
                <input type="number" id="search-filter-min-price" min="0" inputmode="numeric">
            </label>
            <label>
                Giá đến
                <input type="number" id="search-filter-max-price" min="0" inputmode="numeric">
            </label>
            <label class="search-filter-check">
                <input type="checkbox" id="search-filter-in-stock">
                Còn hàng
            </label>
            <label class="search-filter-check">
                <input type="checkbox" id="search-filter-discount">
                Đang giảm giá
            </label>
            <label class="search-filter-check">
                <input type="checkbox" id="search-filter-gift">
                Có quà tặng
            </label>
            <label>
                Sắp xếp
                <select id="search-filter-sort">
                    <option value="relevance">Liên quan nhất</option>
                    <option value="best_selling">Bán chạy</option>
                    <option value="newest">Mới nhất</option>
                    <option value="price_asc">Giá tăng dần</option>
                    <option value="price_desc">Giá giảm dần</option>
                    <option value="name_asc">Tên A-Z</option>
                </select>
            </label>
            <button type="button" class="btn-apply-filter" id="search-filter-apply">ÁP DỤNG</button>
            <button type="button" class="btn-reset-all-filters" id="search-filter-reset">XÓA BỘ LỌC</button>
        </div>
        <p id="search-results-count" class="category-results-count"></p>
        <p id="search-results-error" class="category-filter-error" hidden></p>
        <div class="product-grid category-grid" id="category-products-container" data-db-category=""></div>
        <div class="filter-empty-state" id="search-empty-state" hidden>
            <p>Không tìm thấy sản phẩm phù hợp với từ khóa hoặc bộ lọc đã chọn.</p>
            <button type="button" class="btn-empty-reset" id="search-empty-reset">Xóa bộ lọc</button>
        </div>
    </section>

    <!-- 1. MỤC ĐẦU TIÊN: SẢN PHẨM NỔI BẬT (LỌC BẰNG SQL: is_featured = 1) -->
    <section class="combo-section" aria-label="Sản phẩm nổi bật">
        <div class="combo-container">
            <div class="home-product-heading">
                <div><p class="section-label">BÁN CHẠY NHẤT</p><h2>Sản phẩm nổi bật</h2></div>
                <a href="client/category.php?slug=set-qua-tang">Khám phá bộ sưu tập <span aria-hidden="true">→</span></a>
            </div>
            <div class="combo-slider">
                <button class="slider-btn prev" id="featured-prev">❮</button>

                <div class="combo-items" id="featured-items-container">
                    <!-- Sản phẩm sẽ được render từ JavaScript (script.js) -->
                </div>

                <button class="slider-btn next" id="featured-next"
                    style="width: auto; padding: 0 16px; border-radius: 20px; font-size: 1rem; font-weight: 600; white-space: nowrap;">Xem
                    thêm ❯</button>
            </div>
        </div>
    </section>

    <!-- 2. MỤC THỨ HAI: GỢI Ý CHO BẠN (LỌC BẰNG SQL: is_suggested = 1) -->
    <section class="combo-section combo-section--alt" aria-label="Gợi ý cho bạn">
        <div class="combo-container">
            <div class="home-product-heading">
                <div><p class="section-label">DÀNH RIÊNG CHO BẠN</p><h2>Gợi ý cho bạn</h2></div>
                <a href="client/nhan.php">Khám phá ngay <span aria-hidden="true">→</span></a>
            </div>
            <div class="combo-slider">
                <button class="slider-btn prev" id="suggest-prev">❮</button>

                <div class="combo-items" id="suggest-items-container">
                    <!-- Sản phẩm sẽ được render từ JavaScript (script.js) -->
                </div>

                <button class="slider-btn next" id="suggest-next"
                    style="width: auto; padding: 0 16px; border-radius: 20px; font-size: 1rem; font-weight: 600; white-space: nowrap;">Xem
                    thêm ❯</button>
            </div>
        </div>
    </section>

    <!-- 3. MỤC THỨ BA: SẢN PHẨM MỚI NHẤT (LỌC BẰNG SQL: is_new = 1) -->
    <section class="combo-section" aria-label="Sản phẩm mới nhất">
        <div class="combo-container">
            <div class="home-product-heading">
                <div><p class="section-label">BỘ SƯU TẬP MỚI</p><h2>Sản phẩm mới nhất</h2></div>
                <a href="client/day_chuyen.php">Xem mẫu mới <span aria-hidden="true">→</span></a>
            </div>
            <div class="combo-slider">
                <button class="slider-btn prev" id="new-prev">❮</button>

                <div class="combo-items" id="new-items-container">
                    <!-- Sản phẩm sẽ được render từ JavaScript (script.js) -->
                </div>

                <button class="slider-btn next" id="new-next"
                    style="width: auto; padding: 0 16px; border-radius: 20px; font-size: 1rem; font-weight: 600; white-space: nowrap;">Xem
                    thêm ❯</button>
            </div>
        </div>
    </section>

<?php
include __DIR__ . '/include/footer.php';
?>
