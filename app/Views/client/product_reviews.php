<?php

// View con: khối đánh giá trên trang chi tiết sản phẩm.
// Dữ liệu do ProductPageController::reviewSection() chuẩn bị.
use App\Core\View;
use App\Helpers\ReviewerNameFormatter;

?>

<section class="product-reviews-section" id="product-reviews" aria-labelledby="product-reviews-title">
    <div class="product-reviews-heading">
        <p class="product-reviews-eyebrow">ÉCRIN JEWELRY · TRẢI NGHIỆM KHÁCH HÀNG</p>
        <h2 id="product-reviews-title">Đánh giá sản phẩm</h2>
        <p>Chia sẻ cảm nhận chân thật để giúp khách hàng khác chọn được món trang sức phù hợp.</p>
    </div>

    <?php if (is_array($reviewFlash) && !empty($reviewFlash['message'])): ?>
        <div class="review-notice review-notice-<?php echo ($reviewFlash['type'] ?? '') === 'success' ? 'success' : 'error'; ?>" role="status">
            <?php echo View::e($reviewFlash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="review-summary">
        <div class="review-score">
            <?php if ($reviewCount > 0): ?>
                <strong><?php echo number_format($averageRating, 1, ',', '.'); ?><span>/5</span></strong>
                <span class="review-summary-stars" aria-label="<?php echo View::e(number_format($averageRating, 1, ',', '.')); ?> trên 5 sao"><?php echo str_repeat('★', $roundedAverageStars) . str_repeat('☆', 5 - $roundedAverageStars); ?></span>
                <span><?php echo number_format($reviewCount); ?> đánh giá</span>
            <?php else: ?>
                <strong class="review-no-score">Chưa có</strong>
                <span>đánh giá</span>
            <?php endif; ?>
        </div>

        <div class="review-filter-list" aria-label="Lọc đánh giá theo số sao">
            <a class="review-filter-chip<?php echo $reviewFilter === 0 ? ' is-active' : ''; ?>"
               href="<?php echo View::e($reviewBaseUrl . '#product-reviews'); ?>"
               <?php echo $reviewFilter === 0 ? 'aria-current="page"' : ''; ?>>
                Tất cả (<?php echo $reviewCount; ?>)
            </a>
            <?php foreach ($ratingCounts as $stars => $count): ?>
                <a class="review-filter-chip<?php echo $reviewFilter === $stars ? ' is-active' : ''; ?>"
                   href="<?php echo View::e($reviewBaseUrl . '&rating=' . $stars . '#product-reviews'); ?>"
                   <?php echo $reviewFilter === $stars ? 'aria-current="page"' : ''; ?>>
                    <?php echo $stars; ?> sao (<?php echo $count; ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="review-list" aria-live="polite">
        <?php if ($reviewRows !== []): ?>
            <?php foreach ($reviewRows as $review): ?>
                <?php
                $reviewId = (int) $review['id'];
                $reviewStars = (int) $review['rating'];
                $reviewComment = trim((string) ($review['comment'] ?? ''));
                $reviewDate = strtotime((string) $review['created_at']);
                $adminReply = trim((string) ($review['admin_reply'] ?? ''));
                $reviewUsername = (string) $review['username'];
                $reviewAvatarInitial = trim($reviewUsername) !== ''
                    ? mb_strtoupper(mb_substr($reviewUsername, 0, 1, 'UTF-8'), 'UTF-8')
                    : 'K';
                ?>
                <article class="review-item<?php echo ($ownReview !== null && $reviewId === (int) $ownReview['id']) ? ' is-own-review' : ''; ?>">
                    <div class="review-avatar" aria-hidden="true">
                        <?php echo View::e($reviewAvatarInitial); ?>
                    </div>
                    <div class="review-content">
                        <div class="review-author-line">
                            <strong><?php echo View::e(ReviewerNameFormatter::mask($reviewUsername)); ?></strong>
                            <?php if ($ownReview !== null && $reviewId === (int) $ownReview['id']): ?>
                                <span class="review-verified" style="background:#e8f4fd;color:#0d6efd;border-color:#b6d4fe;font-weight:700;">Đánh giá của bạn</span>
                            <?php else: ?>
                                <span class="review-verified">✓ Đã mua hàng</span>
                            <?php endif; ?>
                        </div>
                        <div class="review-rating-line">
                            <span class="review-stars" aria-label="<?php echo $reviewStars; ?> trên 5 sao">
                                <?php echo str_repeat('★', $reviewStars) . str_repeat('☆', 5 - $reviewStars); ?>
                            </span>
                            <?php if ($reviewDate !== false): ?>
                                <time datetime="<?php echo View::e(date('Y-m-d', $reviewDate)); ?>"><?php echo date('d/m/Y', $reviewDate); ?></time>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($reviewImagesByReview[$reviewId])): ?>
                            <div class="review-image-list" aria-label="Ảnh do khách hàng gửi">
                                <?php foreach ($reviewImagesByReview[$reviewId] as $reviewImagePath): ?>
                                    <?php $reviewImageUrl = $appBaseUrl . '/' . ltrim($reviewImagePath, '/'); ?>
                                    <a href="<?php echo View::e($reviewImageUrl); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo View::e($reviewImageUrl); ?>" alt="Ảnh đánh giá sản phẩm" loading="lazy">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($reviewComment !== ''): ?>
                            <p class="review-comment"><?php echo nl2br(View::e($reviewComment)); ?></p>
                        <?php endif; ?>
                        <?php if ($adminReply !== ''): ?>
                            <div class="review-admin-reply">
                                <strong>Phản hồi từ <?php echo View::e((string) ($review['reply_admin'] ?: 'ÉCRIN Jewelry')); ?></strong>
                                <p><?php echo nl2br(View::e($adminReply)); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (
                            $ownReview !== null
                            && $reviewId === (int) $ownReview['id']
                            && (int) $ownReview['edit_count'] < 1
                        ): ?>
                            <div class="review-customer-actions">
                                <button
                                    type="button"
                                    class="review-edit-button"
                                    data-review="<?php echo View::e(json_encode([
                                        'id' => $reviewId,
                                        'rating' => $reviewStars,
                                        'comment' => $reviewComment,
                                    ], JSON_UNESCAPED_UNICODE)); ?>"
                                    onclick="openCustomerReviewEdit(this.dataset.review)"
                                >Sửa đánh giá</button>
                                <span>Còn 1 lần chỉnh sửa</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="review-empty">
                <?php echo $reviewFilter > 0 ? 'Chưa có đánh giá ' . $reviewFilter . ' sao cho sản phẩm này.' : 'Sản phẩm chưa có đánh giá. Hãy là người đầu tiên chia sẻ cảm nhận nhé.'; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php if ($reviewPageCount > 1): ?>
        <nav class="review-pagination" aria-label="Các trang đánh giá">
            <?php if ($currentReviewPage > 1): ?>
                <a class="review-page-link" href="<?php echo View::e($reviewPageUrl . ($currentReviewPage - 1) . '#product-reviews'); ?>" aria-label="Trang trước">‹</a>
            <?php endif; ?>
            <?php for ($page = $paginationStart; $page <= $paginationEnd; $page++): ?>
                <a class="review-page-link<?php echo $currentReviewPage === $page ? ' is-active' : ''; ?>"
                   href="<?php echo View::e($reviewPageUrl . $page . '#product-reviews'); ?>"
                   aria-label="Trang <?php echo $page; ?>"
                   <?php echo $currentReviewPage === $page ? 'aria-current="page"' : ''; ?>>
                    <?php echo $page; ?>
                </a>
            <?php endfor; ?>
            <?php if ($currentReviewPage < $reviewPageCount): ?>
                <a class="review-page-link" href="<?php echo View::e($reviewPageUrl . ($currentReviewPage + 1) . '#product-reviews'); ?>" aria-label="Trang sau">›</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <div class="review-form-area">
        <?php if ($canReviewProduct && !$hasReviewedProduct): ?>
            <h3>Viết đánh giá của bạn</h3>
            <p>Chỉ khách hàng đã nhận sản phẩm mới có thể gửi đánh giá.</p>
            <form class="product-review-form" action="<?php echo View::e($appBaseUrl . '/client/danhgia.php'); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?php echo $reviewProductId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo View::e($reviewCsrfToken); ?>">
                <fieldset class="review-star-field">
                    <legend>Mức độ hài lòng <span aria-hidden="true">*</span></legend>
                    <div class="review-star-picker">
                        <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                            <input id="review-rating-<?php echo $stars; ?>" type="radio" name="rating" value="<?php echo $stars; ?>" required>
                            <label for="review-rating-<?php echo $stars; ?>" title="<?php echo $stars; ?> sao" aria-label="<?php echo $stars; ?> sao">★</label>
                        <?php endfor; ?>
                    </div>
                </fieldset>
                <label class="review-comment-label" for="review-comment">Chia sẻ trải nghiệm (không bắt buộc)</label>
                <textarea id="review-comment" name="comment" rows="4" maxlength="2000" placeholder="Chất liệu, thiết kế, đóng gói hoặc trải nghiệm của bạn..."></textarea>
                <label class="review-comment-label" for="review-images">Ảnh đánh giá (tối đa 5 ảnh, 5 MB mỗi ảnh)</label>
                <input id="review-images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" multiple>
                <button class="review-submit-button" type="submit">Gửi đánh giá</button>
            </form>
        <?php elseif ($hasReviewedProduct && (int) $ownReview['edit_count'] < 1): ?>
            <p class="review-form-message">Bạn có thể chỉnh sửa đánh giá của mình một lần. Ảnh đã gửi sẽ được giữ nguyên.</p>
            <?php if (!$ownReviewVisible): ?>
                <button
                    type="button"
                    class="review-edit-button"
                    data-review="<?php echo View::e(json_encode([
                        'id' => (int) $ownReview['id'],
                        'rating' => (int) $ownReview['rating'],
                        'comment' => (string) ($ownReview['comment'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE)); ?>"
                    onclick="openCustomerReviewEdit(this.dataset.review)"
                >Sửa đánh giá</button>
            <?php endif; ?>
        <?php elseif ($hasReviewedProduct): ?>
            <p class="review-form-message">Bạn đã đánh giá và sử dụng lượt chỉnh sửa. Cảm ơn bạn đã chia sẻ trải nghiệm!</p>
        <?php elseif ($isReviewCustomer): ?>
            <p class="review-form-message">Bạn có thể đánh giá sau khi đơn hàng chứa sản phẩm này được giao thành công.</p>
        <?php else: ?>
            <p class="review-form-message">Đăng nhập bằng tài khoản khách hàng đã mua sản phẩm để gửi đánh giá.</p>
            <button type="button" class="review-login-button login-trigger">Đăng nhập</button>
        <?php endif; ?>
    </div>
