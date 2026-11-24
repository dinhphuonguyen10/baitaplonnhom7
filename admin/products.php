<?php
require_once __DIR__ . '/../include/admin_guard.php';
require_once __DIR__ . '/../config/db.php';

$appBaseUrl = applicationBaseUrl();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
// Tác dụng: Xác thực phiên và cấp quyền hạn thao tác sản phẩm cho quản trị viên
$isManager = isManagerRole($_SESSION['admin_role'] ?? $_SESSION['role'] ?? null);
$roleLabel = $isManager ? 'Quản lý' : 'Nhân viên';
$adminUsername = $_SESSION['admin_username'] ?? $_SESSION['username'] ?? '';
$adminCurrentPage = 'products.php';

$descTemplatesByCategory = [
    2 => 'Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.',
    3 => 'Mặt dây chuyền được chạm khắc tỉ mỉ với đường nét thanh mảnh, uốn lượn mềm mại ôm trọn viên đá chủ lấp lánh như viên ngọc quý giữa lòng đại dương. Sợi dây bạc Ý 925 mạ rhodium cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ, biểu trưng cho tình yêu vĩnh cửu và phong thái kiêu sa, đài các khó phai.',
    4 => 'Thiết kế nhẫn solitaire kinh điển kết hợp kỹ thuật nạm đá tinh xảo bậc nhất, nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut phản chiếu ánh hào quang lộng lẫy tựa kim cương tự nhiên. Sự lựa chọn hoàn mỹ biểu trưng cho tình yêu đích thực và khẳng định phong cách vương giả, độc bản của quý cô hiện đại.',
    5 => 'Chiếc vòng tay ôm khít cổ tay yêu kiều, chế tác công phu từ chất liệu Bạc Ý 925 đúc nguyên khối dày dặn và các hạt charm đính đá vi mô sáng trong vắt. Thiết kế thanh lịch, chống oxy hóa bền lâu, mang lại nguồn năng lượng an lành, duyên dáng và phong thái thượng lưu trong từng cử chỉ tay.',
    6 => 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.'
];

$message = '';
$messageType = '';

