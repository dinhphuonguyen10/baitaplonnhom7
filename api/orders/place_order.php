<?php
require_once __DIR__ . '/../../include/session.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../include/auto_reply_helper.php';

header('Content-Type: application/json; charset=utf-8');

function respondWithError($message, $statusCode = 400)
{
    http_response_code($statusCode);
    echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function parseOrderMoneyCents($value): ?int
{
    if (!is_string($value) && !is_int($value)) {
        return null;
    }

    $value = trim((string) $value);
    if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
        return null;
    }

    [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
    return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
}

function formatOrderMoneyCents(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
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
    if ($quantities[$product_id] > 99) {
        respondWithError('Số lượng mỗi sản phẩm không được vượt quá 99.');
    }
}
ksort($quantities, SORT_NUMERIC);

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
        "SELECT p.id, p.sku, p.name, p.price_original, c.name AS category_name,
                CASE
                    WHEN p.promotion_ends_at IS NOT NULL AND p.promotion_ends_at <= NOW()
                    THEN p.price_original
                    ELSE p.price_sale
                END AS effective_price,
                p.image, p.stock, p.status, p.is_deleted
         FROM products p
         INNER JOIN categories c
           ON c.id = p.category_id AND c.status = 'active' AND c.is_deleted = 0
         WHERE p.id = ?
         FOR UPDATE"
    );
    if (!$product_stmt) {
        throw new RuntimeException('Không thể kiểm tra giá và tồn kho sản phẩm.');
    }
    $stock_stmt = $conn->prepare(
        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
    );
    if (!$stock_stmt) {
        throw new RuntimeException('Không thể chuẩn bị cập nhật tồn kho.');
    }
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

    $products = [];
    $total_price_cents = 0;
    $total_discount_cents = 0;
    $gross_subtotal_cents = 0;
    $max_order_amount_cents = 999999999999;
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
        if (!is_string($product['sku']) || trim($product['sku']) === '') {
            throw new RuntimeException('Sản phẩm chưa có mã SKU hợp lệ.');
        }

        $unit_price_cents = parseOrderMoneyCents($product['effective_price']);
        $original_price_cents = parseOrderMoneyCents($product['price_original']);
        if ($unit_price_cents === null || $original_price_cents === null
            || $unit_price_cents > $original_price_cents) {
            throw new RuntimeException('Giá sản phẩm trong cơ sở dữ liệu không hợp lệ.');
        }

        $unit_discount_cents = $original_price_cents - $unit_price_cents;
        $line_total_cents = $unit_price_cents * $quantity;
        $line_discount_cents = $unit_discount_cents * $quantity;
        $line_gross_cents = $original_price_cents * $quantity;
        if ($line_total_cents > $max_order_amount_cents
            || $line_discount_cents > $max_order_amount_cents
            || $line_gross_cents > $max_order_amount_cents
            || $total_price_cents > $max_order_amount_cents - $line_total_cents
            || $total_discount_cents > $max_order_amount_cents - $line_discount_cents
            || $gross_subtotal_cents > $max_order_amount_cents - $line_gross_cents) {
            throw new DomainException('Tổng giá trị đơn hàng vượt quá giới hạn thanh toán.');
        }

        $product['quantity'] = $quantity;
        $product['effective_price_decimal'] = formatOrderMoneyCents($unit_price_cents);
        $product['original_price_decimal'] = formatOrderMoneyCents($original_price_cents);
        $product['discount_amount_decimal'] = formatOrderMoneyCents($line_discount_cents);
        $product['line_total_decimal'] = formatOrderMoneyCents($line_total_cents);
        $product['attributes_snapshot'] = '';
        $product['category_name_snapshot'] = (string) $product['category_name'];
        $products[] = $product;
        $total_price_cents += $line_total_cents;
        $total_discount_cents += $line_discount_cents;
        $gross_subtotal_cents += $line_gross_cents;
    }

    if ($gross_subtotal_cents - $total_discount_cents !== $total_price_cents) {
        throw new RuntimeException('Không thể cân đối tổng tiền và giảm giá đơn hàng.');
    }

    $total_price = formatOrderMoneyCents($total_price_cents);
    $total_discount = formatOrderMoneyCents($total_discount_cents);
    if ($user_id === null) {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders
                (user_id, address_id, full_name, phone, email, address, total_price, shipping_fee, discount_amount,
                 payment_method, inventory_status, inventory_reserved_at, expires_at)
             VALUES (NULL, NULL, ?, ?, ?, ?, ?, 0.00, ?, ?, 'reserved', NOW(),
                     CASE WHEN ? = 'BANK_QR' THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                          ELSE DATE_ADD(NOW(), INTERVAL 24 HOUR) END)"
        );
    } elseif ($address_id === null) {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders
                (user_id, address_id, full_name, phone, email, address, total_price, shipping_fee, discount_amount,
                 payment_method, inventory_status, inventory_reserved_at, expires_at)
             VALUES (?, NULL, ?, ?, ?, ?, ?, 0.00, ?, ?, 'reserved', NOW(),
                     CASE WHEN ? = 'BANK_QR' THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                          ELSE DATE_ADD(NOW(), INTERVAL 24 HOUR) END)"
        );
    } else {
        $order_stmt = $conn->prepare(
            "INSERT INTO orders
                (user_id, address_id, full_name, phone, email, address, total_price, shipping_fee, discount_amount,
                 payment_method, inventory_status, inventory_reserved_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, 'reserved', NOW(),
                     CASE WHEN ? = 'BANK_QR' THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                          ELSE DATE_ADD(NOW(), INTERVAL 24 HOUR) END)"
        );
    }
    if (!$order_stmt) {
        throw new RuntimeException('Không thể tạo đơn hàng.');
    }
    if ($user_id === null) {
        $order_stmt->bind_param(
            'ssssssss',
            $full_name,
            $phone,
            $email,
            $address,
            $total_price,
            $total_discount,
            $payment_method,
            $payment_method
        );
    } elseif ($address_id === null) {
        $order_stmt->bind_param(
            'issssssss',
            $user_id,
            $full_name,
            $phone,
            $email,
            $address,
            $total_price,
            $total_discount,
            $payment_method,
            $payment_method
        );
    } else {
        $order_stmt->bind_param(
            'iissssssss',
            $user_id,
            $address_id,
            $full_name,
            $phone,
            $email,
            $address,
            $total_price,
            $total_discount,
            $payment_method,
            $payment_method
        );
    }
    $order_stmt->execute();
    if ($order_stmt->affected_rows !== 1) {
        throw new RuntimeException('Không thể lưu đơn hàng.');
    }
    $order_id = $conn->insert_id;
    $order_stmt->close();

    $item_stmt = $conn->prepare(
        'INSERT INTO order_items
            (order_id, product_id, product_sku, attributes_snapshot, category_name_snapshot,
             product_name, quantity, price, original_price, discount_amount, line_total, image)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$item_stmt) {
        throw new RuntimeException('Không thể lưu snapshot sản phẩm.');
    }

    foreach ($products as $product) {
        $product_id = (int) $product['id'];
        $quantity = (int) $product['quantity'];
        $sku = (string) $product['sku'];
        $attributes_snapshot = (string) $product['attributes_snapshot'];
        $category_name_snapshot = (string) $product['category_name_snapshot'];
        $product_name = $product['name'];
        $price = $product['effective_price_decimal'];
        $original_price = $product['original_price_decimal'];
        $line_discount = $product['discount_amount_decimal'];
        $line_total = $product['line_total_decimal'];
        $image = $product['image'];

        $stock_stmt->bind_param('iii', $quantity, $product_id, $quantity);
        $stock_stmt->execute();
        if ($stock_stmt->affected_rows !== 1) {
            throw new DomainException('Số lượng tồn kho vừa thay đổi. Vui lòng thử đặt hàng lại.');
        }

        $item_stmt->bind_param(
            'iissssisssss',
            $order_id,
            $product_id,
            $sku,
            $attributes_snapshot,
            $category_name_snapshot,
            $product_name,
            $quantity,
            $price,
            $original_price,
            $line_discount,
            $line_total,
            $image
        );
        $item_stmt->execute();
        if ($item_stmt->affected_rows !== 1) {
            throw new RuntimeException('Không thể lưu chi tiết sản phẩm của đơn hàng.');
        }
    }
    $item_stmt->close();
    $product_stmt->close();
    $stock_stmt->close();

    $orderCheckStmt = $conn->prepare(
        'SELECT o.total_price, o.discount_amount,
                COUNT(oi.id) AS item_count,
                COALESCE(SUM(oi.line_total), 0.00) AS line_total_sum,
                COALESCE(SUM(oi.price * oi.quantity), 0.00) AS calculated_line_total,
                COALESCE(SUM(oi.discount_amount), 0.00) AS item_discount_sum,
                COALESCE(SUM(oi.original_price * oi.quantity), 0.00) AS gross_subtotal,
                COALESCE(SUM(oi.original_price * oi.quantity - oi.discount_amount), 0.00) AS calculated_payable
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.id = ?
         GROUP BY o.id'
    );
    if (!$orderCheckStmt) {
        throw new RuntimeException('Không thể kiểm tra tính nhất quán số tiền đơn hàng.');
    }
    $orderCheckStmt->bind_param('i', $order_id);
    $orderCheckStmt->execute();
    $orderCheck = $orderCheckStmt->get_result()->fetch_assoc();
    $orderCheckStmt->close();
    if (!$orderCheck
        || (int) $orderCheck['item_count'] !== count($products)
        || parseOrderMoneyCents((string) $orderCheck['total_price']) !== $total_price_cents
        || parseOrderMoneyCents((string) $orderCheck['discount_amount']) !== $total_discount_cents
        || parseOrderMoneyCents((string) $orderCheck['line_total_sum']) !== $total_price_cents
        || parseOrderMoneyCents((string) $orderCheck['calculated_line_total']) !== $total_price_cents
        || parseOrderMoneyCents((string) $orderCheck['item_discount_sum']) !== $total_discount_cents
        || parseOrderMoneyCents((string) $orderCheck['gross_subtotal']) !== $gross_subtotal_cents
        || parseOrderMoneyCents((string) $orderCheck['calculated_payable']) !== $total_price_cents) {
        throw new RuntimeException('Tổng tiền đơn hàng không khớp với chi tiết sản phẩm.');
    }

    $history_stmt = $conn->prepare(
        "INSERT INTO order_status_history
            (order_id, status, old_status, new_status, note, actor_name)
         VALUES (?, 'Chờ xác nhận', NULL, 'Chờ xác nhận', 'Đơn hàng được tạo.', ?)"
    );
    if (!$history_stmt) {
        throw new RuntimeException('Không thể ghi lịch sử đơn hàng.');
    }
    $actor_name = $user_id !== null
        ? mb_substr((string) ($_SESSION['username'] ?? 'Khách hàng'), 0, 100, 'UTF-8')
        : 'Khách vãng lai';
    $history_stmt->bind_param('is', $order_id, $actor_name);
    $history_stmt->execute();
    $history_stmt->close();

    $conn->commit();

    // Phản hồi tự động không được làm đơn hàng đã commit bị báo thất bại.
    if ($user_id !== null) {
        try {
            sendAutoReply($conn, $user_id, 'order_thankyou');
        } catch (Throwable $e) {
            error_log('Order confirmation auto-reply failed: ' . $e->getMessage());
        }
    }
    $ordered_items = array_map(static function ($product) {
        return [
            'product_name' => $product['name'],
            'product_sku' => $product['sku'],
            'attributes_snapshot' => $product['attributes_snapshot'],
            'category_name_snapshot' => $product['category_name_snapshot'],
            'quantity' => (int) $product['quantity'],
            'price' => $product['effective_price_decimal'],
            'original_price' => $product['original_price_decimal'],
            'discount_amount' => $product['discount_amount_decimal'],
            'line_total' => $product['line_total_decimal'],
        ];
    }, $products);
    echo json_encode([
        'status' => 'success',
        'message' => 'Đặt hàng thành công!',
        'order_id' => $order_id,
        'subtotal' => formatOrderMoneyCents($gross_subtotal_cents),
        'total_price' => $total_price,
        'discount_amount' => $total_discount,
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
