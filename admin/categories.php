<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';

$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Tác dụng: Xác thực phiên và cấp quyền hạn thao tác danh mục cho quản trị viên
$isManager = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'categories.php';

$message = '';
$messageType = '';

if (empty($_SESSION['categories_csrf_token']) || !is_string($_SESSION['categories_csrf_token'])) {
    $_SESSION['categories_csrf_token'] = bin2hex(random_bytes(32));
}
$categoriesCsrfToken = $_SESSION['categories_csrf_token'];

// Hàm tự động tạo slug từ tên tiếng Việt (để đảm bảo cột slug trong DB luôn hợp lệ)
function makeSlug(string $str): string {
    $search = [
        'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
        'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
        'ì','í','ị','ỉ','ĩ',
        'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
        'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
        'ỳ','ý','ỵ','ỷ','ỹ','đ',
        'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ',
        'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
        'Ì','Í','Ị','Ỉ','Ĩ',
        'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
        'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ',
        'Ỳ','Ý','Ỵ','Ỷ','Ỹ','Đ'
    ];
    $replace = [
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d',
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d'
    ];
    $str = str_replace($search, $replace, $str);
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $str), '-'));
    return $slug !== '' ? $slug : 'cat-' . time();
}

$normalizeCategoryName = static function ($value): string {
    if (!is_string($value)) {
        throw new InvalidArgumentException('Tên danh mục không hợp lệ.');
    }
    $name = preg_replace('/\s+/u', ' ', trim($value));
    if (!is_string($name) || $name === '') {
        throw new InvalidArgumentException('Vui lòng nhập tên danh mục.');
    }
    if (mb_strlen($name, 'UTF-8') > 150) {
        throw new InvalidArgumentException('Tên danh mục không được vượt quá 150 ký tự.');
    }
    return $name;
};
$normalizeCategoryImage = static function ($value): ?string {
    if (!is_string($value)) {
        throw new InvalidArgumentException('Đường dẫn ảnh danh mục không hợp lệ.');
    }
    $image = trim($value);
    if ($image === '') {
        return null;
    }
    if (strlen($image) > 500) {
        throw new InvalidArgumentException('Đường dẫn ảnh danh mục quá dài.');
    }
    if (filter_var($image, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string)parse_url($image, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('Ảnh danh mục chỉ nhận URL HTTP hoặc HTTPS.');
        }
        return $image;
    }
    if (strpos($image, 'assets/') !== 0 || strpos($image, '..') !== false || strpos($image, '\\') !== false) {
        throw new InvalidArgumentException('Ảnh nội bộ phải là đường dẫn hợp lệ bên trong thư mục assets.');
    }
    $assetsRoot = realpath(__DIR__ . '/../assets');
    $imagePath = realpath(__DIR__ . '/../' . $image);
    if ($assetsRoot === false || $imagePath === false
        || !is_file($imagePath)
        || strpos($imagePath, $assetsRoot . DIRECTORY_SEPARATOR) !== 0) {
        throw new InvalidArgumentException('Không tìm thấy tệp ảnh danh mục trong assets.');
    }
    return $image;
};
$getCategoryInput = static function () use ($normalizeCategoryName, $normalizeCategoryImage): array {
    $status = $_POST['status'] ?? 'active';
    if (!is_string($status) || !in_array($status, ['active', 'inactive'], true)) {
        throw new InvalidArgumentException('Trạng thái danh mục không hợp lệ.');
    }
    $description = $_POST['description'] ?? '';
    if (!is_string($description)) {
        throw new InvalidArgumentException('Mô tả danh mục không hợp lệ.');
    }
    return [
        'name' => $normalizeCategoryName($_POST['name'] ?? null),
        'description' => trim($description),
        'image' => $normalizeCategoryImage($_POST['image'] ?? ''),
        'status' => $status,
    ];
};
$assertUniqueCategoryName = static function (string $name, int $categoryId) use ($conn): void {
    $stmt = $conn->prepare('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('Không thể kiểm tra tên danh mục: ' . $conn->error);
    }
    $stmt->bind_param('si', $name, $categoryId);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Không thể kiểm tra tên danh mục: ' . $conn->error);
    }
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    if ($exists) {
        throw new DomainException('Tên danh mục này đã được sử dụng.');
    }
};
$uniqueCategorySlug = static function (string $name, int $categoryId) use ($conn): string {
    $base = substr(makeSlug($name), 0, 80);
    for ($suffix = 1; $suffix < 1000; $suffix++) {
        $candidate = $suffix === 1
            ? $base
            : substr($base, 0, 79 - strlen((string)$suffix)) . '-' . $suffix;
        $stmt = $conn->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ? LIMIT 1');
        if (!$stmt) {
            throw new RuntimeException('Không thể kiểm tra mã đường dẫn danh mục: ' . $conn->error);
        }
        $stmt->bind_param('si', $candidate, $categoryId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Không thể kiểm tra mã đường dẫn danh mục: ' . $conn->error);
        }
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        if (!$exists) {
            return $candidate;
        }
    }
    throw new RuntimeException('Không thể tạo mã đường dẫn duy nhất cho danh mục.');
};

