<?php
// api/catalog/wishlist.php
// Tác dụng: Quản lý danh sách sản phẩm yêu thích (thả tim) của khách hàng
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../../config/db.php';

$userId = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer' ? (int)$_SESSION['user_id'] : 0;

$method = $_SERVER['REQUEST_METHOD'];

// 1. GET: Lấy danh sách ID hoặc danh sách sản phẩm yêu thích
if ($method === 'GET') {
    if ($userId <= 0) {
        echo json_encode([
            'status' => 'guest',
            'is_logged_in' => false,
            'ids' => [],
            'items' => [],
            'count' => 0
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $type = $_GET['type'] ?? 'ids';

    if ($type === 'items') {
        // Lấy chi tiết sản phẩm trong danh sách yêu thích
        $stmt = $conn->prepare("
            SELECT w.id AS wishlist_id, w.created_at AS favorited_at,
                   p.id, p.name, p.category_id, p.price_sale, p.price_original, p.stock, p.image, p.description,
                   c.name AS category_name, c.slug AS category,
                   COALESCE(SUM(oi.quantity), 0) AS sold_count
            FROM wishlists w
            JOIN products p ON w.product_id = p.id
            JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            WHERE w.user_id = ?
            GROUP BY p.id
            ORDER BY w.id DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['stock'] = (int)$row['stock'];
            $row['sold_count'] = (int)$row['sold_count'];
            $row['price_sale_raw'] = (float)$row['price_sale'];
            $row['price_original_raw'] = (float)($row['price_original'] ?? 0);
            $row['price_sale'] = number_format((float)$row['price_sale'], 0, ',', '.');
            $row['price_original'] = $row['price_original_raw'] > 0 ? number_format($row['price_original_raw'], 0, ',', '.') : null;
            $row['is_favorite'] = true;
            $items[] = $row;
            $ids[] = $row['id'];
        }
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'is_logged_in' => true,
            'items' => $items,
            'ids' => $ids,
            'count' => count($ids)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Mặc định lấy danh sách IDs
    $stmt = $conn->prepare("SELECT product_id FROM wishlists WHERE user_id = ? ORDER BY id DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int)$row['product_id'];
    }
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'is_logged_in' => true,
        'ids' => $ids,
        'count' => count($ids)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. POST: Thao tác Thả tim (Toggle), Xóa, hoặc Đồng bộ
if ($method === 'POST') {
    // Đọc input JSON hoặc form post
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action    = trim($data['action'] ?? 'toggle');
    $productId = (int)($data['product_id'] ?? 0);

    // Nếu chưa đăng nhập: yêu cầu đăng nhập để lưu trữ vào tài khoản
    if ($userId <= 0) {
        echo json_encode([
            'status' => 'unauthenticated',
            'message' => 'Vui lòng đăng nhập để lưu sản phẩm yêu thích vào tài khoản!',
            'require_login' => true
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ĐỒNG BỘ từ localStorage khi khách vừa đăng nhập
    if ($action === 'sync') {
        $syncIds = $data['sync_ids'] ?? [];
        if (is_array($syncIds)) {
            $stmt = $conn->prepare("INSERT IGNORE INTO wishlists (user_id, product_id) VALUES (?, ?)");
            foreach ($syncIds as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) {
                    $stmt->bind_param('ii', $userId, $pid);
                    $stmt->execute();
                }
            }
            $stmt->close();
        }

        // Trả về danh sách ID mới nhất
        $cRes = $conn->query("SELECT product_id FROM wishlists WHERE user_id = {$userId}");
        $newIds = [];
        if ($cRes) {
            while ($r = $cRes->fetch_assoc()) {
                $newIds[] = (int)$r['product_id'];
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Đã đồng bộ sản phẩm yêu thích!',
            'ids' => $newIds,
            'count' => count($newIds)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($productId <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Mã sản phẩm không hợp lệ!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Kiểm tra sản phẩm có tồn tại trong CSDL không
    $chkProd = $conn->prepare("SELECT id, name FROM products WHERE id = ? LIMIT 1");
    $chkProd->bind_param('i', $productId);
    $chkProd->execute();
    $prodRow = $chkProd->get_result()->fetch_assoc();
    $chkProd->close();

    if (!$prodRow) {
        echo json_encode(['status' => 'error', 'message' => 'Sản phẩm không tồn tại!'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $prodName = $prodRow['name'];

    // TOGGLE (Bật / Tắt thả tim)
    if ($action === 'toggle') {
        $chkExist = $conn->prepare("SELECT id FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1");
        $chkExist->bind_param('ii', $userId, $productId);
        $chkExist->execute();
        $existRow = $chkExist->get_result()->fetch_assoc();
        $chkExist->close();

        if ($existRow) {
            // Đã tim rồi -> Hủy tim (xóa khỏi wishlists)
            $delStmt = $conn->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
            $delStmt->bind_param('ii', $userId, $productId);
            $delStmt->execute();
            $delStmt->close();
            $favorited = false;
            $msg = "Đã bỏ yêu thích '{$prodName}'";
        } else {
            // Chưa tim -> Thêm vào wishlists
            $insStmt = $conn->prepare("INSERT INTO wishlists (user_id, product_id) VALUES (?, ?)");
            $insStmt->bind_param('ii', $userId, $productId);
            $insStmt->execute();
            $insStmt->close();
            $favorited = true;
            $msg = "❤️ Đã lưu '{$prodName}' vào danh sách yêu thích!";
        }

        // Lấy tổng số lượng yêu thích hiện tại của user
        $cntRes = $conn->query("SELECT COUNT(*) AS total FROM wishlists WHERE user_id = {$userId}");
        $totalCount = (int)($cntRes ? $cntRes->fetch_assoc()['total'] : 0);

        echo json_encode([
            'status' => 'success',
            'favorited' => $favorited,
            'product_id' => $productId,
            'count' => $totalCount,
            'message' => $msg
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // REMOVE (Xóa thẳng khỏi danh sách yêu thích)
    if ($action === 'remove') {
        $delStmt = $conn->prepare("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?");
        $delStmt->bind_param('ii', $userId, $productId);
        $delStmt->execute();
        $delStmt->close();

        $cntRes = $conn->query("SELECT COUNT(*) AS total FROM wishlists WHERE user_id = {$userId}");
        $totalCount = (int)($cntRes ? $cntRes->fetch_assoc()['total'] : 0);

        echo json_encode([
            'status' => 'success',
            'favorited' => false,
            'product_id' => $productId,
            'count' => $totalCount,
            'message' => "Đã bỏ yêu thích '{$prodName}'"
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