// Thư mục upload ảnh
$uploadDir = __DIR__ . '/../assets/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Xử lý Form Thêm / Sửa / Xóa / Khôi phục / Xóa vĩnh viễn
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. THÊM SẢN PHẨM
    if ($action === 'add') {
        $name        = trim($_POST['name'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? 0);
        $price       = (float)($_POST['price'] ?? 0);
        $stock       = max(0, (int)($_POST['stock'] ?? 0));
        $status      = in_array($_POST['status'] ?? '', ['active', 'inactive', 'hidden'], true) ? $_POST['status'] : 'active';
        $description = trim($_POST['description'] ?? '');
        if ($description === '') {
            $description = $descTemplatesByCategory[$categoryId] ?? $descTemplatesByCategory[2];
        }
        $imagePath   = 'assets/banner/logo.png'; // Ảnh mặc định — đồng bộ với logo config nếu có
        $_lpConf = __DIR__ . '/../storage/banner/logo.json';
        if (is_file($_lpConf)) {
            $_lpData = json_decode((string) file_get_contents($_lpConf), true);
            if (is_array($_lpData) && !empty($_lpData['src'])) $imagePath = (string) $_lpData['src'];
        }

        if ($name === '' || $categoryId <= 0) {
            $message = 'Vui lòng nhập tên sản phẩm và chọn danh mục!';
            $messageType = 'error';
        } else {
            // Xử lý upload ảnh nếu có
            if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed, true)) {
                    $newFileName = 'prod_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $newFileName)) {
                        $imagePath = 'assets/uploads/' . $newFileName;
                    }
                }
            } elseif (!empty($_POST['image_url'])) {
                $imagePath = trim($_POST['image_url']);
            }

            $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;
            $isSuggested = isset($_POST['is_suggested']) ? 1 : 0;
            $isNew       = isset($_POST['is_new']) ? 1 : 0;

            $stmt = $conn->prepare("INSERT INTO products (name, category_id, price_sale, stock, image, description, is_featured, is_suggested, is_new, status, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)");
            $stmt->bind_param("sidissiiis", $name, $categoryId, $price, $stock, $imagePath, $description, $isFeatured, $isSuggested, $isNew, $status);
            if ($stmt->execute()) {
                $message = "Đã thêm sản phẩm '{$name}' thành công!";
                $messageType = 'success';
            } else {
                $message = 'Lỗi hệ thống khi thêm sản phẩm: ' . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // 2. SỬA SẢN PHẨM
    elseif ($action === 'edit') {
        $id            = (int)($_POST['id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $categoryId    = (int)($_POST['category_id'] ?? 0);
        $price         = (float)($_POST['price'] ?? 0);
        $priceOriginal = isset($_POST['price_original']) ? (float)$_POST['price_original'] : 0.0;
        $stock         = max(0, (int)($_POST['stock'] ?? 0));
        $status        = in_array($_POST['status'] ?? '', ['active', 'inactive', 'hidden'], true) ? $_POST['status'] : 'active';
        $description   = trim($_POST['description'] ?? '');
        $isFeatured    = isset($_POST['is_featured']) ? 1 : 0;
        $isSuggested   = isset($_POST['is_suggested']) ? 1 : 0;
        $isNew         = isset($_POST['is_new']) ? 1 : 0;
        if ($description === '') {
            $description = $descTemplatesByCategory[$categoryId] ?? $descTemplatesByCategory[2];
        }

        if ($id <= 0 || $name === '' || $categoryId <= 0) {
            $message = 'Dữ liệu chỉnh sửa không hợp lệ!';
            $messageType = 'error';
        } else {
            // Kiểm tra có tải ảnh mới lên không
            $hasNewImage = false;
            $newImagePath = '';
            if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed, true)) {
                    $newFileName = 'prod_' . time() . '_' . mt_rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadDir . $newFileName)) {
                        $newImagePath = 'assets/uploads/' . $newFileName;
                        $hasNewImage = true;
                    }
                }
            } elseif (!empty($_POST['image_url_new'])) {
                $newImagePath = trim($_POST['image_url_new']);
                $hasNewImage = true;
            }

            if ($hasNewImage) {
                $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, price_sale = ?, price_original = ?, stock = ?, image = ?, description = ?, is_featured = ?, is_suggested = ?, is_new = ?, status = ? WHERE id = ?");
                $stmt->bind_param("siddissiiiis", $name, $categoryId, $price, $priceOriginal, $stock, $newImagePath, $description, $isFeatured, $isSuggested, $isNew, $status, $id);
            } else {
                $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, price_sale = ?, price_original = ?, stock = ?, description = ?, is_featured = ?, is_suggested = ?, is_new = ?, status = ? WHERE id = ?");
                $stmt->bind_param("siddsiiiisi", $name, $categoryId, $price, $priceOriginal, $stock, $description, $isFeatured, $isSuggested, $isNew, $status, $id);
            }

            if ($stmt->execute()) {
                $message = "Đã cập nhật thông tin sản phẩm thành công!";
                $messageType = 'success';
            } else {
                $message = 'Lỗi cập nhật sản phẩm: ' . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // 3. XÓA MỀM SẢN PHẨM (SOFT DELETE)
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $adminUser = $adminUsername !== '' ? $adminUsername : 'Admin';
            $stmt = $conn->prepare("UPDATE products SET is_deleted = 1, deleted_at = NOW(), deleted_by = ? WHERE id = ?");
            $stmt->bind_param("si", $adminUser, $id);
            if ($stmt->execute()) {
                $message = "Đã chuyển sản phẩm vào Thùng rác (xóa mềm)! Dữ liệu vẫn được bảo toàn trong database cho lịch sử đơn hàng và báo cáo.";
                $messageType = 'success';
            } else {
                $message = 'Lỗi khi xóa mềm sản phẩm: ' . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // 4. KHÔI PHỤC SẢN PHẨM (RESTORE)
    elseif ($action === 'restore') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE products SET is_deleted = 0, deleted_at = NULL, deleted_by = NULL WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $message = "Đã khôi phục sản phẩm thành công! Sản phẩm đã quay lại danh sách hoạt động.";
                $messageType = 'success';
            } else {
                $message = 'Lỗi khi khôi phục sản phẩm: ' . $conn->error;
                $messageType = 'error';
            }
            $stmt->close();
        }
    }

    // 5. XÓA CỨNG VĨNH VIỄN (FORCE DELETE) - CHỈ CHO PHÉP KHI KHÔNG CÓ LỊCH SỬ ĐƠN HÀNG
    elseif ($action === 'force_delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Kiểm tra xem sản phẩm đã phát sinh giao dịch đơn hàng chưa
            $chkOrder = $conn->prepare("SELECT COUNT(*) AS order_count FROM order_items WHERE product_id = ?");
            $chkOrder->bind_param("i", $id);
            $chkOrder->execute();
            $orderCount = (int)($chkOrder->get_result()->fetch_assoc()['order_count'] ?? 0);
            $chkOrder->close();

            if ($orderCount > 0) {
                $message = "KHÔNG THỂ XÓA VĨNH VIỄN! Sản phẩm này đã phát sinh {$orderCount} lượt giao dịch trong đơn hàng. Để bảo đảm tính toàn vẹn dữ liệu kế toán và báo cáo, sản phẩm chỉ có thể lưu trong Thùng rác hoặc chuyển sang trạng thái Ngừng kinh doanh.";
                $messageType = 'error';
            } else {
                // Sản phẩm chưa từng có đơn hàng -> Cho phép xóa cứng
                $conn->begin_transaction();
                try {
                    // Dọn dẹp các dữ liệu phụ trợ liên kết
                    $conn->query("DELETE FROM cart_items WHERE product_id = {$id}");
                    $conn->query("DELETE FROM wishlists WHERE product_id = {$id}");
                    $conn->query("DELETE FROM reviews WHERE product_id = {$id}");

                    $delStmt = $conn->prepare("DELETE FROM products WHERE id = ? AND is_deleted = 1");
                    $delStmt->bind_param("i", $id);
                    $delStmt->execute();
                    $delStmt->close();

                    $conn->commit();
                    $message = "Đã xóa vĩnh viễn sản phẩm khỏi cơ sở dữ liệu!";
                    $messageType = 'success';
                } catch (Throwable $e) {
                    $conn->rollback();
                    $message = "Lỗi khi xóa vĩnh viễn: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
        }
    }
}

// Chế độ xem: 'active' (đang hoạt động) hoặc 'trash' (thùng rác)
$currentView = ($_GET['view'] ?? 'active') === 'trash' ? 'trash' : 'active';

// Lấy số lượng cho các tab
$cntActiveRes = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE is_deleted = 0");
$countActive = (int)($cntActiveRes ? $cntActiveRes->fetch_assoc()['cnt'] : 0);

$cntTrashRes = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE is_deleted = 1");
$countTrash = (int)($cntTrashRes ? $cntTrashRes->fetch_assoc()['cnt'] : 0);