</section>

<?php if ($hasReviewedProduct && (int) $ownReview['edit_count'] < 1): ?>
    <dialog class="customer-review-edit-dialog" id="customer-review-edit-dialog" aria-labelledby="customer-review-edit-title">
        <form action="<?php echo View::e($appBaseUrl . '/client/danhgia.php'); ?>" method="post">
            <div class="customer-review-edit-header">
                <h3 id="customer-review-edit-title">Sửa đánh giá</h3>
                <button type="button" class="customer-review-edit-close" onclick="closeCustomerReviewEdit()" aria-label="Đóng">×</button>
            </div>
            <p class="customer-review-edit-hint">Bạn còn 1 lần chỉnh sửa. Ảnh đã gửi sẽ được giữ nguyên.</p>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="review_id" id="customer-edit-review-id">
            <input type="hidden" name="product_id" value="<?php echo $reviewProductId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo View::e($reviewCsrfToken); ?>">
            <fieldset class="review-star-field">
                <legend>Mức độ hài lòng <span aria-hidden="true">*</span></legend>
                <div class="review-star-picker">
                    <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                        <input id="customer-edit-rating-<?php echo $stars; ?>" type="radio" name="rating" value="<?php echo $stars; ?>" required>
                        <label for="customer-edit-rating-<?php echo $stars; ?>" title="<?php echo $stars; ?> sao" aria-label="<?php echo $stars; ?> sao">★</label>
                    <?php endfor; ?>
                </div>
            </fieldset>
            <label class="review-comment-label" for="customer-edit-comment">Chia sẻ trải nghiệm</label>
            <textarea id="customer-edit-comment" name="comment" rows="5" maxlength="2000"></textarea>
            <div class="customer-review-edit-actions">
                <button type="button" class="customer-review-cancel" onclick="closeCustomerReviewEdit()">Hủy</button>
                <button type="submit" class="customer-review-save">Lưu chỉnh sửa</button>
            </div>
        </form>
    </dialog>
    <script>
    function openCustomerReviewEdit(reviewData) {
        const review = JSON.parse(reviewData);
        document.getElementById('customer-edit-review-id').value = String(review.id);
        document.getElementById('customer-edit-comment').value = review.comment || '';
        document.querySelectorAll('input[name="rating"][id^="customer-edit-rating-"]').forEach(function (input) {
            input.checked = Number(input.value) === Number(review.rating);
        });
        document.getElementById('customer-review-edit-dialog').showModal();
    }
    function closeCustomerReviewEdit() {
        document.getElementById('customer-review-edit-dialog').close();
    }
    </script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (window.location.hash === '#product-reviews' || new URLSearchParams(window.location.search).has('rating')) {
        setTimeout(function () {
            const ownReviewEl = document.querySelector('.review-item.is-own-review');
            if (ownReviewEl) {
                ownReviewEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                ownReviewEl.style.transition = 'box-shadow 0.4s ease, border-color 0.4s ease';
                ownReviewEl.style.boxShadow = '0 0 0 4px rgba(196, 164, 90, 0.35)';
                setTimeout(function () {
                    ownReviewEl.style.boxShadow = '';
                }, 2500);
            } else {
                const reviewSection = document.getElementById('product-reviews');
                if (reviewSection) {
                    reviewSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        }, 300);
    }
});
</script>
