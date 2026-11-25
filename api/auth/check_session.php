<?php
// Tác dụng: Kiểm tra và duy trì phiên đăng nhập riêng cho khách hàng ngoài giao diện bán hàng, đảm bảo không nhầm lẫn với phiên quản trị
require_once __DIR__ . '/../../include/session.php';
header('Content-Type: application/json; charset=utf-8');

// Trang công khai chỉ kiểm tra phiên khách hàng, không trả trạng thái quản trị.
if (($_SESSION['role'] ?? '') === 'customer') {
    require_once __DIR__ . '/../../config/db.php';
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    $user = null;

    if ($userId !== false && $userId > 0) {
        $stmt = $conn->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Không thể kiểm tra phiên đăng nhập."]);
            $conn->close();
            exit;
        }
        $stmt->bind_param('i', $userId);
        if (!$stmt->execute()) {
            $stmt->close();
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Không thể kiểm tra phiên đăng nhập."]);
            $conn->close();
            exit;
        }
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($user) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = (string) $user['username'];
        echo json_encode([
            "status" => "success",
            "username" => $_SESSION['username'],
            "role" => $_SESSION['role'],
            "user_id" => $_SESSION['user_id']
        ]);
    } else {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        echo json_encode(["status" => "error", "message" => "Not logged in"]);
    }
    $conn->close();
} else {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
}
?>
