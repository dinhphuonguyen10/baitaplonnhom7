<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/auto_reply_helper.php';

header('Content-Type: application/json; charset=utf-8');

function respondWithError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondWithError('Phương thức yêu cầu không hợp lệ.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
$cart = $data['cart'] ?? null;
$payment_method = strtoupper(trim((string) ($data['payment_method'] ?? 'COD')));

if (!in_array($payment_method, ['COD', 'BANK_QR'], true)) {
    respondWithError('Phương thức thanh toán không hợp lệ.');
}

if (!is_array($cart) || $cart === []) {
    respondWithError('Giỏ hàng trống hoặc không hợp lệ.');
}

$full_name = trim((string) ($customer['name'] ?? ''));
$phone = trim((string) ($customer['phone'] ?? ''));
$email = trim((string) ($customer['email'] ?? ''));
$address = trim((string) ($customer['address'] ?? ''));
$requested_address_id = null;
if (is_array($data) && array_key_exists('address_id', $data) && $data['address_id'] !== null && $data['address_id'] !== '') {
    $requested_address_id = filter_var($data['address_id'], FILTER_VALIDATE_INT);
    if ($requested_address_id === false || $requested_address_id < 1) {
        respondWithError('Địa chỉ nhận hàng không hợp lệ.');
    }
}
$save_as_new_address = filter_var($data['save_as_new_address'] ?? false, FILTER_VALIDATE_BOOLEAN);
$make_default = filter_var($data['make_default'] ?? false, FILTER_VALIDATE_BOOLEAN);
$latitude = null;
$longitude = null;
$coordinates = $data['coordinates'] ?? null;
if ($coordinates !== null) {
    if (!is_array($coordinates)
        || !isset($coordinates['latitude'], $coordinates['longitude'])
        || !is_numeric($coordinates['latitude'])
        || !is_numeric($coordinates['longitude'])) {
        respondWithError('Vị trí bản đồ không hợp lệ.');
    }
    $latitude = (float) $coordinates['latitude'];
    $longitude = (float) $coordinates['longitude'];
    if (!is_finite($latitude) || !is_finite($longitude)
        || $latitude < -90 || $latitude > 90
        || $longitude < -180 || $longitude > 180) {
        respondWithError('Tọa độ bản đồ nằm ngoài phạm vi hợp lệ.');
    }
}

if ($full_name === '' || $phone === '' || $email === '' || $address === '') {
    respondWithError('Vui lòng cung cấp đầy đủ thông tin giao hàng.');
}
if (strlen($full_name) > 150 || strlen($phone) > 20 || strlen($email) > 254 || strlen($address) > 500) {
    respondWithError('Thông tin đặt hàng vượt quá độ dài cho phép.');
}
if (!preg_match('/^0[0-9]{9}$/', $phone)) {
    respondWithError('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.');
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    respondWithError('Vui lòng nhập địa chỉ email hợp lệ.');
}

$quantities = [];
foreach ($cart as $item) {
    if (!is_array($item)) {
        respondWithError('Dữ liệu sản phẩm trong giỏ hàng không hợp lệ.');
    }

    $product_id = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
    if ($product_id === false || $product_id < 1 || $quantity === false || $quantity < 1 || $quantity > 99) {
        respondWithError('Một sản phẩm trong giỏ hàng không hợp lệ. Vui lòng xóa sản phẩm cũ và thêm lại từ danh sách.');
    }

    $quantities[$product_id] = ($quantities[$product_id] ?? 0) + $quantity;
}

try {
    $email_column = $conn->query("SHOW COLUMNS FROM orders LIKE 'email'");
    if (!$email_column) {
        throw new RuntimeException('Không thể kiểm tra cấu trúc bảng đơn hàng.');
    }
    if ($email_column->num_rows === 0 && !$conn->query(
        "ALTER TABLE orders ADD COLUMN email VARCHAR(254) NOT NULL DEFAULT '' AFTER phone"
    )) {
        throw new RuntimeException('Không thể cập nhật cấu trúc lưu email đơn hàng.');
    }

    $conn->begin_transaction();

    $product_stmt = $conn->prepare(
        'SELECT id, name, price_sale, image, stock, status, is_deleted FROM products WHERE id = ? FOR UPDATE'
    );
    $stock_stmt = $conn->prepare(
        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
    );
    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $address_id = null;

    // Session cũ có thể còn giữ ID tài khoản đã bị xóa hoặc sau khi đổi database.
    // Khi đó không được ghi ID không tồn tại vào orders vì sẽ vi phạm khóa ngoại.
    if ($user_id !== null) {
        $user_stmt = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
        if (!$user_stmt) {
            throw new RuntimeException('Không thể kiểm tra tài khoản đặt hàng.');
        }
        $user_stmt->bind_param('i', $user_id);
        $user_stmt->execute();
        $valid_user = $user_stmt->get_result()->fetch_assoc();
        $user_stmt->close();
        if (!$valid_user) {
            $user_id = null;
        }
    }

    if ($requested_address_id !== null && $user_id === null) {
        throw new DomainException('Vui lòng đăng nhập lại để sử dụng địa chỉ đã lưu.');
    }

    if ($user_id !== null) {
        if ($requested_address_id !== null) {
            $address_stmt = $conn->prepare(
                'SELECT id FROM addresses WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE'
            );
            if (!$address_stmt) {
                throw new RuntimeException('Không thể kiểm tra địa chỉ nhận hàng.');
            }
            $address_stmt->bind_param('ii', $requested_address_id, $user_id);
            $address_stmt->execute();
            $saved_address = $address_stmt->get_result()->fetch_assoc();
            $address_stmt->close();
            if (!$saved_address) {
                throw new DomainException('Không tìm thấy địa chỉ thuộc tài khoản của bạn.');
            }
            $address_id = (int) $saved_address['id'];
            if ($make_default) {
                $clear_default = $conn->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
                if (!$clear_default) {
                    throw new RuntimeException('Không thể cập nhật địa chỉ mặc định.');
                }
                $clear_default->bind_param('i', $user_id);
                $clear_default->execute();
                $clear_default->close();
                if ($latitude === null) {
                    $default_stmt = $conn->prepare(
                        'UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?'
                    );
                    if (!$default_stmt) {
                        throw new RuntimeException('Không thể cập nhật địa chỉ mặc định.');
                    }
                    $default_stmt->bind_param('ii', $address_id, $user_id);
                } else {
                    $default_stmt = $conn->prepare(
                        'UPDATE addresses SET is_default = 1, latitude = ?, longitude = ? WHERE id = ? AND user_id = ?'
                    );
                    if (!$default_stmt) {
                        throw new RuntimeException('Không thể cập nhật địa chỉ mặc định.');
                    }
                    $default_stmt->bind_param('ddii', $latitude, $longitude, $address_id, $user_id);
                }
                $default_stmt->execute();
                $default_stmt->close();
            }
        } elseif ($make_default) {
            $saved_address = null;
            if (!$save_as_new_address) {
                $address_stmt = $conn->prepare(
                    'SELECT id FROM addresses WHERE user_id = ? AND recipient_name = ? AND address = ? AND phone = ? LIMIT 1'
                );
                if (!$address_stmt) {
                    throw new RuntimeException('Không thể kiểm tra địa chỉ nhận hàng.');
                }
                $address_stmt->bind_param('isss', $user_id, $full_name, $address, $phone);
                $address_stmt->execute();
                $saved_address = $address_stmt->get_result()->fetch_assoc();
                $address_stmt->close();
            }

            if ($saved_address) {
                $address_id = (int) $saved_address['id'];
                if ($latitude !== null) {
                    $location_stmt = $conn->prepare(
                        'UPDATE addresses SET latitude = ?, longitude = ? WHERE id = ? AND user_id = ?'
                    );
                    if (!$location_stmt) {
                        throw new RuntimeException('Không thể cập nhật vị trí địa chỉ.');
                    }
                    $location_stmt->bind_param('ddii', $latitude, $longitude, $address_id, $user_id);
                    $location_stmt->execute();
                    $location_stmt->close();
                }
            } else {
                $insert_address = $latitude === null
                    ? $conn->prepare(
                        'INSERT INTO addresses (user_id, recipient_name, address, phone, is_default)
                         VALUES (?, ?, ?, ?, 1)'
                    )
                    : $conn->prepare(
                        'INSERT INTO addresses (user_id, recipient_name, address, phone, is_default, latitude, longitude)
                         VALUES (?, ?, ?, ?, 1, ?, ?)'
                    );
                if (!$insert_address) {
                    throw new RuntimeException('Không thể lưu địa chỉ nhận hàng.');
                }
                if ($latitude === null) {
                    $insert_address->bind_param('isss', $user_id, $full_name, $address, $phone);
                } else {
                    $insert_address->bind_param(
                        'isssdd',
                        $user_id,
                        $full_name,
                        $address,
                        $phone,
                        $latitude,
                        $longitude
                    );
                }
                $insert_address->execute();
                $address_id = (int) $conn->insert_id;
                $insert_address->close();
            }

            $clear_default = $conn->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id <> ?');
            if (!$clear_default) {
                throw new RuntimeException('Không thể cập nhật địa chỉ mặc định.');
            }
            $clear_default->bind_param('ii', $user_id, $address_id);
            $clear_default->execute();
            $clear_default->close();
            $default_stmt = $conn->prepare(
                'UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?'
            );
            if (!$default_stmt) {
                throw new RuntimeException('Không thể cập nhật địa chỉ mặc định.');
            }
            $default_stmt->bind_param('ii', $address_id, $user_id);
            $default_stmt->execute();
            $default_stmt->close();
        }
    }

    if ($user_id === null) {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders (user_id, address_id, full_name, phone, email, address, total_price, payment_method)
             VALUES (NULL, NULL, ?, ?, ?, ?, ?, ?)"
        );
    } elseif ($address_id === null) {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders (user_id, address_id, full_name, phone, email, address, total_price, payment_method)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?)"
        );
    } else {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders (user_id, address_id, full_name, phone, email, address, total_price, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
    }
    $item_stmt = $conn->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, quantity, price, image)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    $products = [];
    $total_price = 0;
    foreach ($quantities as $product_id => $quantity) {
        $product_stmt->bind_param('i', $product_id);
        $product_stmt->execute();
        $product = $product_stmt->get_result()->fetch_assoc();

        if (!$product || (int)$product['is_deleted'] === 1) {
            throw new DomainException('Sản phẩm không tồn tại hoặc đã ngừng cung cấp.');
        }
        if (($product['status'] ?? '') !== 'active') {
            throw new DomainException('Sản phẩm "' . $product['name'] . '" hiện không còn được kinh doanh.');
        }
        if ((int) $product['stock'] < $quantity) {
            throw new DomainException('Sản phẩm "' . $product['name'] . '" không đủ số lượng tồn kho.');
        }

        $product['quantity'] = $quantity;
        $products[] = $product;
        $total_price += (float) $product['price_sale'] * $quantity;
    }

    $total_price = round($total_price, 2);
    if ($user_id === null) {
        $order_stmt->bind_param('ssssds', $full_name, $phone, $email, $address, $total_price, $payment_method);
    } elseif ($address_id === null) {
        $order_stmt->bind_param('issssds', $user_id, $full_name, $phone, $email, $address, $total_price, $payment_method);
    } else {
        $order_stmt->bind_param('iissssds', $user_id, $address_id, $full_name, $phone, $email, $address, $total_price, $payment_method);
    }
    $order_stmt->execute();
    $order_id = $conn->insert_id;

    foreach ($products as $product) {
        $product_id = (int) $product['id'];
        $quantity = (int) $product['quantity'];
        $price = (float) $product['price_sale'];
        $product_name = $product['name'];
        $image = $product['image'];

        $stock_stmt->bind_param('iii', $quantity, $product_id, $quantity);
        $stock_stmt->execute();
        if ($stock_stmt->affected_rows !== 1) {
            throw new DomainException('Số lượng tồn kho vừa thay đổi. Vui lòng thử đặt hàng lại.');
        }

        $item_stmt->bind_param('iisids', $order_id, $product_id, $product_name, $quantity, $price, $image);
        $item_stmt->execute();
    }

    $conn->commit();

    // Gửi tin cảm ơn tự động cho khách đã đăng nhập
    if ($user_id !== null) {
        sendAutoReply($conn, $user_id, 'order_thankyou');
    }
    $ordered_items = array_map(static function ($product) {
        return [
            'product_name' => $product['name'],
            'quantity' => (int) $product['quantity'],
            'price' => (float) $product['price_sale']
        ];
    }, $products);
    echo json_encode([
        'status' => 'success',
        'message' => 'Đặt hàng thành công!',
        'order_id' => $order_id,
        'total_price' => $total_price,
        'items' => $ordered_items
    ], JSON_UNESCAPED_UNICODE);
} catch (DomainException $e) {
    $conn->rollback();
    respondWithError($e->getMessage());
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Order placement failed: ' . $e->getMessage());
    respondWithError('Không thể xử lý đơn hàng lúc này. Vui lòng thử lại sau.', 500);
}

$conn->close();
?>
