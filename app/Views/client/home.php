<?php

use App\Core\View;

include VIEW_PATH . '/layouts/header.php';
?>

    <!-- BANNER -->
    <section class="banner" aria-label="Bộ sưu tập Écrin Jewelry">
        <div class="banner-left">
            <div class="slideshow">
                <?php foreach ($banners as $bannerIndex => $banner): ?>
                    <img src="<?php echo View::e($banner['src']); ?>"
                         alt="<?php echo View::e($banner['alt']); ?>"
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
        <?php foreach ($homeCategories as $cat): ?>
            <a href="<?php echo View::e($cat['link']); ?>" class="item">
                <img src="<?php echo View::e($cat['image']); ?>" alt="<?php echo View::e($cat['name']); ?>" class="item-icon" onerror="this.onerror=null;this.src='<?php echo View::e($appBaseUrl . '/assets/banner/logo.png'); ?>';">
                <span><?php echo View::e($cat['name']); ?></span>
            </a>
        <?php endforeach; ?>
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
                    <?php foreach ($visibleNavCategories as $navCategory): ?>
                        <option value="<?php echo View::e($navCategory['slug']); ?>">
                            <?php echo View::e($navCategory['name']); ?>
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

                <button class="slider-btn next" id="featured-next" aria-label="Xem thêm sản phẩm">❯</button>
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

                <button class="slider-btn next" id="suggest-next" aria-label="Xem thêm sản phẩm">❯</button>
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

                <button class="slider-btn next" id="new-next" aria-label="Xem thêm sản phẩm">❯</button>
            </div>
        </div>
    </section>

<?php
include VIEW_PATH . '/layouts/footer.php';
?>
