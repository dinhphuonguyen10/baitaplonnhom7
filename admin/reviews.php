<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';

$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$isManager = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'reviews.php';

if (empty($_SESSION['reviews_csrf'])) {
    $_SESSION['reviews_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['reviews_csrf'];
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $reviewId = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $notice = ['type' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.'];
    } elseif ($reviewId === false || $reviewId < 1) {
        $notice = ['type' => 'error', 'message' => 'Mã đánh giá không hợp lệ.'];
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare('DELETE FROM reviews WHERE id = ?');
        $stmt->bind_param('i', $reviewId);
        if ($stmt->execute()) {
            $notice = ['type' => 'success', 'message' => 'Đã xóa đánh giá khỏi hệ thống.'];
        } else {
            error_log('Admin review delete failed: ' . $stmt->error);
            $notice = ['type' => 'error', 'message' => 'Không thể xóa đánh giá lúc này.'];
        }
        $stmt->close();
    } elseif ($action === 'update') {
        $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        $comment = trim((string) ($_POST['comment'] ?? ''));
        if ($rating === false || $rating < 1 || $rating > 5) {
            $notice = ['type' => 'error', 'message' => 'Số sao phải từ 1 đến 5.'];
        } elseif (mb_strlen($comment, 'UTF-8') > 2000) {
            $notice = ['type' => 'error', 'message' => 'Nội dung đánh giá không được vượt quá 2.000 ký tự.'];
        } else {
            $storedComment = $comment === '' ? null : $comment;
            $stmt = $conn->prepare('UPDATE reviews SET rating = ?, comment = ? WHERE id = ?');
            $stmt->bind_param('isi', $rating, $storedComment, $reviewId);
            if ($stmt->execute()) {
                $notice = ['type' => 'success', 'message' => 'Đã cập nhật đánh giá.'];
            } else {
                error_log('Admin review update failed: ' . $stmt->error);
                $notice = ['type' => 'error', 'message' => 'Không thể cập nhật đánh giá lúc này.'];
            }
            $stmt->close();
        }
    } elseif ($action === 'reply') {
        $reply = trim((string) ($_POST['reply'] ?? ''));
        if ($reply === '') {
            $notice = ['type' => 'error', 'message' => 'Vui lòng nhập nội dung phản hồi.'];
        } elseif (mb_strlen($reply, 'UTF-8') > 2000) {
            $notice = ['type' => 'error', 'message' => 'Nội dung phản hồi không được vượt quá 2.000 ký tự.'];
        } else {
            $adminName = (string) ($_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'Quản trị viên');
            $stmt = $conn->prepare(
                'INSERT INTO review_replies (review_id, admin_username, reply) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE admin_username = VALUES(admin_username), reply = VALUES(reply), replied_at = CURRENT_TIMESTAMP'
            );
            $stmt->bind_param('iss', $reviewId, $adminName, $reply);
            if ($stmt->execute()) {
                $notice = ['type' => 'success', 'message' => 'Đã gửi phản hồi cho đánh giá.'];
            } else {
                error_log('Admin review reply failed: ' . $stmt->error);
                $notice = ['type' => 'error', 'message' => 'Không thể gửi phản hồi lúc này.'];
            }
            $stmt->close();
        }
    } else {
        $notice = ['type' => 'error', 'message' => 'Thao tác không được hỗ trợ.'];
    }

    $_SESSION['review_notice'] = $notice;
    header('Location: ' . $appBaseUrl . '/admin/reviews.php');
    exit;
}

if (isset($_SESSION['review_notice']) && is_array($_SESSION['review_notice'])) {
    $notice = $_SESSION['review_notice'];
    unset($_SESSION['review_notice']);
}

$productFilter = (int) ($_GET['product_id'] ?? 0);
$ratingFilter = (int) ($_GET['rating'] ?? 0);
$search = trim((string) ($_GET['q'] ?? ''));

$products = [];
$productResult = $conn->query('SELECT id, name FROM products ORDER BY name ASC');
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $products[] = $row;
    }
    $productResult->free();
}

$reviews = [];
$reviewImagesByReview = [];
$reviewsError = '';
$where = [];
$types = '';
$params = [];

if ($productFilter > 0) {
    $where[] = 'r.product_id = ?';
    $types .= 'i';
    $params[] = $productFilter;
}
if ($ratingFilter >= 1 && $ratingFilter <= 5) {
    $where[] = 'r.rating = ?';
    $types .= 'i';
    $params[] = $ratingFilter;
}
if ($search !== '') {
    $where[] = '(u.username LIKE ? OR p.name LIKE ? OR r.comment LIKE ? OR rr.reply LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = "SELECT r.id, r.rating, r.comment, r.created_at,
               r.product_id, p.name AS product_name, p.image AS product_image,
               r.user_id, u.username, rr.reply AS admin_reply,
               rr.admin_username AS reply_admin, rr.replied_at
        FROM reviews r
        INNER JOIN users u ON u.id = r.user_id
        INNER JOIN products p ON p.id = r.product_id
        LEFT JOIN review_replies rr ON rr.review_id = r.id";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.created_at DESC, r.id DESC';

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }
    if ($params) {
        $bindArguments = [$types];
        foreach ($params as &$param) {
            $bindArguments[] = &$param;
        }
        call_user_func_array([$stmt, 'bind_param'], $bindArguments);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException($stmt->error);
    }
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $productId = (int) $row['product_id'];
        $userId = (int) $row['user_id'];

        $verifiedStmt = $conn->prepare(
            "SELECT 1
             FROM orders o
             INNER JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ? AND oi.product_id = ?
               AND o.status IN ('Đã giao hàng', 'Đã giao')
             LIMIT 1"
        );
        $verifiedStmt->bind_param('ii', $userId, $productId);
        $verifiedStmt->execute();
        $verifiedResult = $verifiedStmt->get_result();
        $row['verified'] = $verifiedResult->num_rows > 0;
        $verifiedStmt->close();
        $reviews[] = $row;
    }
    $stmt->close();
} catch (Throwable $exception) {
    error_log('Admin review list failed: ' . $exception->getMessage());
    $reviewsError = 'Không thể tải danh sách đánh giá.';
}

