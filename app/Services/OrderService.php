<?php

namespace App\Services;

use App\Core\Database;
use App\Database\SchemaManager;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Lớp OrderService - nghiệp vụ đơn hàng phía khách hàng:
 * đặt hàng (trong transaction, trừ kho, lưu địa chỉ), hủy đơn (hoàn kho),
 * tra cứu đơn và danh sách "Đơn hàng của tôi".
 *
 * Lỗi nghiệp vụ hiển thị cho khách được ném dưới dạng DomainException.
 */
class OrderService
{
    /** Nhóm trạng thái dùng cho bộ lọc "Đơn hàng của tôi". */
    public const STATUS_GROUPS = [
        'processing' => ['Chờ xác nhận', 'Đã xác nhận', 'Chuẩn bị đơn hàng'],
        'shipping' => ['Đang vận chuyển', 'Đang giao'],
        'delivered' => ['Đã giao hàng', 'Đã giao'],
        'cancelled' => ['Đã hủy'],
    ];

    /** Ánh xạ trạng thái => nhóm khi đếm số đơn. */
    public const STATUS_TO_GROUP = [
        'Chờ xác nhận' => 'processing',
        'Đã xác nhận' => 'processing',
        'Chuẩn bị đơn hàng' => 'processing',
        'Đang xử lý' => 'processing',
        'Đang chuẩn bị' => 'processing',
        'Đang vận chuyển' => 'shipping',
        'Đang giao' => 'shipping',
        'Đã giao hàng' => 'delivered',
        'Đã giao' => 'delivered',
        'Đã hủy' => 'cancelled',
    ];