// Xử lý Form Thêm / Sửa / Xóa mềm / Khôi phục.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionStarted = false;
    try {
        if (!is_string($_POST['csrf_token'] ?? null)
            || !hash_equals($categoriesCsrfToken, $_POST['csrf_token'])) {
            throw new DomainException('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.');
        }
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

        if ($action === 'add') {
            $category = $getCategoryInput();
            $conn->begin_transaction();
            $transactionStarted = true;
            $assertUniqueCategoryName($category['name'], 0);
            $slug = $uniqueCategorySlug($category['name'], 0);
            $code = 'DM-' . strtoupper(bin2hex(random_bytes(4)));
            $stmt = $conn->prepare(
                'INSERT INTO categories (code, name, slug, description, image, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                throw new RuntimeException('Không thể chuẩn bị thêm danh mục: ' . $conn->error);
            }
            $stmt->bind_param(
                'ssssss',
                $code,
                $category['name'],
                $slug,
                $category['description'],
                $category['image'],
                $category['status']
            );
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Không thể thêm danh mục: ' . $conn->error);
            }
            $stmt->close();
            $conn->commit();
            $transactionStarted = false;
            $message = 'Đã thêm danh mục thành công.';
            $messageType = 'success';
        } elseif ($action === 'edit') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new InvalidArgumentException('Mã danh mục không hợp lệ.');
            }
            $category = $getCategoryInput();
            $conn->begin_transaction();
            $transactionStarted = true;
            $lock = $conn->prepare('SELECT id FROM categories WHERE id = ? AND is_deleted = 0 FOR UPDATE');
            if (!$lock) {
                throw new RuntimeException('Không thể khóa danh mục cần sửa: ' . $conn->error);
            }
            $lock->bind_param('i', $id);
            if (!$lock->execute()) {
                $lock->close();
                throw new RuntimeException('Không thể tải danh mục cần sửa: ' . $conn->error);
            }
            $exists = $lock->get_result()->num_rows === 1;
            $lock->close();
            if (!$exists) {
                throw new DomainException('Danh mục không tồn tại hoặc đã bị xóa mềm.');
            }
            $assertUniqueCategoryName($category['name'], (int)$id);
            $slug = $uniqueCategorySlug($category['name'], (int)$id);
            $stmt = $conn->prepare(
                'UPDATE categories
                 SET name = ?, slug = ?, description = ?, image = ?, status = ?, updated_at = NOW()
                 WHERE id = ? AND is_deleted = 0'
            );
            if (!$stmt) {
                throw new RuntimeException('Không thể chuẩn bị cập nhật danh mục: ' . $conn->error);
            }
            $stmt->bind_param(
                'sssssi',
                $category['name'],
                $slug,
                $category['description'],
                $category['image'],
                $category['status'],
                $id
            );
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Không thể cập nhật danh mục: ' . $conn->error);
            }
            $stmt->close();
            $conn->commit();
            $transactionStarted = false;
            $message = 'Đã cập nhật danh mục.';
            $messageType = 'success';
        } elseif ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new InvalidArgumentException('Mã danh mục không hợp lệ.');
            }
            $conn->begin_transaction();
            $transactionStarted = true;
            $lock = $conn->prepare('SELECT id FROM categories WHERE id = ? AND is_deleted = 0 FOR UPDATE');
            if (!$lock) {
                throw new RuntimeException('Không thể khóa danh mục cần xóa: ' . $conn->error);
            }
            $lock->bind_param('i', $id);
            if (!$lock->execute()) {
                $lock->close();
                throw new RuntimeException('Không thể tải danh mục cần xóa: ' . $conn->error);
            }
            $exists = $lock->get_result()->num_rows === 1;
            $lock->close();
            if (!$exists) {
                throw new DomainException('Danh mục không tồn tại hoặc đã bị xóa mềm.');
            }

            $productCountStmt = $conn->prepare('SELECT COUNT(*) AS total FROM products WHERE category_id = ?');
            if (!$productCountStmt) {
                throw new RuntimeException('Không thể kiểm tra sản phẩm thuộc danh mục: ' . $conn->error);
            }
            $productCountStmt->bind_param('i', $id);
            if (!$productCountStmt->execute()) {
                $productCountStmt->close();
                throw new RuntimeException('Không thể kiểm tra sản phẩm thuộc danh mục: ' . $conn->error);
            }
            $productCount = (int)($productCountStmt->get_result()->fetch_assoc()['total'] ?? 0);
            $productCountStmt->close();
            if ($productCount > 0) {
                throw new DomainException(
                    "Danh mục đang có {$productCount} sản phẩm. Hãy chuyển sản phẩm sang danh mục khác trong Quản lý sản phẩm trước khi xóa."
                );
            }

            $deletedBy = (string)($_SESSION['admin_username'] ?? $_SESSION['username'] ?? 'admin');
            $stmt = $conn->prepare(
                'UPDATE categories
                 SET is_deleted = 1, deleted_at = NOW(), deleted_by = ?, updated_at = NOW()
                 WHERE id = ? AND is_deleted = 0'
            );
            if (!$stmt) {
                throw new RuntimeException('Không thể chuẩn bị xóa mềm danh mục: ' . $conn->error);
            }
            $stmt->bind_param('si', $deletedBy, $id);
            if (!$stmt->execute()) {
                $stmt->close();
                throw new RuntimeException('Không thể xóa mềm danh mục: ' . $conn->error);
            }
            $stmt->close();
            $conn->commit();
            $transactionStarted = false;
            $message = 'Đã xóa mềm danh mục.';
            $messageType = 'success';
        } elseif ($action === 'restore') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new InvalidArgumentException('Mã danh mục không hợp lệ.');
            }
            $conn->begin_transaction();
            $transactionStarted = true;
            $lock = $conn->prepare(
                "SELECT id, name, code, status
                 FROM categories
                 WHERE id = ? AND is_deleted = 1
                 FOR UPDATE"
            );
            if (!$lock) {
                throw new RuntimeException('Không thể khóa danh mục cần khôi phục: ' . $conn->error);
            }
            $lock->bind_param('i', $id);
            if (!$lock->execute()) {
                $lock->close();
                throw new RuntimeException('Không thể tải danh mục cần khôi phục: ' . $conn->error);
            }
            $deletedCategory = $lock->get_result()->fetch_assoc();
            $lock->close();
            if (!$deletedCategory) {
                throw new DomainException('Danh mục không tồn tại trong thùng rác.');
            }
            if (!in_array($deletedCategory['status'], ['active', 'inactive'], true)
                || trim((string)$deletedCategory['code']) === '') {
                throw new DomainException('Danh mục có trạng thái hoặc mã không hợp lệ nên chưa thể khôi phục.');
            }
            $assertUniqueCategoryName((string)$deletedCategory['name'], (int)$id);
            $restore = $conn->prepare(
                'UPDATE categories
                 SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL, updated_at = NOW()
                 WHERE id = ? AND is_deleted = 1'
            );
            if (!$restore) {
                throw new RuntimeException('Không thể chuẩn bị khôi phục danh mục: ' . $conn->error);
            }
            $restore->bind_param('i', $id);
            if (!$restore->execute()) {
                $restore->close();
                throw new RuntimeException('Không thể khôi phục danh mục: ' . $conn->error);
            }
            $restore->close();
            $conn->commit();
            $transactionStarted = false;
            $message = 'Đã khôi phục danh mục. Trạng thái hoạt động hiện tại được giữ nguyên.';
            $messageType = 'success';
        } else {
            throw new InvalidArgumentException('Thao tác danh mục không hợp lệ.');
        }
    } catch (Throwable $exception) {
        if ($transactionStarted) {
            $conn->rollback();
        }
        if (!($exception instanceof DomainException) && !($exception instanceof InvalidArgumentException)) {
            error_log('Category management failed: ' . $exception->getMessage());
        }
        $message = $exception instanceof DomainException || $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Không thể hoàn tất thao tác danh mục. Vui lòng thử lại.';
        $messageType = 'error';
    }
}

