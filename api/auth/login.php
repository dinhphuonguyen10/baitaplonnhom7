<?php
// api/auth/login.php
require_once __DIR__ . '/../../include/session.php';
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$json = file_get_contents('php://input');
$data = json_decode($json);

if (!isset($data->username) || !isset($data->password)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu!'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = trim($data->username);
$password = $data->password;

// Phiên admin và khách hàng được lưu riêng để đăng nhập một bên không xóa phiên bên kia.
// ─── Bước 1: Tìm trong bảng users (khách hàng) ───────────────────────────
$stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    $isMatch = password_verify($password, $user['password']) || $password === $user['password'];

    if ($isMatch) {
        // Tự động nâng cấp mật khẩu plain-text lên hash
        if ($password === $user['password']) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($upStmt) {
                $upStmt->bind_param('si', $newHash, $user['id']);
                $upStmt->execute();
                $upStmt->close();
            }
        }

        session_regenerate_id(true);

        // Phiên khách hàng mua sắm
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = 'customer';

        http_response_code(200);
        echo json_encode([
            'status'   => 'success',
            'message'  => 'Đăng nhập thành công!',
            'role'     => 'customer',
            'username' => $user['username'],
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }

    // Username trùng nhưng sai mật khẩu → trả lỗi luôn, không tìm tiếp admins
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.'], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

// ─── Bước 2: Tìm trong bảng admins (staff / manager) ─────────────────────
$stmt = $conn->prepare("SELECT id, username, password, role FROM admins WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

if ($admin) {
    $isMatch = password_verify($password, $admin['password']) || $password === $admin['password'];

    if ($isMatch) {
        // Tự động nâng cấp mật khẩu plain-text lên hash
        if ($password === $admin['password']) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $upStmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
            if ($upStmt) {
                $upStmt->bind_param('si', $newHash, $admin['id']);
                $upStmt->execute();
                $upStmt->close();
            }
        }

        session_regenerate_id(true);

        // Phiên quản trị viên
        $_SESSION['admin_user_id']  = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_role']     = $admin['role'];

        http_response_code(200);
        echo json_encode([
            'status'   => 'success',
            'message'  => 'Đăng nhập thành công!',
            'role'     => $admin['role'],
            'username' => $admin['username'],
        ], JSON_UNESCAPED_UNICODE);
        $conn->close();
        exit;
    }
}

http_response_code(401);
echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng.'], JSON_UNESCAPED_UNICODE);
$conn->close();