    private Database $db;
    private Order $orders;
    private OrderItem $orderItems;
    private Product $products;
    private Address $addresses;
    private User $users;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->orders = new Order($this->db);
        $this->orderItems = new OrderItem($this->db);
        $this->products = new Product($this->db);
        $this->addresses = new Address($this->db);
        $this->users = new User($this->db);
    }

    // ─────────────────────────── Đặt hàng ───────────────────────────

    /**
     * Kiểm tra dữ liệu đặt hàng (chưa chạm CSDL). Ném DomainException với thông báo cho khách.
     *
     * @return array dữ liệu đã chuẩn hóa để truyền cho placeOrder()
     */
    public function validatePlaceOrderInput(array $data): array
    {
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $cart = $data['cart'] ?? null;
        $paymentMethod = strtoupper(trim((string) ($data['payment_method'] ?? 'COD')));

        if (!in_array($paymentMethod, ['COD', 'BANK_QR'], true)) {
            throw new DomainException('Phương thức thanh toán không hợp lệ.');
        }
        if (!is_array($cart) || $cart === []) {
            throw new DomainException('Giỏ hàng trống hoặc không hợp lệ.');
        }

        $fullName = trim((string) ($customer['name'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $address = trim((string) ($customer['address'] ?? ''));

        $requestedAddressId = null;
        if (array_key_exists('address_id', $data) && $data['address_id'] !== null && $data['address_id'] !== '') {
            $requestedAddressId = filter_var($data['address_id'], FILTER_VALIDATE_INT);
            if ($requestedAddressId === false || $requestedAddressId < 1) {
                throw new DomainException('Địa chỉ nhận hàng không hợp lệ.');
            }
        }
        $saveAsNewAddress = filter_var($data['save_as_new_address'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $makeDefault = filter_var($data['make_default'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $latitude = null;
        $longitude = null;
        $coordinates = $data['coordinates'] ?? null;
        if ($coordinates !== null) {
            if (!is_array($coordinates)
                || !isset($coordinates['latitude'], $coordinates['longitude'])
                || !is_numeric($coordinates['latitude'])
                || !is_numeric($coordinates['longitude'])) {
                throw new DomainException('Vị trí bản đồ không hợp lệ.');
            }
            $latitude = (float) $coordinates['latitude'];
            $longitude = (float) $coordinates['longitude'];
            if (!is_finite($latitude) || !is_finite($longitude)
                || $latitude < -90 || $latitude > 90
                || $longitude < -180 || $longitude > 180) {
                throw new DomainException('Tọa độ bản đồ nằm ngoài phạm vi hợp lệ.');
            }
        }

        if ($fullName === '' || $phone === '' || $email === '' || $address === '') {
            throw new DomainException('Vui lòng cung cấp đầy đủ thông tin giao hàng.');
        }
        if (strlen($fullName) > 150 || strlen($phone) > 20 || strlen($email) > 254 || strlen($address) > 500) {
            throw new DomainException('Thông tin đặt hàng vượt quá độ dài cho phép.');
        }
        if (!preg_match('/^0[0-9]{9}$/', $phone)) {
            throw new DomainException('Số điện thoại phải gồm đúng 10 chữ số và bắt đầu bằng 0.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('Vui lòng nhập địa chỉ email hợp lệ.');
        }

        $quantities = [];
        foreach ($cart as $item) {
            if (!is_array($item)) {
                throw new DomainException('Dữ liệu sản phẩm trong giỏ hàng không hợp lệ.');
            }

            $productId = filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($productId === false || $productId < 1 || $quantity === false || $quantity < 1 || $quantity > 99) {
                throw new DomainException('Một sản phẩm trong giỏ hàng không hợp lệ. Vui lòng xóa sản phẩm cũ và thêm lại từ danh sách.');
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }

        return [
            'payment_method' => $paymentMethod,
            'full_name' => $fullName,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'requested_address_id' => $requestedAddressId,
            'save_as_new_address' => $saveAsNewAddress,
            'make_default' => $makeDefault,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'quantities' => $quantities,
        ];
    }

    /**
     * Tạo đơn hàng trong một transaction: kiểm tra tài khoản/địa chỉ, khóa sản phẩm, trừ kho,
     * lưu chi tiết đơn; sau đó gửi tin cảm ơn tự động.
     * DomainException = lỗi hiển thị cho khách; lỗi khác = lỗi hệ thống.
     *
     * @return array{order_id:int, total_price:float, items:array}
     */
    public function placeOrder(array $input, ?int $sessionUserId): array
    {
        $fullName = $input['full_name'];
        $phone = $input['phone'];
        $email = $input['email'];
        $address = $input['address'];
        $latitude = $input['latitude'];
        $longitude = $input['longitude'];
        $requestedAddressId = $input['requested_address_id'];
        $makeDefault = $input['make_default'];

        try {
            (new SchemaManager($this->db))->ensureOrderEmailColumn();

            $this->db->beginTransaction();

            $userId = $sessionUserId;
            $addressId = null;

            // Session cũ có thể còn giữ ID tài khoản đã bị xóa hoặc sau khi đổi database.
            // Khi đó không được ghi ID không tồn tại vào orders vì sẽ vi phạm khóa ngoại.
            if ($userId !== null && !$this->users->exists($userId)) {
                $userId = null;
            }

            if ($requestedAddressId !== null && $userId === null) {
                throw new DomainException('Vui lòng đăng nhập lại để sử dụng địa chỉ đã lưu.');
            }

            if ($userId !== null) {
                if ($requestedAddressId !== null) {
                    $addressId = $this->addresses->lockOwnedId($requestedAddressId, $userId);
                    if ($addressId === null) {
                        throw new DomainException('Không tìm thấy địa chỉ thuộc tài khoản của bạn.');
                    }
                    if ($makeDefault) {
                        $this->addresses->clearDefaultForUser($userId);
                        $this->addresses->markDefaultForUser($addressId, $userId, $latitude, $longitude);
                    }
                } elseif ($makeDefault) {
                    $savedAddressId = null;
                    if (!$input['save_as_new_address']) {
                        $savedAddressId = $this->addresses->findMatchingId($userId, $fullName, $address, $phone);
                    }

                    if ($savedAddressId !== null) {
                        $addressId = $savedAddressId;
                        if ($latitude !== null) {
                            $this->addresses->updateCoordinatesForUser($addressId, $userId, $latitude, $longitude);
                        }
                    } else {
                        $addressId = $this->addresses->createForUser(
                            $userId,
                            $fullName,
                            $address,
                            $phone,
                            1,
                            $latitude,
                            $longitude
                        );
                    }

                    $this->addresses->clearDefaultForUser($userId, $addressId);
                    $this->addresses->markDefaultForUser($addressId, $userId);
                }
            }

            $products = [];
            $totalPrice = 0;
            foreach ($input['quantities'] as $productId => $quantity) {
                $product = $this->products->lockForOrder((int) $productId);

                if (!$product || (int) $product['is_deleted'] === 1) {
                    throw new DomainException('Sản phẩm không tồn tại hoặc đã ngừng cung cấp.');
                }
                if (($product['status'] ?? 'active') === 'inactive') {
                    throw new DomainException('Sản phẩm "' . $product['name'] . '" đã ngừng kinh doanh, không thể đặt mua.');
                }
                if (($product['status'] ?? 'active') === 'hidden') {
                    throw new DomainException('Sản phẩm "' . $product['name'] . '" hiện không khả dụng.');
                }
                if ((int) $product['stock'] < $quantity) {
                    throw new DomainException('Sản phẩm "' . $product['name'] . '" không đủ số lượng tồn kho.');
                }

                $product['quantity'] = $quantity;
                $products[] = $product;
                $totalPrice += (float) $product['price_sale'] * $quantity;
            }

            $totalPrice = round($totalPrice, 2);
            $orderId = $this->orders->createCustomerOrder(
                $userId,
                $userId === null ? null : $addressId,
                $fullName,
                $phone,
                $email,
                $address,
                $totalPrice,
                $input['payment_method']
            );

            foreach ($products as $product) {
                $productId = (int) $product['id'];
                $quantity = (int) $product['quantity'];

                if ($this->products->decrementStock($productId, $quantity) !== 1) {
                    throw new DomainException('Số lượng tồn kho vừa thay đổi. Vui lòng thử đặt hàng lại.');
                }

                $this->orderItems->createLine(
                    $orderId,
                    $productId,
                    (string) $product['name'],
                    $quantity,
                    (float) $product['price_sale'],
                    $product['image'] === null ? null : (string) $product['image']
                );
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        // Gửi tin cảm ơn tự động cho khách đã đăng nhập
        if ($userId !== null) {
            (new AutoReplyService($this->db))->send($userId, 'order_thankyou');
        }

        return [
            'order_id' => $orderId,
            'total_price' => $totalPrice,
            'items' => array_map(static function (array $product): array {
                return [
                    'product_name' => $product['name'],
                    'quantity' => (int) $product['quantity'],
                    'price' => (float) $product['price_sale'],
                ];
            }, $products),
        ];
    }

    // ─────────────────────────── Hủy đơn ───────────────────────────

    /**
     * Khách hàng hủy đơn đang chờ xác nhận và hoàn kho sản phẩm.
     * Mọi lỗi hiển thị cho khách được ném dưới dạng DomainException (kể cả lỗi hệ thống khi cập nhật).
     */
    public function cancelCustomerOrder(int $userId, int $orderId): void
    {
        $order = $this->orders->findOwnerAndStatus($orderId);
        if ($order === null) {
            throw new DomainException('Không tìm thấy đơn hàng');
        }
        if ((int) $order['user_id'] !== $userId) {
            throw new DomainException('Bạn không có quyền hủy đơn này');
        }

        $status = $order['status'];
        if ($status === 'Đã hủy' || $status === 'Canceled') {
            throw new DomainException('Đơn đã được hủy trước đó');
        }

        // Khách hàng chỉ được hủy khi đơn đang chờ xác nhận.
        if (!in_array($status, ['Chờ xác nhận', 'Pending', '', null], true)) {
            throw new DomainException("Chỉ có thể hủy khi đơn ở trạng thái 'Chờ xác nhận'");
        }

        $this->db->beginTransaction();
        try {
            foreach ($this->orderItems->listStockLinesByOrder($orderId) as $item) {
                if ($item['product_id'] === null) {
                    continue;
                }
                $this->products->incrementStock((int) $item['product_id'], (int) $item['quantity']);
            }

            if ($this->orders->updateStatusIfUnchanged($orderId, 'Đã hủy', $status) !== 1) {
                throw new RuntimeException('Trạng thái đơn hàng đã được thay đổi.');
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            error_log('Order cancellation failed: ' . $e->getMessage());
            throw new DomainException('Không thể hủy đơn hàng lúc này.', 0, $e);
        }
    }

    // ─────────────────────────── Tra cứu ───────────────────────────

    /** Tra cứu đơn (kèm chi tiết sản phẩm) theo mã đơn hoặc số điện thoại. */
    public function lookup(string $query): array
    {
        $orders = [];
        foreach ($this->orders->findByIdOrPhone($query) as $row) {
            $row['items'] = $this->orderItems->listByOrder((int) $row['id']);
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * Danh sách "Đơn hàng của tôi" kèm số lượng đơn theo nhóm trạng thái.
     *
     * @return array{orders:array, counts:array<string,int>}
     */
    public function customerOrders(int $userId, string $statusFilter, string $timeFilter): array
    {
        $statuses = [];
        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $statuses = self::STATUS_GROUPS[$statusFilter] ?? [$statusFilter];
        }

        $orders = [];
        foreach ($this->orders->findForCustomer($userId, $timeFilter, $statuses) as $row) {
            $row['items'] = $this->orderItems->listByOrderWithReviewFlag((int) $row['id'], $userId);
            $orders[] = $row;
        }

        $counts = [
            'all' => 0,
            'processing' => 0,
            'shipping' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];
        foreach ($this->orders->countByStatusForCustomer($userId, $timeFilter) as $countRow) {
            $count = (int) $countRow['cnt'];
            $counts['all'] += $count;
            $group = self::STATUS_TO_GROUP[$countRow['status']] ?? 'processing';
            $counts[$group] += $count;
        }

        return ['orders' => $orders, 'counts' => $counts];
    }
}
