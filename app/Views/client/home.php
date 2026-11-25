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
        <?php if (!empty($homeCategories)): foreach ($homeCategories as $cat): ?>
            <a href="<?php echo View::e($cat['link']); ?>" class="item">
                <img src="<?php echo View::e($cat['image']); ?>" alt="<?php echo View::e($cat['name']); ?>" class="item-icon">
                <span><?php echo View::e($cat['name']); ?></span>
            </a>
        <?php endforeach; else: ?>
            <a href="client/bong_tai.php" class="item">
                <img src="assets/bôngtai/bong1.jpg" alt="Bông tai" class="item-icon">
                <span>Bông tai</span>
            </a>
            <a href="client/day_chuyen.php" class="item">
                <img src="assets/dây chuyền/dâychuyen/day1.jpg" alt="Dây chuyền" class="item-icon">
                <span>Dây chuyền</span>
            </a>
            <a href="client/nhan.php" class="item">
                <img src="assets/nhẫn/nhan1.png" alt="Nhẫn" class="item-icon">
                <span>Nhẫn</span>
            </a>
            <a href="client/vong_tay.php" class="item">
                <img src="assets/vòng tay/vong1.png" alt="Vòng tay" class="item-icon">
                <span>Vòng tay</span>
            </a>
            <a href="client/category.php?slug=set-qua-tang" class="item">
                <img src="assets/quà/set1.jpg" alt="Set quà tặng" class="item-icon">
                <span>Set quà tặng</span>
            </a>
        <?php endif; ?>
    </section>

    <section class="category-page" id="search-results-section" hidden>
        <div class="category-header">
            <h1 class="page-title-left">KẾT QUẢ TÌM KIẾM</h1>
        </div>
        <div class="product-grid category-grid" id="category-products-container" data-db-category=""></div>
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
