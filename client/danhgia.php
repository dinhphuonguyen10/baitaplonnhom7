<?php
// Hỗ trợ xác minh đơn đã giao và ẩn danh khách hàng trong danh sách đánh giá.
function customerPurchasedProduct(mysqli $conn, int $userId, int $productId): bool
{
    $stmt = $conn->prepare(
        "SELECT 1
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
 WHERE o.user_id = ? AND oi.product_id = ? AND o.status IN ('Đã giao hàng', 'Đã giao')
         LIMIT 1"
    );
    if (!$stmt) {
        throw new RuntimeException('Không thể kiểm tra đơn hàng đã giao: ' . $conn->error);
    }

    $stmt->bind_param('ii', $userId, $productId);
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Không thể kiểm tra đơn hàng đã giao: ' . $error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Không thể đọc kết quả kiểm tra đơn hàng: ' . $error);
    }

    $hasPurchased = $result->num_rows > 0;
    $stmt->close();
    return $hasPurchased;
}

function maskProductReviewerName(string $username): string
{
    $username = trim($username);
    if ($username === '') {
        return 'Khách hàng';
    }

    $length = mb_strlen($username, 'UTF-8');
    if ($length <= 2) {
        return mb_substr($username, 0, 1, 'UTF-8') . '***';
    }

    return mb_substr($username, 0, 1, 'UTF-8')
        . str_repeat('*', min(4, $length - 2))
        . mb_substr($username, -1, 1, 'UTF-8');
}

