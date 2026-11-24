<?php
// Tác dụng: Đăng xuất phiên khách hàng ngoài giao diện bán hàng mà không làm gián đoạn phiên quản trị viên đang làm việc
require_once __DIR__ . '/../../include/session.php';

// Hủy thông tin phiên khách hàng
unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(["status" => "success", "message" => "Đã đăng xuất thành công!"]);
?>
