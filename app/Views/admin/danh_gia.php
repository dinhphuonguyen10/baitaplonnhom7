<?php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý đánh giá - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .review-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .review-summary { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
        .review-stat { padding: 14px 16px; border-radius: 9px; background: #f5f7fa; }
        .review-stat span { display: block; color: var(--muted); font-size: 12px; }
        .review-stat strong { display: block; margin-top: 5px; color: var(--navy); font-size: 22px; }
        .review-toolbar { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .review-toolbar input, .review-toolbar select { min-height: 40px; padding: 8px 10px; border: 1px solid #d5dbe2; border-radius: 7px; }
        .review-toolbar input { min-width: 230px; }
        .review-toolbar button { border: 0; border-radius: 7px; padding: 8px 14px; background: var(--navy); color: #fff; font-weight: 700; cursor: pointer; }
        .review-notice { margin: 16px 0; padding: 12px 15px; border-radius: 8px; }
        .review-notice-success { background: #ecfdf3; color: #027a48; }
        .review-notice-error { background: #fee4e2; color: #b42318; }
        .review-table-wrap { overflow-x: auto; }
        .review-table { width: 100%; min-width: 980px; border-collapse: collapse; }
        .review-table th, .review-table td { padding: 12px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        .review-table th { color: var(--muted); font-size: 12px; white-space: nowrap; }
        .review-product { display: flex; align-items: center; gap: 9px; min-width: 220px; }
        .review-thumb { width: 48px; height: 48px; flex: 0 0 48px; border: 1px solid var(--line); border-radius: 8px; object-fit: cover; }
        .review-stars { color: #b58c54; font-size: 15px; white-space: nowrap; }
        .review-comment { max-width: 360px; line-height: 1.5; white-space: pre-wrap; }
        .review-image-list { display: flex; flex-wrap: wrap; gap: 6px; min-width: 110px; }
        .review-image-list img { width: 52px; height: 52px; border: 1px solid var(--line); border-radius: 6px; object-fit: cover; }
        .review-reply { max-width: 300px; color: #475569; line-height: 1.5; white-space: pre-wrap; }
        .review-reply strong { display: block; margin-bottom: 4px; color: var(--navy); font-size: 11px; }
        .review-reply-empty { color: var(--muted); font-size: 12px; }
        .review-reply-action { display: inline-flex; align-items: center; justify-content: center; min-width: 78px; padding: 8px 13px; border: 0; border-radius: 7px; background: var(--navy); color: #fff; font: inherit; font-size: 12px; font-weight: 700; line-height: 1.2; cursor: pointer; transition: background .16s ease, box-shadow .16s ease, transform .16s ease; }
        .review-reply-action:hover { background: #2a5298; box-shadow: 0 3px 8px rgba(29, 52, 97, .18); transform: translateY(-1px); }
        .review-reply-action:focus-visible { outline: 3px solid rgba(181, 140, 84, .38); outline-offset: 2px; }
        .review-reply-action:active { transform: translateY(0); box-shadow: 0 1px 3px rgba(29, 52, 97, .18); }
        .verified-badge { display: inline-block; margin-top: 5px; padding: 3px 8px; border-radius: 20px; background: #ecfdf3; color: #027a48; font-size: 11px; font-weight: 700; }
        .unverified-badge { background: #f3f4f6; color: #667085; }
        .review-actions { display: flex; gap: 6px; white-space: nowrap; }
        .review-actions button { border: 0; border-radius: 6px; padding: 7px 9px; cursor: pointer; font-weight: 700; }
        .review-empty { padding: 24px; color: var(--muted); text-align: center; }
        .edit-dialog { width: min(520px, calc(100vw - 32px)); border: 0; border-radius: 12px; padding: 0; box-shadow: 0 20px 60px rgba(15,39,71,.22); }
        .edit-dialog::backdrop { background: rgba(15,39,71,.55); }
        .edit-dialog form { padding: 20px; }
        .edit-dialog h3 { margin: 0 0 16px; color: var(--navy); }
        .edit-dialog label { display: block; margin-top: 12px; font-weight: 700; }
        .edit-dialog select, .edit-dialog textarea { width: 100%; margin-top: 6px; padding: 9px; border: 1px solid #cbd5e1; border-radius: 7px; }
        .edit-dialog textarea { min-height: 130px; resize: vertical; }
        .dialog-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
        .dialog-actions button { border: 0; border-radius: 7px; padding: 9px 13px; font-weight: 700; cursor: pointer; }
        .dialog-save { background: var(--navy); color: #fff; }
        .dialog-cancel { background: #eef2f6; color: var(--navy); }
        @media (max-width: 700px) {
            .review-summary { grid-template-columns: 1fr; }
            .review-toolbar input { min-width: 100%; }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include VIEW_PATH . '/layouts/thanh_ben_quan_tri.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý đánh giá</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>
        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý đánh giá sản phẩm</h1>
                    <p class="page-subtitle">Kiểm duyệt nội dung đánh giá và theo dõi mức hài lòng của khách hàng.</p>
                </div>
            </div>

            <?php if (is_array($notice) && isset($notice['message'])): ?>
                <p class="review-notice review-notice-<?php echo ($notice['type'] ?? '') === 'success' ? 'success' : 'error'; ?>" role="status">
                    <?php echo $escape($notice['message']); ?>
                </p>
            <?php endif; ?>

            <section class="review-panel" aria-labelledby="reviews-title">
                <div class="review-summary">
                    <div class="review-stat"><span>Tổng đánh giá</span><strong><?php echo $totalReviews; ?></strong></div>
                    <div class="review-stat"><span>Điểm trung bình</span><strong><?php echo number_format($averageRating, 1, '.', ''); ?>/5</strong></div>
                    <div class="review-stat"><span>5 sao</span><strong><?php echo $reviewCountByRating[5]; ?></strong></div>
                </div>

                <form class="review-toolbar" method="get" action="<?php echo $escape($appBaseUrl . '/admin/danh_gia.php'); ?>">
                    <input type="search" name="q" value="<?php echo $escape($search); ?>" placeholder="Tìm khách hàng, sản phẩm, nội dung...">
                    <select name="product_id" aria-label="Lọc theo sản phẩm">
                        <option value="0">Tất cả sản phẩm</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo (int) $product['id']; ?>"<?php echo $productFilter === (int) $product['id'] ? ' selected' : ''; ?>>
                                <?php echo $escape($product['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="rating" aria-label="Lọc theo số sao">
                        <option value="0">Tất cả số sao</option>
                        <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                            <option value="<?php echo $rating; ?>"<?php echo $ratingFilter === $rating ? ' selected' : ''; ?>><?php echo $rating; ?> sao</option>
                        <?php endfor; ?>
                    </select>
                    <button type="submit">Lọc</button>
                </form>

                <div class="review-table-wrap">
                    <table class="review-table">
                        <thead>
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Khách hàng</th>
                                <th>Đánh giá</th>
                                <th>Nội dung</th>
                                <th>Ảnh</th>
                                <th>Phản hồi của cửa hàng</th>
                                <th>Ngày gửi</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($reviewsError !== ''): ?>
                            <tr><td class="review-empty" colspan="8"><?php echo $escape($reviewsError); ?></td></tr>
                        <?php elseif ($reviews === []): ?>
                            <tr><td class="review-empty" colspan="8">Không tìm thấy đánh giá phù hợp.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reviews as $review): ?>
                                <?php
                                $reviewId = (int) $review['id'];
                                $rating = (int) $review['rating'];
                                $image = (string) ($review['product_image'] ?? '');
                                $imageUrl = preg_match('~^https?://~i', $image)
                                    ? $image
                                    : $appBaseUrl . '/' . ltrim($image !== '' ? $image : 'assets/banner/logo.png', '/');
                                $date = strtotime((string) $review['created_at']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="review-product">
                                            <img class="review-thumb" src="<?php echo $escape($imageUrl); ?>" alt="" onerror="this.onerror=null;this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>'">
                                            <strong><?php echo $escape($review['product_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $escape($review['username']); ?>
                                        <br>
                                        <span class="verified-badge<?php echo empty($review['verified']) ? ' unverified-badge' : ''; ?>">
                                            <?php echo empty($review['verified']) ? 'Chưa xác minh' : 'Đã mua hàng'; ?>
                                        </span>
                                    </td>
                                    <td><span class="review-stars"><?php echo str_repeat('★', $rating) . str_repeat('☆', 5 - $rating); ?></span></td>
                                    <td><div class="review-comment"><?php echo $escape((string) ($review['comment'] ?? 'Không có nội dung')); ?></div></td>
                                    <td>
                                        <?php if (!empty($reviewImagesByReview[$reviewId])): ?>
                                            <div class="review-image-list">
                                                <?php foreach ($reviewImagesByReview[$reviewId] as $reviewImagePath): ?>
                                                    <?php $reviewImageUrl = $appBaseUrl . '/' . ltrim($reviewImagePath, '/'); ?>
                                                    <a href="<?php echo $escape($reviewImageUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                        <img src="<?php echo $escape($reviewImageUrl); ?>" alt="Ảnh đánh giá" loading="lazy">
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="review-reply-empty">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($review['admin_reply'])): ?>
                                            <div class="review-reply">
                                                <strong><?php echo $escape($review['reply_admin'] ?: 'Quản trị viên'); ?> · <?php echo $escape(date('d/m/Y H:i', strtotime((string) $review['replied_at']))); ?></strong>
                                                <?php echo $escape($review['admin_reply']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="review-reply-empty">Chưa phản hồi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $date ? $escape(date('d/m/Y H:i', $date)) : 'Không rõ'; ?></td>
                                    <td>
                                        <button type="button" class="review-reply-action" onclick='openReviewReply(<?php echo json_encode([
                                            'id' => $reviewId,
                                            'reply' => (string) ($review['admin_reply'] ?? ''),
                                        ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><?php echo empty($review['admin_reply']) ? 'Trả lời' : 'Sửa trả lời'; ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>

<dialog class="edit-dialog" id="review-reply-dialog">
    <form method="post">
        <h3>Phản hồi đánh giá</h3>
        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
        <input type="hidden" name="action" value="reply">
        <input type="hidden" name="review_id" id="reply-review-id">
        <label for="review-reply-text">Nội dung phản hồi</label>
        <textarea name="reply" id="review-reply-text" maxlength="2000" required></textarea>
        <div class="dialog-actions">
            <button type="button" class="dialog-cancel" onclick="closeReviewReply()">Hủy</button>
            <button type="submit" class="dialog-save">Gửi phản hồi</button>
        </div>
    </form>
</dialog>

<script>
function openReviewReply(review) {
    document.getElementById('reply-review-id').value = String(review.id);
    document.getElementById('review-reply-text').value = review.reply || '';
    document.getElementById('review-reply-dialog').showModal();
}
function closeReviewReply() {
    document.getElementById('review-reply-dialog').close();
}
</script>
</body>
</html>