$imageResult = $conn->query('SELECT review_id, image_path FROM review_images ORDER BY review_id, sort_order, id');
if (!$imageResult) {
    error_log('Admin review image list failed: ' . $conn->error);
    $reviewsError = 'Không thể tải ảnh đánh giá.';
} else {
    while ($imageRow = $imageResult->fetch_assoc()) {
        $reviewImagesByReview[(int) $imageRow['review_id']][] = (string) $imageRow['image_path'];
    }
    $imageResult->free();
}

$totalReviews = 0;
$averageRating = 0.0;
$reviewCountByRating = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$summaryResult = $conn->query('SELECT rating, COUNT(*) AS total FROM reviews GROUP BY rating');
if ($summaryResult) {
    $ratingSum = 0;
    while ($row = $summaryResult->fetch_assoc()) {
        $rating = (int) $row['rating'];
        $total = (int) $row['total'];
        if ($rating >= 1 && $rating <= 5) {
            $reviewCountByRating[$rating] = $total;
            $ratingSum += $rating * $total;
            $totalReviews += $total;
        }
    }
    $summaryResult->free();
}
if ($totalReviews > 0) {
    $averageRating = $ratingSum / $totalReviews;
}
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
        .review-reply-action { background: #ecfdf3; color: #027a48; }
        .verified-badge { display: inline-block; margin-top: 5px; padding: 3px 8px; border-radius: 20px; background: #ecfdf3; color: #027a48; font-size: 11px; font-weight: 700; }
        .unverified-badge { background: #f3f4f6; color: #667085; }
        .review-actions { display: flex; gap: 6px; white-space: nowrap; }
        .review-actions button { border: 0; border-radius: 6px; padding: 7px 9px; cursor: pointer; font-weight: 700; }
        .review-edit { background: #e0f2fe; color: #026aa2; }
        .review-delete { background: #fee4e2; color: #b42318; }
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
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
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

                <form class="review-toolbar" method="get" action="<?php echo $escape($appBaseUrl . '/admin/reviews.php'); ?>">
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
                                $imageUrl = $image !== '' ? $appBaseUrl . '/' . ltrim($image, '/') : $appBaseUrl . '/assets/banner/logo.png';
                                $date = strtotime((string) $review['created_at']);
                                ?>
                                <tr>
                                    <td>
                                        <div class="review-product">
                                            <img class="review-thumb" src="<?php echo $escape($imageUrl); ?>" alt="">
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
                                        <div class="review-actions">
                                            <button type="button" class="review-edit" onclick='openReviewEdit(<?php echo json_encode([
                                                'id' => $reviewId,
                                                'rating' => $rating,
                                                'comment' => (string) ($review['comment'] ?? ''),
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>Sửa</button>
                                            <button type="button" class="review-reply-action" onclick='openReviewReply(<?php echo json_encode([
                                                'id' => $reviewId,
                                                'reply' => (string) ($review['admin_reply'] ?? ''),
                                            ], JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><?php echo empty($review['admin_reply']) ? 'Trả lời' : 'Sửa trả lời'; ?></button>
                                            <form method="post" onsubmit="return confirm('Xóa đánh giá này?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="review_id" value="<?php echo $reviewId; ?>">
                                                <button type="submit" class="review-delete">Xóa</button>
                                            </form>
                                        </div>
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

<dialog class="edit-dialog" id="review-edit-dialog">
    <form method="post">
        <h3>Chỉnh sửa đánh giá</h3>
        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="review_id" id="edit-review-id">
        <label for="edit-review-rating">Số sao</label>
        <select name="rating" id="edit-review-rating">
            <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                <option value="<?php echo $rating; ?>"><?php echo $rating; ?> sao</option>
            <?php endfor; ?>
        </select>
        <label for="edit-review-comment">Nội dung</label>
        <textarea name="comment" id="edit-review-comment" maxlength="2000"></textarea>
        <div class="dialog-actions">
            <button type="button" class="dialog-cancel" onclick="closeReviewEdit()">Hủy</button>
            <button type="submit" class="dialog-save">Lưu thay đổi</button>
        </div>
    </form>
</dialog>

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
function openReviewEdit(review) {
    document.getElementById('edit-review-id').value = String(review.id);
    document.getElementById('edit-review-rating').value = String(review.rating);
    document.getElementById('edit-review-comment').value = review.comment || '';
    document.getElementById('review-edit-dialog').showModal();
}
function closeReviewEdit() {
    document.getElementById('review-edit-dialog').close();
}
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