// Xử lý POST khi danhgia.php được gọi trực tiếp làm endpoint gửi đánh giá.
$isDirectReviewRequest = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if ($isDirectReviewRequest) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Phương thức không được hỗ trợ.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../include/auto_reply_helper.php';

    $productIdInput = $_POST['product_id'] ?? null;
    $productId = is_scalar($productIdInput) ? filter_var($productIdInput, FILTER_VALIDATE_INT) : false;
    $productId = ($productId !== false && $productId !== null && $productId > 0) ? $productId : 0;
    $reviewAction = $_POST['action'] ?? 'create';

    $redirectWithMessage = static function (string $type, string $message) use ($productId): void {
        $_SESSION['product_review_flash'] = ['type' => $type, 'message' => $message];
        header('Location: product.php?id=' . $productId . '#product-reviews');
        exit;
    };

    if (!is_string($reviewAction) || !in_array($reviewAction, ['create', 'update'], true)) {
        $redirectWithMessage('error', 'Thao tác đánh giá không hợp lệ.');
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0 || ($_SESSION['role'] ?? '') !== 'customer') {
        $redirectWithMessage('error', 'Vui lòng đăng nhập bằng tài khoản khách hàng để gửi đánh giá.');
    }

    $sessionToken = $_SESSION['product_review_csrf'] ?? '';
    $postedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($postedToken) || !hash_equals($sessionToken, $postedToken)) {
        $redirectWithMessage('error', 'Phiên gửi đánh giá không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }

    $ratingInput = $_POST['rating'] ?? null;
    $rating = is_scalar($ratingInput) ? filter_var($ratingInput, FILTER_VALIDATE_INT) : false;
    if ($productId <= 0 || $rating === false || $rating === null || $rating < 1 || $rating > 5) {
        $redirectWithMessage('error', 'Thông tin đánh giá không hợp lệ.');
    }

    $comment = $_POST['comment'] ?? '';
    if (!is_string($comment)) {
        $redirectWithMessage('error', 'Nội dung đánh giá không hợp lệ.');
    }
    $comment = trim($comment);
    if (mb_strlen($comment, 'UTF-8') > 2000) {
        $redirectWithMessage('error', 'Nội dung đánh giá không được vượt quá 2.000 ký tự.');
    }

    $reviewImageCandidates = [];
    if (isset($_FILES['images'])) {
        $uploadedFiles = $_FILES['images'];
        if (!is_array($uploadedFiles)
            || !isset($uploadedFiles['name'], $uploadedFiles['error'], $uploadedFiles['tmp_name'])
            || !is_array($uploadedFiles['name'])
            || !is_array($uploadedFiles['error'])
            || !is_array($uploadedFiles['tmp_name'])) {
            $redirectWithMessage('error', 'Danh sách ảnh tải lên không hợp lệ.');
        }

        $fileCount = count($uploadedFiles['name']);
        if ($fileCount > 5) {
            $redirectWithMessage('error', 'Bạn chỉ có thể đính kèm tối đa 5 ảnh.');
        }

        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            error_log('Unable to initialize fileinfo for product review images.');
            $redirectWithMessage('error', 'Máy chủ chưa thể kiểm tra ảnh tải lên.');
        }

        $allowedImageTypes = [
            'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
            'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
            'image/webp' => ['extension' => 'webp', 'type' => IMAGETYPE_WEBP],
        ];
        for ($fileIndex = 0; $fileIndex < $fileCount; $fileIndex++) {
            $uploadError = $uploadedFiles['error'][$fileIndex] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadError === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($uploadError !== UPLOAD_ERR_OK) {
                finfo_close($fileInfo);
                $message = $uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
                    ? 'Mỗi ảnh không được vượt quá 5 MB.'
                    : 'Không thể nhận ảnh tải lên. Vui lòng thử lại.';
                $redirectWithMessage('error', $message);
            }

            $temporaryPath = $uploadedFiles['tmp_name'][$fileIndex] ?? '';
            if (!is_string($temporaryPath) || $temporaryPath === '' || !is_uploaded_file($temporaryPath)) {
                finfo_close($fileInfo);
                $redirectWithMessage('error', 'Tệp ảnh tải lên không hợp lệ.');
            }
            $fileSize = filesize($temporaryPath);
            if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
                finfo_close($fileInfo);
                $redirectWithMessage('error', 'Mỗi ảnh phải có dung lượng tối đa 5 MB.');
            }

            $detectedMime = finfo_file($fileInfo, $temporaryPath);
            $imageInfo = getimagesize($temporaryPath);
            if (!is_string($detectedMime)
                || !isset($allowedImageTypes[$detectedMime])
                || $imageInfo === false
                || ($imageInfo['mime'] ?? '') !== $detectedMime
                || (int) $imageInfo[0] > 16000
                || (int) $imageInfo[1] > 16000
                || (int) $imageInfo[0] * (int) $imageInfo[1] > 50000000
                || (int) ($imageInfo[2] ?? 0) !== $allowedImageTypes[$detectedMime]['type']) {
                finfo_close($fileInfo);
                $redirectWithMessage('error', 'Chỉ nhận ảnh JPG, PNG hoặc WEBP hợp lệ (tối đa 50 MP).');
            }

            $reviewImageCandidates[] = [
                'temporary_path' => $temporaryPath,
                'extension' => $allowedImageTypes[$detectedMime]['extension'],
            ];
        }
        finfo_close($fileInfo);
    }

    try {
        if (!customerPurchasedProduct($conn, $userId, $productId)) {
            $redirectWithMessage('error', 'Bạn chỉ có thể đánh giá sản phẩm trong đơn hàng đã giao thành công.');
        }
    } catch (RuntimeException $exception) {
        error_log($exception->getMessage());
        $redirectWithMessage('error', 'Chưa thể xác minh đơn hàng. Vui lòng thử lại sau.');
    }

    $existingReview = $conn->prepare('SELECT id, edit_count FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1');
    if (!$existingReview) {
        error_log('Không thể kiểm tra đánh giá đã tồn tại: ' . $conn->error);
        $redirectWithMessage('error', 'Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
    }
    $existingReview->bind_param('ii', $productId, $userId);
    if (!$existingReview->execute()) {
        error_log('Không thể kiểm tra đánh giá đã tồn tại: ' . $existingReview->error);
        $existingReview->close();
        $redirectWithMessage('error', 'Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
    }
    $existingResult = $existingReview->get_result();
    if (!$existingResult) {
        error_log('Không thể đọc đánh giá đã tồn tại: ' . $existingReview->error);
        $existingReview->close();
        $redirectWithMessage('error', 'Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
    }
    $existingReviewRow = $existingResult ? $existingResult->fetch_assoc() : null;
    $existingReview->close();
    if ($reviewAction === 'update') {
        $submittedReviewId = filter_var($_POST['review_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$existingReviewRow
            || $submittedReviewId === false
            || (int) $existingReviewRow['id'] !== $submittedReviewId) {
            $redirectWithMessage('error', 'Không tìm thấy đánh giá để chỉnh sửa.');
        }
        if ((int) $existingReviewRow['edit_count'] >= 1) {
            $redirectWithMessage('error', 'Bạn đã sử dụng lượt chỉnh sửa đánh giá này.');
        }
        if ($reviewImageCandidates !== []) {
            $redirectWithMessage('error', 'Ảnh đã gửi được giữ nguyên khi chỉnh sửa đánh giá.');
        }

        $storedComment = $comment === '' ? null : $comment;
        $updateReview = $conn->prepare(
            'UPDATE reviews
             SET rating = ?, comment = ?, edit_count = edit_count + 1
             WHERE id = ? AND product_id = ? AND user_id = ? AND edit_count = 0'
        );
        if (!$updateReview) {
            error_log('Không thể chuẩn bị cập nhật đánh giá: ' . $conn->error);
            $redirectWithMessage('error', 'Chưa thể cập nhật đánh giá lúc này. Vui lòng thử lại sau.');
        }
        $updateReview->bind_param(
            'isiii',
            $rating,
            $storedComment,
            $submittedReviewId,
            $productId,
            $userId
        );
        if (!$updateReview->execute()) {
            error_log('Không thể cập nhật đánh giá: ' . $updateReview->error);
            $updateReview->close();
            $redirectWithMessage('error', 'Chưa thể cập nhật đánh giá lúc này. Vui lòng thử lại sau.');
        }
        $updated = $updateReview->affected_rows === 1;
        $updateReview->close();
        if (!$updated) {
            $redirectWithMessage('error', 'Đánh giá đã được chỉnh sửa hoặc không còn khả dụng.');
        }
        $redirectWithMessage('success', 'Đã cập nhật đánh giá. Bạn không thể sửa lại lần nữa.');
    }

    if ($existingReviewRow) {
        $redirectWithMessage('error', 'Bạn đã gửi đánh giá cho sản phẩm này rồi.');
    }

    $storedComment = $comment === '' ? null : $comment;
    $reviewUploadDirectory = __DIR__ . '/../assets/uploads/reviews';
    $storedImagePaths = [];
    try {
        if ($reviewImageCandidates !== []) {
            if (!is_dir($reviewUploadDirectory)
                && !mkdir($reviewUploadDirectory, 0750, true)
                && !is_dir($reviewUploadDirectory)) {
                throw new RuntimeException('Không thể tạo thư mục lưu ảnh đánh giá.');
            }
            foreach ($reviewImageCandidates as $candidate) {
                $storedName = bin2hex(random_bytes(24)) . '.' . $candidate['extension'];
                $storedPath = $reviewUploadDirectory . DIRECTORY_SEPARATOR . $storedName;
                if (!move_uploaded_file($candidate['temporary_path'], $storedPath)) {
                    throw new RuntimeException('Không thể lưu ảnh đánh giá.');
                }
                $storedImagePaths[] = 'assets/uploads/reviews/' . $storedName;
            }
        }

        $conn->begin_transaction();
        $insertReview = $conn->prepare(
            'INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)'
        );
        if (!$insertReview) {
            throw new RuntimeException('Không thể chuẩn bị lưu đánh giá: ' . $conn->error);
        }
        $insertReview->bind_param('iiis', $productId, $userId, $rating, $storedComment);
        if (!$insertReview->execute()) {
            $errorNumber = $insertReview->errno;
            $errorMessage = $insertReview->error;
            $insertReview->close();
            if ($errorNumber === 1062) {
                throw new mysqli_sql_exception('Bạn đã gửi đánh giá cho sản phẩm này rồi.', 1062);
            }
            throw new RuntimeException('Không thể lưu đánh giá sản phẩm: ' . $errorMessage);
        }
        $reviewId = (int) $conn->insert_id;
        $insertReview->close();

        if ($storedImagePaths !== []) {
            $insertImage = $conn->prepare(
                'INSERT INTO review_images (review_id, image_path, sort_order) VALUES (?, ?, ?)'
            );
            if (!$insertImage) {
                throw new RuntimeException('Không thể chuẩn bị lưu ảnh đánh giá: ' . $conn->error);
            }
            foreach ($storedImagePaths as $sortOrder => $imagePath) {
                $insertImage->bind_param('isi', $reviewId, $imagePath, $sortOrder);
                if (!$insertImage->execute()) {
                    $error = $insertImage->error;
                    $insertImage->close();
                    throw new RuntimeException('Không thể lưu ảnh đánh giá: ' . $error);
                }
            }
            $insertImage->close();
        }
        $conn->commit();
        // Chỉ gửi cảm ơn sau khi một đánh giá mới được lưu thành công.
        sendAutoReply($conn, $userId, 'review_thankyou');
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        foreach ($storedImagePaths as $imagePath) {
            $storedName = basename($imagePath);
            $storedFile = $reviewUploadDirectory . DIRECTORY_SEPARATOR . $storedName;
            if (is_file($storedFile)) {
                unlink($storedFile);
            }
        }
        if ((int) $exception->getCode() === 1062) {
            $redirectWithMessage('error', 'Bạn đã gửi đánh giá cho sản phẩm này rồi.');
        }

        error_log('Không thể lưu đánh giá sản phẩm: ' . $exception->getMessage());
        $redirectWithMessage('error', 'Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
    } catch (Throwable $exception) {
        $conn->rollback();
        foreach ($storedImagePaths as $imagePath) {
            $storedName = basename($imagePath);
            $storedFile = $reviewUploadDirectory . DIRECTORY_SEPARATOR . $storedName;
            if (is_file($storedFile)) {
                unlink($storedFile);
            }
        }
        error_log('Không thể lưu đánh giá sản phẩm: ' . $exception->getMessage());
        $redirectWithMessage('error', 'Chưa thể gửi đánh giá lúc này. Vui lòng thử lại sau.');
    }

    $redirectWithMessage('success', 'Cảm ơn bạn đã chia sẻ đánh giá về sản phẩm!');
}

// Tải tổng hợp sao, bộ lọc và danh sách đánh giá đã xác minh cho trang chi tiết.
if (empty($product['id']) || !isset($conn) || !($conn instanceof mysqli)) {
    return;
}

$reviewProductId = (int) $product['id'];
$reviewSummaryQuery = $conn->prepare(
    "SELECT
        COUNT(*) AS review_count,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COALESCE(SUM(r.rating = 5), 0) AS five_star_count,
        COALESCE(SUM(r.rating = 4), 0) AS four_star_count,
        COALESCE(SUM(r.rating = 3), 0) AS three_star_count,
        COALESCE(SUM(r.rating = 2), 0) AS two_star_count,
        COALESCE(SUM(r.rating = 1), 0) AS one_star_count
     FROM reviews r
     INNER JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ?
       AND r.rating BETWEEN 1 AND 5
       AND EXISTS (
           SELECT 1
           FROM orders o
           INNER JOIN order_items oi ON oi.order_id = o.id
           WHERE o.user_id = r.user_id
             AND oi.product_id = r.product_id
 AND o.status IN ('Đã giao hàng', 'Đã giao')
       )"
);
if (!$reviewSummaryQuery) {
    throw new RuntimeException('Không thể tải tổng hợp đánh giá: ' . $conn->error);
}
$reviewSummaryQuery->bind_param('i', $reviewProductId);
if (!$reviewSummaryQuery->execute()) {
    throw new RuntimeException('Không thể tải tổng hợp đánh giá: ' . $reviewSummaryQuery->error);
}
$reviewSummaryResult = $reviewSummaryQuery->get_result();
if (!$reviewSummaryResult) {
    $error = $reviewSummaryQuery->error;
    $reviewSummaryQuery->close();
    throw new RuntimeException('Không thể đọc tổng hợp đánh giá: ' . $error);
}
$reviewSummary = $reviewSummaryResult->fetch_assoc() ?: [];
$reviewSummaryQuery->close();

$reviewCount = (int) ($reviewSummary['review_count'] ?? 0);
$averageRating = min(5.0, max(0.0, (float) ($reviewSummary['average_rating'] ?? 0)));
$roundedAverageStars = min(5, max(0, (int) round($averageRating)));
$ratingCounts = [
    5 => (int) ($reviewSummary['five_star_count'] ?? 0),
    4 => (int) ($reviewSummary['four_star_count'] ?? 0),
    3 => (int) ($reviewSummary['three_star_count'] ?? 0),
    2 => (int) ($reviewSummary['two_star_count'] ?? 0),
    1 => (int) ($reviewSummary['one_star_count'] ?? 0),
];

$requestedRating = filter_input(INPUT_GET, 'rating', FILTER_VALIDATE_INT);
$reviewFilter = ($requestedRating !== false && $requestedRating !== null && $requestedRating >= 1 && $requestedRating <= 5)
    ? $requestedRating
    : 0;
$requestedReviewPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$reviewPageSize = 10;
$matchingReviewCount = $reviewFilter > 0 ? $ratingCounts[$reviewFilter] : $reviewCount;
$reviewPageCount = max(1, (int) ceil($matchingReviewCount / $reviewPageSize));
$currentReviewPage = ($requestedReviewPage !== false && $requestedReviewPage !== null && $requestedReviewPage > 0)
    ? min($requestedReviewPage, $reviewPageCount)
    : 1;
$reviewOffset = ($currentReviewPage - 1) * $reviewPageSize;
$reviewListSql = "SELECT r.id, r.rating, r.comment, r.created_at, u.username,
                         rr.reply AS admin_reply, rr.admin_username AS reply_admin, rr.replied_at
                  FROM reviews r
                  INNER JOIN users u ON u.id = r.user_id
                  LEFT JOIN review_replies rr ON rr.review_id = r.id
                  WHERE r.product_id = ?
                    AND r.rating BETWEEN 1 AND 5
                    AND EXISTS (
                        SELECT 1
                        FROM orders o
                        INNER JOIN order_items oi ON oi.order_id = o.id
                        WHERE o.user_id = r.user_id
                          AND oi.product_id = r.product_id
 AND o.status IN ('Đã giao hàng', 'Đã giao')
                    )";
if ($reviewFilter > 0) {
    $reviewListSql .= ' AND r.rating = ?';
}
$reviewListSql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT ? OFFSET ?';
$reviewListQuery = $conn->prepare($reviewListSql);
if (!$reviewListQuery) {
    throw new RuntimeException('Không thể tải danh sách đánh giá: ' . $conn->error);
}
if ($reviewFilter > 0) {
    $reviewListQuery->bind_param('iiii', $reviewProductId, $reviewFilter, $reviewPageSize, $reviewOffset);
} else {
    $reviewListQuery->bind_param('iii', $reviewProductId, $reviewPageSize, $reviewOffset);
}
if (!$reviewListQuery->execute()) {
    throw new RuntimeException('Không thể tải danh sách đánh giá: ' . $reviewListQuery->error);
}
$reviewResult = $reviewListQuery->get_result();
if (!$reviewResult) {
    $error = $reviewListQuery->error;
    $reviewListQuery->close();
    throw new RuntimeException('Không thể đọc danh sách đánh giá: ' . $error);
}
$reviewRows = $reviewResult->fetch_all(MYSQLI_ASSOC);
$reviewListQuery->close();
$reviewImagesByReview = [];
if ($reviewRows !== []) {
    $reviewIds = array_map(static fn(array $review): int => (int) $review['id'], $reviewRows);
    $imagePlaceholders = implode(',', array_fill(0, count($reviewIds), '?'));
    $imageQuery = $conn->prepare(
        "SELECT review_id, image_path FROM review_images
         WHERE review_id IN ({$imagePlaceholders})
         ORDER BY review_id, sort_order, id"
    );
    if (!$imageQuery) {
        throw new RuntimeException('Không thể tải ảnh đánh giá: ' . $conn->error);
    }
    $imageTypes = str_repeat('i', count($reviewIds));
    $imageBindArguments = [$imageTypes];
    foreach ($reviewIds as &$reviewIdForImage) {
        $imageBindArguments[] = &$reviewIdForImage;
    }
    call_user_func_array([$imageQuery, 'bind_param'], $imageBindArguments);
    if (!$imageQuery->execute()) {
        $error = $imageQuery->error;
        $imageQuery->close();
        throw new RuntimeException('Không thể tải ảnh đánh giá: ' . $error);
    }
    $imageResult = $imageQuery->get_result();
    while ($imageRow = $imageResult->fetch_assoc()) {
        $reviewImagesByReview[(int) $imageRow['review_id']][] = (string) $imageRow['image_path'];
    }
    $imageQuery->close();
}

$reviewUserId = (int) ($_SESSION['user_id'] ?? 0);
$isReviewCustomer = $reviewUserId > 0 && ($_SESSION['role'] ?? '') === 'customer';
$canReviewProduct = $isReviewCustomer && customerPurchasedProduct($conn, $reviewUserId, $reviewProductId);
$hasReviewedProduct = false;
$ownReview = null;
if ($isReviewCustomer && $canReviewProduct) {
    $ownReviewQuery = $conn->prepare(
        'SELECT id, rating, comment, edit_count FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1'
    );
    if (!$ownReviewQuery) {
        throw new RuntimeException('Không thể kiểm tra đánh giá của bạn: ' . $conn->error);
    }
    $ownReviewQuery->bind_param('ii', $reviewProductId, $reviewUserId);
    if (!$ownReviewQuery->execute()) {
        throw new RuntimeException('Không thể kiểm tra đánh giá của bạn: ' . $ownReviewQuery->error);
    }
    $ownReviewResult = $ownReviewQuery->get_result();
    if (!$ownReviewResult) {
        $error = $ownReviewQuery->error;
        $ownReviewQuery->close();
        throw new RuntimeException('Không thể đọc đánh giá của bạn: ' . $error);
    }
    $ownReview = $ownReviewResult ? $ownReviewResult->fetch_assoc() : null;
    $hasReviewedProduct = $ownReview !== null;
    $ownReviewQuery->close();
}
$ownReviewVisible = false;
if ($ownReview !== null) {
    foreach ($reviewRows as $reviewRow) {
        if ((int) $reviewRow['id'] === (int) $ownReview['id']) {
            $ownReviewVisible = true;
            break;
        }
    }
}

if (empty($_SESSION['product_review_csrf'])) {
    $_SESSION['product_review_csrf'] = bin2hex(random_bytes(32));
}
$reviewCsrfToken = $_SESSION['product_review_csrf'];
$reviewFlash = $_SESSION['product_review_flash'] ?? null;
unset($_SESSION['product_review_flash']);
$reviewEscape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$reviewBaseUrl = $appBaseUrl . '/client/product.php?id=' . $reviewProductId;
$reviewPageUrl = $reviewBaseUrl . ($reviewFilter > 0 ? '&rating=' . $reviewFilter : '') . '&page=';
$paginationStart = max(1, $currentReviewPage - 2);
$paginationEnd = min($reviewPageCount, $paginationStart + 4);
$paginationStart = max(1, $paginationEnd - 4);
?>

<section class="product-reviews-section" id="product-reviews" aria-labelledby="product-reviews-title">
    <div class="product-reviews-heading">
        <p class="product-reviews-eyebrow">ÉCRIN JEWELRY · TRẢI NGHIỆM KHÁCH HÀNG</p>
        <h2 id="product-reviews-title">Đánh giá sản phẩm</h2>
        <p>Chia sẻ cảm nhận chân thật để giúp khách hàng khác chọn được món trang sức phù hợp.</p>
    </div>

    <?php if (is_array($reviewFlash) && !empty($reviewFlash['message'])): ?>
        <div class="review-notice review-notice-<?php echo ($reviewFlash['type'] ?? '') === 'success' ? 'success' : 'error'; ?>" role="status">
            <?php echo $reviewEscape($reviewFlash['message']); ?>
        </div>
    <?php endif; ?>

    <div class="review-summary">
        <div class="review-score">
            <?php if ($reviewCount > 0): ?>
                <strong><?php echo number_format($averageRating, 1, ',', '.'); ?><span>/5</span></strong>
                <span class="review-summary-stars" aria-label="<?php echo $reviewEscape(number_format($averageRating, 1, ',', '.')); ?> trên 5 sao"><?php echo str_repeat('★', $roundedAverageStars) . str_repeat('☆', 5 - $roundedAverageStars); ?></span>
                <span><?php echo number_format($reviewCount); ?> đánh giá</span>
            <?php else: ?>
                <strong class="review-no-score">Chưa có</strong>
                <span>đánh giá</span>
            <?php endif; ?>
        </div>

        <div class="review-filter-list" aria-label="Lọc đánh giá theo số sao">
            <a class="review-filter-chip<?php echo $reviewFilter === 0 ? ' is-active' : ''; ?>"
               href="<?php echo $reviewEscape($reviewBaseUrl . '#product-reviews'); ?>"
               <?php echo $reviewFilter === 0 ? 'aria-current="page"' : ''; ?>>
                Tất cả (<?php echo $reviewCount; ?>)
            </a>
            <?php foreach ($ratingCounts as $stars => $count): ?>
                <a class="review-filter-chip<?php echo $reviewFilter === $stars ? ' is-active' : ''; ?>"
                   href="<?php echo $reviewEscape($reviewBaseUrl . '&rating=' . $stars . '#product-reviews'); ?>"
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
                <article class="review-item">
                    <div class="review-avatar" aria-hidden="true">
                        <?php echo $reviewEscape($reviewAvatarInitial); ?>
                    </div>
                    <div class="review-content">
                        <div class="review-author-line">
                            <strong><?php echo $reviewEscape(maskProductReviewerName($reviewUsername)); ?></strong>
                            <span class="review-verified">✓ Đã mua hàng</span>
                        </div>
                        <div class="review-rating-line">
                            <span class="review-stars" aria-label="<?php echo $reviewStars; ?> trên 5 sao">
                                <?php echo str_repeat('★', $reviewStars) . str_repeat('☆', 5 - $reviewStars); ?>
                            </span>
                            <?php if ($reviewDate !== false): ?>
                                <time datetime="<?php echo $reviewEscape(date('Y-m-d', $reviewDate)); ?>"><?php echo date('d/m/Y', $reviewDate); ?></time>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($reviewImagesByReview[$reviewId])): ?>
                            <div class="review-image-list" aria-label="Ảnh do khách hàng gửi">
                                <?php foreach ($reviewImagesByReview[$reviewId] as $reviewImagePath): ?>
                                    <?php $reviewImageUrl = $appBaseUrl . '/' . ltrim($reviewImagePath, '/'); ?>
                                    <a href="<?php echo $reviewEscape($reviewImageUrl); ?>" target="_blank" rel="noopener noreferrer">
                                        <img src="<?php echo $reviewEscape($reviewImageUrl); ?>" alt="Ảnh đánh giá sản phẩm" loading="lazy">
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($reviewComment !== ''): ?>
                            <p class="review-comment"><?php echo nl2br($reviewEscape($reviewComment)); ?></p>
                        <?php endif; ?>
                        <?php if ($adminReply !== ''): ?>
                            <div class="review-admin-reply">
                                <strong>Phản hồi từ <?php echo $reviewEscape((string) ($review['reply_admin'] ?: 'ÉCRIN Jewelry')); ?></strong>
                                <p><?php echo nl2br($reviewEscape($adminReply)); ?></p>
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
                                    data-review="<?php echo $reviewEscape(json_encode([
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
                <a class="review-page-link" href="<?php echo $reviewEscape($reviewPageUrl . ($currentReviewPage - 1) . '#product-reviews'); ?>" aria-label="Trang trước">‹</a>
            <?php endif; ?>
            <?php for ($page = $paginationStart; $page <= $paginationEnd; $page++): ?>
                <a class="review-page-link<?php echo $currentReviewPage === $page ? ' is-active' : ''; ?>"
                   href="<?php echo $reviewEscape($reviewPageUrl . $page . '#product-reviews'); ?>"
                   aria-label="Trang <?php echo $page; ?>"
                   <?php echo $currentReviewPage === $page ? 'aria-current="page"' : ''; ?>>
                    <?php echo $page; ?>
                </a>
            <?php endfor; ?>
            <?php if ($currentReviewPage < $reviewPageCount): ?>
                <a class="review-page-link" href="<?php echo $reviewEscape($reviewPageUrl . ($currentReviewPage + 1) . '#product-reviews'); ?>" aria-label="Trang sau">›</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <div class="review-form-area">
        <?php if ($canReviewProduct && !$hasReviewedProduct): ?>
            <h3>Viết đánh giá của bạn</h3>
            <p>Chỉ khách hàng đã nhận sản phẩm mới có thể gửi đánh giá.</p>
            <form class="product-review-form" action="<?php echo $reviewEscape($appBaseUrl . '/client/danhgia.php'); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="product_id" value="<?php echo $reviewProductId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $reviewEscape($reviewCsrfToken); ?>">
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
                    data-review="<?php echo $reviewEscape(json_encode([
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
        <form action="<?php echo $reviewEscape($appBaseUrl . '/client/danhgia.php'); ?>" method="post">
            <div class="customer-review-edit-header">
                <h3 id="customer-review-edit-title">Sửa đánh giá</h3>
                <button type="button" class="customer-review-edit-close" onclick="closeCustomerReviewEdit()" aria-label="Đóng">×</button>
            </div>
            <p class="customer-review-edit-hint">Bạn còn 1 lần chỉnh sửa. Ảnh đã gửi sẽ được giữ nguyên.</p>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="review_id" id="customer-edit-review-id">
            <input type="hidden" name="product_id" value="<?php echo $reviewProductId; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $reviewEscape($reviewCsrfToken); ?>">
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
