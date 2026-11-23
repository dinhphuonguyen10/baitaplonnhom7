<?php
// Tác dụng: Kiểm tra và duy trì phiên đăng nhập riêng cho khách hàng ngoài giao diện bán hàng, đảm bảo không nhầm lẫn với phiên quản trị
session_start();
header('Content-Type: application/json; charset=utf-8');

// Trang công khai chỉ kiểm tra phiên khách hàng, không trả trạng thái quản trị
if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'customer') {
    echo json_encode([
        "status" => "success",
        "username" => $_SESSION['username'],
        "role" => $_SESSION['role'],
        "user_id" => $_SESSION['user_id']
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
}
?>
