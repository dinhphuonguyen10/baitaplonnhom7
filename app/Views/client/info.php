<?php

use App\Core\View;

include VIEW_PATH . '/layouts/header.php';
?>
<main class="info-page-main">
    <div class="info-page-container">
        <nav class="breadcrumb-container info-breadcrumb" aria-label="Điều hướng đường dẫn">
            <a href="<?php echo View::e($appBaseUrl . '/index.php'); ?>">Trang chủ</a>
            <span class="separator" aria-hidden="true">/</span>
            <span class="current"><?php echo View::e($topic['title'] ?? 'Không tìm thấy nội dung'); ?></span>
        </nav>

        <header class="info-hero">
            <span class="info-eyebrow"><?php echo View::e($topic['eyebrow'] ?? 'ÉCRIN JEWELRY'); ?></span>
            <h1><?php echo View::e($topic['title'] ?? 'Nội dung không tồn tại'); ?></h1>
            <p><?php echo View::e($topic['intro'] ?? 'Rất tiếc, nội dung bạn tìm kiếm không tồn tại hoặc đã được cập nhật.'); ?></p>
            <span class="info-hero-mark" aria-hidden="true">É</span>
        </header>

        <?php if ($topic !== null): ?>
            <?php if (!empty($topic['sections'])): ?>
                <div class="info-section-list">
                    <?php foreach ($topic['sections'] as $section): ?>
                        <section class="info-card">
                            <h2><?php echo View::e($section['title']); ?></h2>
                            <?php foreach ($section['paragraphs'] ?? [] as $paragraph): ?>
                                <p><?php echo View::e($paragraph); ?></p>
                            <?php endforeach; ?>
                            <?php if (!empty($section['items'])): ?>
                                <ul>
                                    <?php foreach ($section['items'] as $item): ?>
                                        <li><?php echo View::e($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($topic['faqs'])): ?>
                <section class="info-faqs" aria-labelledby="info-faq-heading">
                    <div class="info-section-heading">
                        <span class="info-eyebrow">GIẢI ĐÁP NHANH</span>
                        <h2 id="info-faq-heading">Bạn muốn biết thêm?</h2>
                    </div>
                    <div class="info-faq-list">
                        <?php foreach ($topic['faqs'] as $faq): ?>
                            <details class="info-faq-item">
                                <summary><?php echo View::e($faq[0]); ?></summary>
                                <p><?php echo View::e($faq[1]); ?></p>
                            </details>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($topic['actions'])): ?>
                <nav class="info-actions" aria-label="Liên kết hữu ích">
                    <?php foreach ($topicActions as $action): ?>
                        <a class="info-action<?php echo !empty($action['primary']) ? ' info-action-primary' : ''; ?>"
                            href="<?php echo View::e(strpos($action['href'], 'tel:') === 0 ? $action['href'] : $appBaseUrl . $action['href']); ?>">
                            <?php echo View::e($action['label']); ?>
                            <span aria-hidden="true">→</span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="info-not-found">
                <a class="info-action info-action-primary" href="<?php echo View::e($appBaseUrl . '/index.php'); ?>">Quay về trang chủ <span aria-hidden="true">→</span></a>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include VIEW_PATH . '/layouts/footer.php'; ?>