// Include products in the count so deleted products are also reassigned before soft deletion.
$categories = [];
$res = $conn->query(
    'SELECT c.id, c.code, c.name, c.slug, c.description, c.image, c.status,
            c.is_deleted, c.created_at, c.updated_at,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c
     ORDER BY c.is_deleted ASC, c.id ASC'
);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    error_log('Admin category list failed: ' . $conn->error);
    $message = 'Không thể tải danh sách danh mục.';
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý danh mục - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .cat-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .cat-panel h2 { margin: 0 0 16px; font-size: 1.15rem; color: var(--navy); }
        .cat-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); align-items: end; gap: 12px; }
        .cat-form label { display: grid; gap: 6px; font-weight: 600; font-size: 14px; color: var(--navy); }
        .cat-form input, .cat-form select, .cat-form textarea { width: 100%; padding: 10px 14px; border: 1px solid #cbd4df; border-radius: 8px; font: inherit; outline: none; }
        .cat-form input:focus, .cat-form select:focus, .cat-form textarea:focus { border-color: var(--navy); }
        .cat-form textarea { min-height: 42px; resize: vertical; }
        .cat-form .form-actions { display: flex; align-items: flex-end; }
        .cat-status { display: inline-flex; padding: 4px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .cat-status-active { background: #ecfdf3; color: #027a48; }
        .cat-status-inactive, .cat-status-deleted { background: #f2f4f7; color: #475467; }
        .cat-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; background: #f2f4f7; }
        .cat-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; min-width: 220px; }
        .cat-warning { color: #b54708; font-size: 12px; margin-top: 5px; }
        .btn-primary { padding: 10px 20px; border: 0; border-radius: 8px; background: var(--navy); color: #fff; font: 600 14px inherit; cursor: pointer; transition: .2s; }
        .btn-primary:hover { background: #1a3c68; }

        .cat-table { width: 100%; border-collapse: collapse; }
        .cat-table th, .cat-table td { padding: 14px; border-bottom: 1px solid var(--line); text-align: left; }
        .cat-table th { background: #fafbfc; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .cat-table tr:hover { background: #fafbfc; }
        .btn-sm { padding: 6px 14px; border: 0; border-radius: 6px; font: 600 13px inherit; cursor: pointer; transition: .2s; }
        .btn-edit { background: #e0f2fe; color: #026aa2; }
        .btn-edit:hover { background: #b9e6fe; }
        .btn-delete { background: #fee4e2; color: #b42318; margin-left: 6px; }
        .btn-delete:hover { background: #fecdca; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .alert-error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        /* Modal Sửa */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 39, 71, .5); z-index: 100; align-items: center; justify-content: center; backdrop-filter: blur(2px); }
        .modal.is-open { display: flex; }
        .modal-box { width: min(600px, 92%); background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,.15); }
        .modal-box h3 { margin: 0 0 16px; color: var(--navy); font-size: 1.2rem; }
        .modal-box label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px; color: var(--navy); }
        .modal-box input, .modal-box select, .modal-box textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd4df; border-radius: 8px; margin-bottom: 16px; font: inherit; }
        .modal-box textarea { min-height: 90px; resize: vertical; }
        .modal-box .actions { display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { padding: 9px 16px; border: 1px solid #cbd4df; border-radius: 8px; background: #fff; cursor: pointer; font: inherit; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý danh mục</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>

        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý danh mục</h1>
                    <p class="page-subtitle">Thêm, chỉnh sửa và quản lý danh mục sản phẩm ÉCRIN.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $escape($message); ?></div>
            <?php endif; ?>

            <section class="cat-panel">
                <h2>Thêm danh mục</h2>
                <form method="post" class="cat-form">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
                    <label>
                        Tên danh mục
                        <input type="text" name="name" maxlength="150" placeholder="Nhập tên danh mục mới..." required>
                    </label>
                    <label>
                        Mô tả
                        <textarea name="description" rows="2"></textarea>
                    </label>
                    <label>
                        Ảnh đại diện (URL hoặc đường dẫn trong assets)
                        <input type="text" name="image" maxlength="500" placeholder="assets/... hoặc https://...">
                    </label>
                    <label>
                        Trạng thái
                        <select name="status">
                            <option value="active">Hoạt động</option>
                            <option value="inactive">Ngừng hoạt động</option>
                        </select>
                    </label>
                    <div class="form-actions"><button type="submit" class="btn-primary">Lưu danh mục</button></div>
                </form>
            </section>

            <section class="cat-panel">
                <h2>Danh sách danh mục</h2>
                <div style="overflow-x:auto">
                    <table class="cat-table">
                        <thead>
                            <tr>
                                <th>Mã</th>
                                <th>Ảnh</th>
                                <th>Tên / mô tả</th>
                                <th>Trạng thái</th>
                                <th>Sản phẩm</th>
                                <th>Thời điểm</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr><td colspan="7" style="text-align:center; color: var(--muted);">Chưa có danh mục nào.</td></tr>
                            <?php else: ?>
                                <?php foreach ($categories as $cat): ?>
                                <?php
                                    $categoryImageUrl = trim((string)($cat['image'] ?? '')) !== ''
                                        ? (preg_match('~^https?://~i', (string)$cat['image'])
                                            ? (string)$cat['image']
                                            : $appBaseUrl . '/' . ltrim((string)$cat['image'], '/'))
                                        : $appBaseUrl . '/assets/banner/logo.png';
                                    $categoryIsDeleted = (int)$cat['is_deleted'] === 1;
                                    $categoryIsActive = $cat['status'] === 'active' && !$categoryIsDeleted;
                                ?>
                                <tr>
                                    <td><strong><?php echo $escape($cat['code']); ?></strong></td>
                                    <td><img class="cat-thumb" src="<?php echo $escape($categoryImageUrl); ?>" alt="" onerror="this.onerror=null;this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>';"></td>
                                    <td>
                                        <strong><?php echo $escape($cat['name']); ?></strong>
                                        <?php if (trim((string)$cat['description']) !== ''): ?>
                                            <div><?php echo nl2br($escape($cat['description'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($categoryIsDeleted): ?>
                                            <span class="cat-status cat-status-deleted">Đã xóa mềm</span>
                                        <?php elseif ($categoryIsActive): ?>
                                            <span class="cat-status cat-status-active">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="cat-status cat-status-inactive">Ngừng hoạt động</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (int)$cat['product_count']; ?></td>
                                    <td>
                                        <small>Tạo: <?php echo $escape($cat['created_at']); ?></small><br>
                                        <small>Cập nhật: <?php echo $escape($cat['updated_at']); ?></small>
                                    </td>
                                    <td>
                                        <div class="cat-actions">
                                        <?php if ($categoryIsDeleted): ?>
                                            <form method="post" onsubmit="return confirm('Khôi phục danh mục này? Trạng thái hoạt động hiện tại sẽ được giữ nguyên.');">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
                                                <button type="submit" class="btn-sm btn-edit">Khôi phục</button>
                                            </form>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                class="btn-sm btn-edit"
                                                data-id="<?php echo (int)$cat['id']; ?>"
                                                data-name="<?php echo $escape($cat['name']); ?>"
                                                data-description="<?php echo $escape($cat['description']); ?>"
                                                data-image="<?php echo $escape($cat['image']); ?>"
                                                data-status="<?php echo $escape($cat['status']); ?>"
                                                onclick="openEditModal(this)">Sửa</button>
                                            <?php if ((int)$cat['product_count'] > 0): ?>
                                                <div class="cat-warning">
                                                    Còn <?php echo (int)$cat['product_count']; ?> sản phẩm. Hãy chuyển chúng sang danh mục khác trước.
                                                </div>
                                            <?php else: ?>
                                                <form method="post" onsubmit="return confirm('Xóa mềm danh mục này?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$cat['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
                                                    <button type="submit" class="btn-sm btn-delete">Xóa mềm</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
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

<!-- Modal Sửa danh mục -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <h3>Chỉnh sửa danh mục</h3>
        <form method="post">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="csrf_token" value="<?php echo $escape($categoriesCsrfToken); ?>">
            <input type="hidden" name="id" id="editId">
            <label for="editName">Tên danh mục:</label>
            <input type="text" name="name" id="editName" maxlength="150" required>
            <label for="editDescription">Mô tả:</label>
            <textarea name="description" id="editDescription"></textarea>
            <label for="editImage">Ảnh đại diện (URL hoặc đường dẫn trong assets):</label>
            <input type="text" name="image" id="editImage" maxlength="500" placeholder="assets/... hoặc https://...">
            <label for="editStatus">Trạng thái:</label>
            <select name="status" id="editStatus">
                <option value="active">Hoạt động</option>
                <option value="inactive">Ngừng hoạt động</option>
            </select>
            <div class="actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn-primary">Cập nhật</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(button) {
    document.getElementById('editId').value = button.dataset.id;
    document.getElementById('editName').value = button.dataset.name;
    document.getElementById('editDescription').value = button.dataset.description;
    document.getElementById('editImage').value = button.dataset.image;
    document.getElementById('editStatus').value = button.dataset.status;
    document.getElementById('editModal').classList.add('is-open');
    document.getElementById('editName').focus();
}

function closeEditModal() {
    document.getElementById('editModal').classList.remove('is-open');
}

// Bấm ra ngoài modal để đóng
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
</body>
</html>