// Lấy danh sách danh mục (cho dropdown chọn)
$categories = [];
$catRes = $conn->query("SELECT id, name FROM categories ORDER BY id ASC");
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Lấy danh sách sản phẩm từ SQL VIEW theo chế độ xem
$products = [];
if ($currentView === 'trash') {
    $prodRes = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 1 ORDER BY deleted_at DESC, id DESC");
} else {
    $prodRes = $conn->query("SELECT * FROM view_product_details WHERE is_deleted = 0 ORDER BY id DESC");
}
if ($prodRes) {
    while ($row = $prodRes->fetch_assoc()) {
        $products[] = $row;
    }
}
if ($prodRes) {
    while ($row = $prodRes->fetch_assoc()) {
        $products[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quản lý sản phẩm - ÉCRIN Jewelry</title>
    <link rel="stylesheet" href="<?php echo $escape($appBaseUrl . '/admin/style.css'); ?>">
    <style>
        .prod-panel { margin: 20px 0; padding: 22px; border: 1px solid var(--line); border-radius: 12px; background: #fff; box-shadow: var(--shadow); }
        .prod-toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .toolbar-left { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
        .toolbar-left input, .toolbar-left select { padding: 9px 14px; border: 1px solid #cbd4df; border-radius: 8px; font: inherit; outline: none; }
        .toolbar-left input:focus, .toolbar-left select:focus { border-color: var(--navy); }
        .toolbar-left input { min-width: 240px; }

        .btn-add { padding: 10px 18px; border: 0; border-radius: 8px; background: var(--navy); color: #fff; font: 600 14px inherit; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-add:hover { background: #1a3c68; }

        .prod-table { width: 100%; border-collapse: collapse; }
        .prod-table th, .prod-table td { padding: 13px 15px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: middle; }
        .prod-table th { background: #fafbfc; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .prod-table tr:hover { background: #fafbfc; }

        .thumb-img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid var(--line); background: #f7f9fb; display: block; }
        .prod-name-cell { min-width: 250px; }
        .prod-name { font-family: 'Roboto', sans-serif; font-size: 14.5px; font-weight: 600; color: var(--navy); line-height: 1.35; }
        .prod-desc-preview { font-family: 'Roboto', sans-serif; font-size: 12px; color: #64748b; line-height: 1.45; margin-top: 4px; max-width: 380px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
        .cat-badge { display: inline-block; padding: 4px 11px; background: #eef2f6; color: var(--navy); border-radius: 20px; font-size: 12px; font-weight: 600; font-family: 'Roboto', sans-serif; letter-spacing: 0.02em; }
        .price-text { font-family: 'Roboto', sans-serif; font-size: 14px; font-weight: 700; color: var(--navy); }
        .stock-tag { font-weight: 600; font-size: 13px; font-family: 'Roboto', sans-serif; }
        .stock-ok   { color: #027a48; }
        .stock-low  { color: #b54708; }
        .stock-out  { color: #b42318; }

        .btn-suggest-desc { background: #f8fafc; border: 1px solid #cbd5e1; color: var(--navy); font-size: 11.5px; font-weight: 600; padding: 3px 10px; border-radius: 6px; cursor: pointer; transition: all .2s; }
        .btn-suggest-desc:hover { background: #eef2f6; border-color: var(--navy); color: #b58c54; }
        .desc-hints { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 4px; }
        .desc-hints small { color: var(--muted); font-size: 11px; }
        .desc-pill { background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 12px; cursor: pointer; transition: all .15s; }
        .desc-pill:hover { background: #e2e8f0; color: var(--navy); border-color: #cbd5e1; }

        .btn-sm { padding: 6px 14px; border: 0; border-radius: 6px; font: 600 13px inherit; cursor: pointer; transition: .2s; }
        .btn-edit { background: #e0f2fe; color: #026aa2; }
        .btn-edit:hover { background: #b9e6fe; }
        .btn-delete { background: #fee4e2; color: #b42318; margin-left: 6px; }
        .btn-delete:hover { background: #fecdca; }
        .btn-restore { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .btn-restore:hover { background: #d1fadf; }
        .btn-force { background: #7f1d1d; color: #fff; margin-left: 6px; }
        .btn-force:hover { background: #991b1b; }

        /* Tabs chuyển đổi Active / Trash */
        .admin-nav-tabs { display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; }
        .admin-nav-tab { padding: 10px 20px; border-radius: 8px 8px 0 0; text-decoration: none; font-weight: 600; font-size: 14px; color: #64748b; background: transparent; transition: all .2s; display: inline-flex; align-items: center; gap: 8px; margin-bottom: -2px; }
        .admin-nav-tab:hover { color: var(--navy); background: #f8fafc; }
        .admin-nav-tab.active { color: var(--navy); border-bottom: 3px solid var(--navy); background: #fff; }
        .tab-count { font-size: 11px; padding: 2px 8px; border-radius: 12px; background: #e2e8f0; color: #475467; font-weight: 700; }
        .admin-nav-tab.active .tab-count { background: var(--navy); color: #fff; }

        /* Badge trạng thái sản phẩm */
        .status-badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 700; letter-spacing: 0.02em; }
        .status-active   { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .status-inactive { background: #fffaeb; color: #b54708; border: 1px solid #fedf89; }
        .status-hidden   { background: #f2f4f7; color: #475467; border: 1px solid #eaecf0; }
        .status-deleted  { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #ecfdf3; color: #027a48; border: 1px solid #a6f4c5; }
        .alert-error { background: #fef3f2; color: #b42318; border: 1px solid #fecdca; }

        /* Modal popup */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 39, 71, .55); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .modal.is-open { display: flex; }
        .modal-box { width: min(540px, 94%); max-height: 90vh; overflow-y: auto; background: #fff; border-radius: 14px; padding: 26px; box-shadow: 0 16px 40px rgba(0,0,0,.2); }
        .modal-box h3 { margin: 0 0 18px; color: var(--navy); font-size: 1.25rem; border-bottom: 1px solid var(--line); padding-bottom: 10px; }
        .form-grid { display: grid; gap: 14px; }
        .form-field { display: grid; gap: 6px; }
        .form-field label { font-weight: 600; font-size: 13px; color: var(--navy); }
        .form-field input, .form-field select, .form-field textarea { padding: 9px 12px; border: 1px solid #cbd4df; border-radius: 8px; font: inherit; outline: none; }
        .form-field input:focus, .form-field select:focus, .form-field textarea:focus { border-color: var(--navy); }
        .form-field textarea { resize: vertical; min-height: 70px; line-height: 1.5; }
        .form-field small { color: var(--muted); font-size: 11px; }

        /* Khung nhập thêm hàng */
        .stock-adjust-box { background: #f4f7fb; border: 1px dashed #b8c7db; border-radius: 8px; padding: 12px; margin-top: 4px; }
        .stock-adjust-box strong { color: var(--navy); font-size: 13px; }
        .stock-calc-preview { font-size: 12px; color: #026aa2; margin-top: 4px; font-weight: 600; }

        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 14px; border-top: 1px solid var(--line); }
        .btn-cancel { padding: 9px 16px; border: 1px solid #cbd4df; border-radius: 8px; background: #fff; cursor: pointer; font: inherit; }
        .current-img-preview { width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 6px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/../include/admin_sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="breadcrumb">Trang chủ <span>/</span> <strong>Quản lý sản phẩm</strong></div>
            <div class="admin-profile">
                <div class="avatar"><?php echo $isManager ? 'QL' : 'NV'; ?></div>
                <div class="admin-info"><strong><?php echo $escape($adminUsername); ?></strong><small><?php echo $escape($roleLabel); ?></small></div>
            </div>
        </header>

        <section class="page-content">
            <div class="welcome-row">
                <div>
                    <h1>Quản lý sản phẩm</h1>
                    <p class="page-subtitle">Hệ thống quản lý sản phẩm thương mại điện tử ÉCRIN Jewelry.</p>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo $escape($message); ?></div>
            <?php endif; ?>

            <!-- TABS CHUYỂN ĐỔI: SẢN PHẨM HOẠT ĐỘNG / THÙNG RÁC -->
            <div class="admin-nav-tabs">
                <a href="products.php?view=active" class="admin-nav-tab <?php echo $currentView === 'active' ? 'active' : ''; ?>">
                    <span>🛍️ Sản phẩm đang hoạt động</span>
                    <span class="tab-count"><?php echo $countActive; ?></span>
                </a>
                <a href="products.php?view=trash" class="admin-nav-tab <?php echo $currentView === 'trash' ? 'active' : ''; ?>">
                    <span>🗑️ Thùng rác (Đã xóa mềm)</span>
                    <span class="tab-count" style="<?php echo $countTrash > 0 ? 'background: #fee2e2; color: #dc2626;' : ''; ?>"><?php echo $countTrash; ?></span>
                </a>
            </div>

            <section class="prod-panel">
                <!-- Thanh công cụ tìm kiếm và lọc -->
                <div class="prod-toolbar">
                    <div class="toolbar-left">
                        <input type="search" id="search-input" placeholder="<?php echo $currentView === 'trash' ? 'Tìm trong thùng rác...' : 'Tìm sản phẩm...'; ?>">
                        <select id="category-filter">
                            <option value="">Tất cả danh mục</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"><?php echo $escape($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($currentView === 'active'): ?>
                            <select id="status-filter">
                                <option value="">Tất cả trạng thái</option>
                                <option value="active">🟢 Đang kinh doanh</option>
                                <option value="inactive">🟠 Ngừng kinh doanh</option>
                                <option value="hidden">⚪ Bị ẩn</option>
                                <option value="out_of_stock">🔴 Hết hàng (Tồn = 0)</option>
                            </select>
                        <?php endif; ?>
                    </div>
                    <?php if ($currentView === 'active'): ?>
                        <button type="button" class="btn-add" onclick="openAddModal()">+ Thêm sản phẩm</button>
                    <?php else: ?>
                        <span style="font-size: 13px; color: #64748b; font-weight: 500;">💡 Sản phẩm đã xóa mềm vẫn bảo toàn lịch sử đơn hàng &amp; báo cáo.</span>
                    <?php endif; ?>
                </div>

                <!-- Bảng danh sách sản phẩm -->
                <div style="overflow-x:auto">
                    <table class="prod-table" id="products-table">
                        <thead>
                            <tr>
                                <th style="width: 70px;">Ảnh</th>
                                <th>Tên sản phẩm</th>
                                <th>Danh mục</th>
                                <th>Giá bán</th>
                                <th>Trạng thái &amp; Kho</th>
                                <?php if ($currentView === 'trash'): ?>
                                    <th>Thông tin xóa</th>
                                <?php endif; ?>
                                <th style="width: <?php echo $currentView === 'trash' ? '200px' : '160px'; ?>; text-align: center;">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr id="empty-row"><td colspan="<?php echo $currentView === 'trash' ? '7' : '6'; ?>" style="text-align:center; color: var(--muted); padding: 30px;">
                                    <?php echo $currentView === 'trash' ? 'Thùng rác hiện đang trống. Chưa có sản phẩm nào bị xóa mềm.' : 'Chưa có sản phẩm nào.'; ?>
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <?php
                                        $stockNum = (int)$p['stock'];
                                        if ($stockNum > 10) {
                                            $stockText = 'Còn hàng (' . $stockNum . ')';
                                            $stockClass = 'stock-ok';
                                        } elseif ($stockNum > 0) {
                                            $stockText = 'Sắp hết (' . $stockNum . ')';
                                            $stockClass = 'stock-low';
                                        } else {
                                            $stockText = 'Hết hàng (0)';
                                            $stockClass = 'stock-out';
                                        }

                                        $prodStatus = $p['status'] ?? 'active';
                                        if ($prodStatus === 'inactive') {
                                            $statusText = 'Ngừng kinh doanh';
                                            $statusClass = 'status-inactive';
                                        } elseif ($prodStatus === 'hidden') {
                                            $statusText = 'Bị ẩn';
                                            $statusClass = 'status-hidden';
                                        } else {
                                            $statusText = 'Đang kinh doanh';
                                            $statusClass = 'status-active';
                                        }

                                        $imgSrc = (str_starts_with($p['image'], 'http://') || str_starts_with($p['image'], 'https://'))
                                            ? $p['image']
                                            : $appBaseUrl . '/' . ltrim($p['image'], '/');
                                    ?>
                                    <tr class="product-row"
                                        data-name="<?php echo strtolower($escape($p['name'])); ?>"
                                        data-cat-id="<?php echo (int)$p['category_id']; ?>"
                                        data-status="<?php echo $escape($prodStatus); ?>"
                                        data-stock="<?php echo $stockNum; ?>">
                                        <td>
                                            <img src="<?php echo $escape($imgSrc); ?>" alt="" class="thumb-img" onerror="this.src='<?php echo $escape($appBaseUrl . '/assets/banner/logo.png'); ?>'">
                                        </td>
                                        <td class="prod-name-cell">
                                            <div class="prod-name"><?php echo $escape($p['name']); ?></div>
                                            <div style="display: flex; gap: 4px; margin-top: 4px; flex-wrap: wrap;">
                                                <?php if (!empty($p['is_featured'])): ?>
                                                    <span style="display: inline-block; font-size: 11px; padding: 2px 7px; border-radius: 4px; background: #fef08a; color: #854d0e; font-weight: 600;">⭐ Nổi bật</span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['is_suggested'])): ?>
                                                    <span style="display: inline-block; font-size: 11px; padding: 2px 7px; border-radius: 4px; background: #e0e7ff; color: #3730a3; font-weight: 600;">💡 Gợi ý</span>
                                                <?php endif; ?>
                                                <?php if (!empty($p['is_new'])): ?>
                                                    <span style="display: inline-block; font-size: 11px; padding: 2px 7px; border-radius: 4px; background: #dcfce7; color: #166534; font-weight: 600;">🆕 Mới</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (!empty($p['description'])): ?>
                                                <div class="prod-desc-preview" title="<?php echo $escape($p['description']); ?>"><?php echo $escape($p['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="cat-badge"><?php echo $escape($p['category_name']); ?></span></td>
                                        <td>
                                            <strong class="price-text"><?php echo number_format((float)$p['price_sale'], 0, ',', '.'); ?>đ</strong>
                                            <?php if (!empty($p['price_original']) && (float)$p['price_original'] > (float)$p['price_sale']): ?>
                                                <div style="font-size: 11px; color: #94a3b8; text-decoration: line-through;">
                                                    <?php echo number_format((float)$p['price_original'], 0, ',', '.'); ?>đ
                                                </div>
                                                <span style="display: inline-block; font-size: 11px; padding: 1px 5px; border-radius: 4px; background: #fee2e2; color: #dc2626; font-weight: 700;">
                                                    <?php echo $escape($p['discount'] ?? ''); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                                <?php if ((int)$p['is_deleted'] === 1): ?>
                                                    <span class="status-badge status-deleted">Đã xóa mềm</span>
                                                <?php else: ?>
                                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $escape($statusText); ?></span>
                                                <?php endif; ?>
                                                <span class="stock-tag <?php echo $stockClass; ?>"><?php echo $escape($stockText); ?></span>
                                            </div>
                                            <?php if ((int)($p['sold_count'] ?? 0) > 0): ?>
                                                <div style="font-size: 0.8rem; color: #e67e22; font-weight: 500; margin-top: 4px;">Đã bán: <?php echo (int)$p['sold_count']; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($currentView === 'trash'): ?>
                                            <td>
                                                <div style="font-size: 12px; color: #64748b;">
                                                    <div><strong>Ngày xóa:</strong> <?php echo !empty($p['deleted_at']) ? date('d/m/Y H:i', strtotime($p['deleted_at'])) : 'N/A'; ?></div>
                                                    <div><strong>Bởi:</strong> <?php echo $escape($p['deleted_by'] ?? 'Admin'); ?></div>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                        <td style="text-align: center;">
                                            <?php if ($currentView === 'trash'): ?>
                                                <!-- THAO TÁC TRONG THÙNG RÁC: KHÔI PHỤC HOẶC XÓA VĨNH VIỄN -->
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Khôi phục sản phẩm \'<?php echo $escape($p['name']); ?>\' về danh sách hoạt động?');">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-restore" title="Khôi phục sản phẩm">↺ Khôi phục</button>
                                                </form>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('CẢNH BÁO NGUY HIỂM!\n\nBạn có chắc chắn muốn XÓA VĨNH VIỄN sản phẩm \'<?php echo $escape($p['name']); ?>\' khỏi hệ thống không?\n\nLưu ý: Chỉ cho phép xóa khi sản phẩm chưa từng phát sinh đơn hàng.');">
                                                    <input type="hidden" name="action" value="force_delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-force" title="Xóa cứng vĩnh viễn (chỉ khi chưa có đơn hàng)">✕ Xóa vĩnh viễn</button>
                                                </form>
                                            <?php else: ?>
                                                <!-- THAO TÁC ĐANG HOẠT ĐỘNG: SỬA HOẶC XÓA MỀM -->
                                                <button type="button" class="btn-sm btn-edit"
                                                    onclick='openEditModal(<?php echo json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, "<?php echo $escape($imgSrc); ?>")'>
                                                    Sửa
                                                </button>
                                                <form method="post" style="display:inline;" onsubmit="return confirm('Chuyển sản phẩm \'<?php echo $escape($p['name']); ?>\' vào Thùng rác (xóa mềm)?\n\nSản phẩm sẽ ẩn khỏi Client nhưng vẫn bảo toàn lịch sử đơn hàng và báo cáo.');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                                    <button type="submit" class="btn-sm btn-delete" title="Xóa mềm vào thùng rác">Xóa</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr id="no-search-results" style="display: none;">
                                <td colspan="<?php echo $currentView === 'trash' ? '7' : '6'; ?>" style="text-align:center; color: var(--muted); padding: 30px;">Không tìm thấy sản phẩm phù hợp.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</div>

<!-- MODAL THÊM SẢN PHẨM -->
<div class="modal" id="addModal">
    <div class="modal-box">
        <h3>+ Thêm sản phẩm mới</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="form-grid">
                <div class="form-field">
                    <label>Tên sản phẩm (*):</label>
                    <input type="text" name="name" placeholder="Ví dụ: Bông tai ÉCRIN ánh vàng" required>
                </div>

                <div class="form-field">
                    <label>Danh mục sản phẩm (*):</label>
                    <select name="category_id" id="add-category" onchange="onAddCategoryChange(this.value)" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo $escape($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label>Giá bán (VNĐ) (*):</label>
                    <input type="number" name="price" placeholder="Ví dụ: 450000" min="0" step="1000" required>
                </div>

                <div class="form-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <label for="add-description">Mô tả chi tiết sản phẩm:</label>
                        <button type="button" class="btn-suggest-desc" onclick="generateSampleDescription('add')">✨ Điền mô tả mẫu cuốn hút</button>
                    </div>
                    <textarea name="description" id="add-description" rows="4" placeholder="Nhập mô tả sản phẩm...">Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.</textarea>
                    <div class="desc-hints">
                        <small>💡 Gợi ý theo danh mục:</small>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 2)">Bông tai</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 3)">Dây chuyền</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 4)">Nhẫn</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 5)">Vòng tay</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('add', 6)">Set quà tặng</button>
                    </div>
                </div>

                <div class="form-field">
                    <label>Số lượng tồn kho ban đầu (*):</label>
                    <input type="number" name="stock" value="10" min="0" required>
                </div>

                <div class="form-field">
                    <label>Trạng thái kinh doanh (*):</label>
                    <select name="status" id="add-status" required>
                        <option value="active" selected>🟢 Đang kinh doanh (Hiển thị và cho phép đặt mua)</option>
                        <option value="inactive">🟠 Ngừng kinh doanh (Không cho đặt mua, giữ lịch sử)</option>
                        <option value="hidden">⚪ Bị ẩn (Ẩn khỏi toàn bộ giao diện Client)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Phân loại hiển thị trang chủ:</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 4px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_featured" value="1">
                            <span>⭐ Sản phẩm nổi bật</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_suggested" value="1">
                            <span>💡 Gợi ý cho bạn</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_new" value="1" checked>
                            <span>🆕 Sản phẩm mới</span>
                        </label>
                    </div>
                </div>

                <div class="form-field">
                    <label>Ảnh sản phẩm:</label>
                    <input type="file" name="image_file" accept="image/*">
                    <small>Hoặc nhập link ảnh trực tiếp:</small>
                    <input type="text" name="image_url" placeholder="assets/bôngtai/bong1.jpg hoặc link URL">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Hủy</button>
                <button type="submit" class="btn-add">Thêm sản phẩm</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SỬA SẢN PHẨM -->
<div class="modal" id="editModal">
    <div class="modal-box">
        <h3>Chỉnh sửa sản phẩm</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit-id">

            <div class="form-grid">
                <div class="form-field">
                    <label>Tên sản phẩm (*):</label>
                    <input type="text" name="name" id="edit-name" required>
                </div>

                <div class="form-field">
                    <label>Danh mục (*):</label>
                    <select name="category_id" id="edit-category" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"><?php echo $escape($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label>Giá bán hiện tại (VNĐ) (*):</label>
                    <input type="number" name="price" id="edit-price" min="0" step="1000" required>
                    <input type="hidden" name="price_original" id="edit-price-original" value="0">
                    <div style="margin-top: 8px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
                            <label style="font-size: 13px; font-weight: 600; color: #334155;">Áp dụng % giảm giá nhanh:</label>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(5)">5%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(10)">10%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(15)">15%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(20)">20%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(30)">30%</button>
                                <button type="button" class="btn-sm" style="background: #e2e8f0; color: #334155; padding: 2px 8px;" onclick="applyDiscountPercent(50)">50%</button>
                                <button type="button" class="btn-sm" style="background: #fee2e2; color: #b91c1c; padding: 2px 8px;" onclick="removeDiscount()">Hủy giảm giá</button>
                            </div>
                        </div>
                        <div id="price-calc-hint" style="font-size: 12px; color: #0284c7; margin-top: 6px; font-weight: 500;"></div>
                    </div>
                </div>

                <div class="form-field">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                        <label for="edit-description">Mô tả chi tiết sản phẩm:</label>
                        <button type="button" class="btn-suggest-desc" onclick="generateSampleDescription('edit')">✨ Gợi ý mô tả cuốn hút</button>
                    </div>
                    <textarea name="description" id="edit-description" rows="4" placeholder="Nhập mô tả sản phẩm, chất liệu đá/bạc..."></textarea>
                    <div class="desc-hints">
                        <small>💡 Gợi ý theo danh mục:</small>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 2)">Bông tai</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 3)">Dây chuyền</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 4)">Nhẫn</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 5)">Vòng tay</button>
                        <button type="button" class="desc-pill" onclick="fillCategoryDesc('edit', 6)">Set quà tặng</button>
                    </div>
                </div>

                <!-- KHU VỰC QUẢN LÝ SỐ LƯỢNG / NHẬP THÊM HÀNG -->
                <div class="form-field">
                    <label>Số lượng tồn kho:</label>
                    <input type="number" name="stock" id="edit-stock" min="0" required>
                    
                    <div class="stock-adjust-box">
                        <strong>📦 Nhập thêm hàng về kho:</strong>
                        <div style="display: flex; gap: 8px; margin-top: 6px; align-items: center;">
                            <input type="number" id="add-stock-input" placeholder="Số lượng nhập thêm (VD: 5)" min="0" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd4df; border-radius: 6px;">
                            <button type="button" class="btn-sm btn-edit" style="white-space: nowrap;" onclick="applyStockAdd()">+ Cộng kho</button>
                        </div>
                        <div class="stock-calc-preview" id="stock-calc-hint"></div>
                    </div>
                </div>

                <div class="form-field">
                    <label>Trạng thái kinh doanh (*):</label>
                    <select name="status" id="edit-status" required>
                        <option value="active">🟢 Đang kinh doanh (Hiển thị và cho phép đặt mua)</option>
                        <option value="inactive">🟠 Ngừng kinh doanh (Không cho đặt mua, giữ lịch sử)</option>
                        <option value="hidden">⚪ Bị ẩn (Ẩn khỏi toàn bộ giao diện Client)</option>
                    </select>
                </div>

                <div class="form-field">
                    <label>Phân loại hiển thị trang chủ:</label>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 4px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_featured" id="edit-is-featured" value="1">
                            <span>⭐ Sản phẩm nổi bật</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_suggested" id="edit-is-suggested" value="1">
                            <span>💡 Gợi ý cho bạn</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">
                            <input type="checkbox" name="is_new" id="edit-is-new" value="1">
                            <span>🆕 Sản phẩm mới</span>
                        </label>
                    </div>
                </div>

                <div class="form-field">
                    <label>Ảnh sản phẩm hiện tại:</label>
                    <div><img id="edit-img-preview" src="" alt="" class="current-img-preview"></div>
                    <label>Đổi ảnh mới (nếu muốn):</label>
                    <input type="file" name="image_file" accept="image/*">
                    <input type="text" name="image_url_new" id="edit-img-url" placeholder="Hoặc nhập đường dẫn ảnh mới..." style="margin-top: 6px;">
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
                <button type="submit" class="btn-add">Lưu thay đổi</button>
            </div>
        </form>
    </div>
</div>

<script>
// Mẫu mô tả cuốn hút theo từng danh mục
const descTemplates = {
    2: 'Lấy cảm hứng từ những đóa hoa tuyết đầu mùa và giọt sương mai thuần khiết, đôi bông tai được chế tác thủ công từ Bạc Ý 925 mạ bạch kim cao cấp, đính kết đá Zirconia đa diện phát sáng rực rỡ dưới mọi góc nhìn. Thiết kế thanh thoát tôn vinh trọn vẹn nét đài các, yêu kiều và là phụ kiện hoàn mỹ giúp nàng tự tin tỏa sáng rạng ngời trong các buổi dạ hội sang trọng lẫn phong cách thường nhật.',
    3: 'Mặt dây chuyền được chạm khắc tỉ mỉ với đường nét thanh mảnh, uốn lượn mềm mại ôm trọn viên đá chủ lấp lánh như viên ngọc quý giữa lòng đại dương. Sợi dây bạc Ý 925 mạ rhodium cao cấp nhẹ nhàng tôn vinh xương quai xanh quyến rũ, biểu trưng cho tình yêu vĩnh cửu và phong thái kiêu sa, đài các khó phai.',
    4: 'Thiết kế nhẫn solitaire kinh điển kết hợp kỹ thuật nạm đá tinh xảo bậc nhất, nâng niu viên đá chủ Zirconia giác cắt Brilliant Cut phản chiếu ánh hào quang lộng lẫy tựa kim cương tự nhiên. Sự lựa chọn hoàn mỹ biểu trưng cho tình yêu đích thực và khẳng định phong cách vương giả, độc bản của quý cô hiện đại.',
    5: 'Chiếc vòng tay ôm khít cổ tay yêu kiều, chế tác công phu từ chất liệu Bạc Ý 925 đúc nguyên khối dày dặn và các hạt charm đính đá vi mô sáng trong vắt. Thiết kế thanh lịch, chống oxy hóa bền lâu, mang lại nguồn năng lượng an lành, duyên dáng và phong thái thượng lưu trong từng cử chỉ tay.',
    6: 'Tuyệt phẩm hộp quà tặng ÉCRIN phiên bản giới hạn sang trọng gồm các món trang sức chế tác tinh tuyển đồng điệu, đi kèm thiệp chúc mừng cao cấp. Món quà hoàn hảo trao trọn tình cảm chân thành, biểu tượng cho hạnh phúc viên mãn và những cột mốc kỷ niệm đáng nhớ nhất.'
};

function fillCategoryDesc(modalType, catId) {
    const textareaId = modalType === 'add' ? 'add-description' : 'edit-description';
    const textarea = document.getElementById(textareaId);
    if (textarea && descTemplates[catId]) {
        textarea.value = descTemplates[catId];
        textarea.focus();
    }
}

function generateSampleDescription(modalType) {
    const catSelectId = modalType === 'add' ? 'add-category' : 'edit-category';
    const catId = parseInt(document.getElementById(catSelectId).value, 10) || 2;
    fillCategoryDesc(modalType, catId);
}

function onAddCategoryChange(catId) {
    const numId = parseInt(catId, 10);
    const textarea = document.getElementById('add-description');
    if (textarea && (!textarea.value.trim() || Object.values(descTemplates).includes(textarea.value.trim()))) {
        if (descTemplates[numId]) {
            textarea.value = descTemplates[numId];
        }
    }
}

// Modal Thêm sản phẩm
function openAddModal() {
    document.getElementById('addModal').classList.add('is-open');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('is-open');
}

// Modal Sửa sản phẩm
let originalStock = 0;
let basePriceForDiscount = 0;

function openEditModal(prod, imgSrc) {
    document.getElementById('edit-id').value          = prod.id;
    document.getElementById('edit-name').value        = prod.name;
    document.getElementById('edit-category').value    = prod.category_id;
    document.getElementById('edit-price').value       = prod.price_sale;
    document.getElementById('edit-stock').value       = prod.stock;
    document.getElementById('edit-status').value      = prod.status || 'active';
    document.getElementById('edit-description').value = prod.description || descTemplates[prod.category_id] || '';
    document.getElementById('edit-is-featured').checked  = parseInt(prod.is_featured, 10) === 1;
    document.getElementById('edit-is-suggested').checked = parseInt(prod.is_suggested, 10) === 1;
    document.getElementById('edit-is-new').checked       = parseInt(prod.is_new, 10) === 1;
    originalStock = parseInt(prod.stock, 10) || 0;

    // Giá gốc tham chiếu khi tính giảm giá
    const origPrice = parseFloat(prod.price_original) || 0;
    const salePrice = parseFloat(prod.price_sale) || 0;
    basePriceForDiscount = origPrice > salePrice ? origPrice : salePrice;

    document.getElementById('edit-price-original').value = origPrice > salePrice ? origPrice : 0;
    updatePriceCalcHint();

    // Reset ô nhập thêm hàng
    const addStockInput = document.getElementById('add-stock-input');
    addStockInput.value = '';
    document.getElementById('stock-calc-hint').textContent = 'Hiện tại đang có ' + originalStock + ' sản phẩm trong kho.';

    document.getElementById('edit-img-preview').src = imgSrc;
    document.getElementById('edit-img-url').value   = '';
    document.getElementById('editModal').classList.add('is-open');
}

function updatePriceCalcHint() {
    const hint = document.getElementById('price-calc-hint');
    const orig = parseFloat(document.getElementById('edit-price-original').value) || 0;
    const current = parseFloat(document.getElementById('edit-price').value) || 0;
    if (orig > current && current > 0) {
        const pct = Math.round((1 - (current / orig)) * 100);
        hint.innerHTML = '🏷️ Giá gốc: <strong>' + orig.toLocaleString('vi-VN') + 'đ</strong> ➡ Giá sau giảm: <strong>' + current.toLocaleString('vi-VN') + 'đ</strong> (Giảm <strong>-' + pct + '%</strong>)';
    } else {
        hint.innerHTML = '💡 Sản phẩm đang bán với giá thường (không giảm giá).';
    }
}

function applyDiscountPercent(pct) {
    if (basePriceForDiscount <= 0) return;
    const discountedPrice = Math.round(basePriceForDiscount * (1 - pct / 100) / 1000) * 1000;
    document.getElementById('edit-price').value = discountedPrice;
    document.getElementById('edit-price-original').value = basePriceForDiscount;
    updatePriceCalcHint();
}

function removeDiscount() {
    document.getElementById('edit-price').value = basePriceForDiscount;
    document.getElementById('edit-price-original').value = 0;
    updatePriceCalcHint();
}

document.getElementById('edit-price')?.addEventListener('input', function() {
    const current = parseFloat(this.value) || 0;
    const orig = parseFloat(document.getElementById('edit-price-original').value) || 0;
    if (current >= orig) {
        document.getElementById('edit-price-original').value = 0;
    }
    updatePriceCalcHint();
});

function closeEditModal() {
    document.getElementById('editModal').classList.remove('is-open');
}

// Tính năng tự động cộng kho khi nhập thêm hàng
document.getElementById('add-stock-input').addEventListener('input', function() {
    const addVal = parseInt(this.value, 10);
    const hint = document.getElementById('stock-calc-hint');
    if (!isNaN(addVal) && addVal > 0) {
        const newTotal = originalStock + addVal;
        hint.textContent = '➡ Kho hiện tại ' + originalStock + ' + nhập thêm ' + addVal + ' = ' + newTotal + ' sản phẩm.';
    } else {
        hint.textContent = 'Hiện tại đang có ' + originalStock + ' sản phẩm trong kho.';
    }
});

function applyStockAdd() {
    const addVal = parseInt(document.getElementById('add-stock-input').value, 10);
    if (!isNaN(addVal) && addVal > 0) {
        const newTotal = originalStock + addVal;
        document.getElementById('edit-stock').value = newTotal;
        originalStock = newTotal;
        document.getElementById('add-stock-input').value = '';
        document.getElementById('stock-calc-hint').textContent = '✔ Đã cập nhật tồn kho thành: ' + newTotal;
    }
}

// Đóng modal khi bấm ra ngoài
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('is-open');
    }
});

// TÍNH NĂNG TÌM KIẾM & LỌC DANH MỤC / TRẠNG THÁI REALTIME
const searchInput = document.getElementById('search-input');
const categoryFilter = document.getElementById('category-filter');
const statusFilter = document.getElementById('status-filter');
const rows = document.querySelectorAll('.product-row');
const noResults = document.getElementById('no-search-results');

function filterProducts() {
    const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const selectedCat = categoryFilter ? categoryFilter.value : '';
    const selectedStatus = statusFilter ? statusFilter.value : '';
    let visibleCount = 0;

    rows.forEach(row => {
        const name = row.getAttribute('data-name') || '';
        const catId = row.getAttribute('data-cat-id') || '';
        const status = row.getAttribute('data-status') || '';
        const stock = parseInt(row.getAttribute('data-stock'), 10) || 0;

        const matchName = query === '' || name.includes(query);
        const matchCat = selectedCat === '' || catId === selectedCat;

        let matchStatus = true;
        if (selectedStatus === 'out_of_stock') {
            matchStatus = stock <= 0;
        } else if (selectedStatus !== '') {
            matchStatus = status === selectedStatus;
        }

        if (matchName && matchCat && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        noResults.style.display = visibleCount === 0 && rows.length > 0 ? '' : 'none';
    }
}

if (searchInput) searchInput.addEventListener('input', filterProducts);
if (categoryFilter) categoryFilter.addEventListener('change', filterProducts);
if (statusFilter) statusFilter.addEventListener('change', filterProducts);
</script>
</body>
</html>
