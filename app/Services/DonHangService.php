<?php

namespace App\Services;

use App\Core\Database;
use App\Database\QuanLyCauTruc;
use App\Models\DiaChi;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\LichSuTrangThaiDon;
use App\Models\SanPham;
use App\Models\KhachHang;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Lớp DonHangService - nghiệp vụ đơn hàng phía khách hàng:
 * đặt hàng (trong transaction, trừ kho, lưu địa chỉ), hủy đơn (hoàn kho),
 * tra cứu đơn và danh sách "Đơn hàng của tôi".
 *
 * Lỗi nghiệp vụ hiển thị cho khách được ném dưới dạng DomainException.
 */
class DonHangService
{
    /** Khóa phiên chứa CSRF token cho thao tác hủy đơn của khách. */
    public const CANCEL_CSRF_KEY = 'order_cancel_csrf';

    /** Nhóm trạng thái dùng cho bộ lọc "Đơn hàng của tôi". */
    public const STATUS_GROUPS = [
        'processing' => ['Chờ xác nhận', 'Đã xác nhận', 'Đang chuẩn bị hàng', 'Chuẩn bị đơn hàng', 'Đang xử lý', 'Đang chuẩn bị', 'Pending'],
        'shipping' => ['Đang giao hàng', 'Đang vận chuyển', 'Đang giao'],
        'delivered' => ['Đã giao hàng', 'Đã giao', 'Hoàn thành'],
        'cancelled' => ['Đã hủy', 'Canceled', 'cancelled', 'huy'],
    ];

    private Database $db;
    private DonHang $orders;
    private ChiTietDonHang $orderItems;
    private SanPham $products;
    private DiaChi $addresses;
    private KhachHang $users;
    private LichSuTrangThaiDon $history;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->orders = new DonHang($this->db);
        $this->orderItems = new ChiTietDonHang($this->db);
        $this->products = new SanPham($this->db);
        $this->addresses = new DiaChi($this->db);
        $this->users = new KhachHang($this->db);
        $this->history = new LichSuTrangThaiDon($this->db);
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
            if ($quantities[$productId] > 99) {
                throw new DomainException('Số lượng mỗi sản phẩm không được vượt quá 99.');
            }
        }
        ksort($quantities, SORT_NUMERIC);

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
     * Tạo đơn hàng trong một transaction: kiểm tra tài khoản/địa chỉ, khóa sản phẩm (theo thứ tự ID),
     * tính tiền theo giá hiệu lực (đơn vị xu), giữ tồn kho, lưu snapshot sản phẩm, kiểm tra tổng tiền
     * và ghi lịch sử trạng thái; sau đó gửi tin cảm ơn tự động.
     * DomainException = lỗi hiển thị cho khách; lỗi khác = lỗi hệ thống.
     *
     * @param bool $isCustomerRole phiên có role = customer
     * @param mixed $sessionUserId giá trị user_id trong phiên (chưa kiểm tra)
     * @return array{order_id:int, subtotal:string, total_price:string, discount_amount:string, items:array}
     */
    public function placeOrder(array $input, bool $isCustomerRole, $sessionUserId, ?string $sessionUsername): array
    {
        $fullName = $input['full_name'];
        $phone = $input['phone'];
        $email = $input['email'];
        $address = $input['address'];
        $latitude = $input['latitude'];
        $longitude = $input['longitude'];
        $requestedAddressId = $input['requested_address_id'];
        $makeDefault = $input['make_default'];
        $paymentMethod = $input['payment_method'];

        try {
            (new QuanLyCauTruc($this->db))->ensureOrderEmailColumn();

            $this->db->beginTransaction();

            $userId = null;
            $addressId = null;

            if ($isCustomerRole) {
                $validatedUserId = filter_var($sessionUserId, FILTER_VALIDATE_INT);
                if ($validatedUserId === false || $validatedUserId < 1) {
                    throw new DomainException('Phiên đăng nhập không hợp lệ. Vui lòng đăng nhập lại trước khi đặt hàng.');
                }
                if (!$this->users->exists($validatedUserId)) {
                    throw new DomainException('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại trước khi đặt hàng.');
                }
                $userId = (int) $validatedUserId;
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
            $totalPriceCents = 0;
            $totalDiscountCents = 0;
            $grossSubtotalCents = 0;
            $maxOrderAmountCents = 999999999999;
            foreach ($input['quantities'] as $productId => $quantity) {
                $product = $this->products->lockForOrderWithPricing((int) $productId);

                if (!$product || (int) $product['is_deleted'] === 1) {
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

                $unitPriceCents = self::parseMoneyCents($product['effective_price']);
                $originalPriceCents = self::parseMoneyCents($product['price_original']);
                if ($unitPriceCents === null || $originalPriceCents === null
                    || $unitPriceCents > $originalPriceCents) {
                    throw new RuntimeException('Giá sản phẩm trong cơ sở dữ liệu không hợp lệ.');
                }

                $unitDiscountCents = $originalPriceCents - $unitPriceCents;
                $lineTotalCents = $unitPriceCents * $quantity;
                $lineDiscountCents = $unitDiscountCents * $quantity;
                $lineGrossCents = $originalPriceCents * $quantity;
                if ($lineTotalCents > $maxOrderAmountCents
                    || $lineDiscountCents > $maxOrderAmountCents
                    || $lineGrossCents > $maxOrderAmountCents
                    || $totalPriceCents > $maxOrderAmountCents - $lineTotalCents
                    || $totalDiscountCents > $maxOrderAmountCents - $lineDiscountCents
                    || $grossSubtotalCents > $maxOrderAmountCents - $lineGrossCents) {
                    throw new DomainException('Tổng giá trị đơn hàng vượt quá giới hạn thanh toán.');
                }

                $product['quantity'] = $quantity;
                $product['effective_price_decimal'] = self::formatMoneyCents($unitPriceCents);
                $product['original_price_decimal'] = self::formatMoneyCents($originalPriceCents);
                $product['discount_amount_decimal'] = self::formatMoneyCents($lineDiscountCents);
                $product['line_total_decimal'] = self::formatMoneyCents($lineTotalCents);
                $product['attributes_snapshot'] = '';
                $product['category_name_snapshot'] = (string) $product['category_name'];
                $products[] = $product;
                $totalPriceCents += $lineTotalCents;
                $totalDiscountCents += $lineDiscountCents;
                $grossSubtotalCents += $lineGrossCents;
            }

            if ($grossSubtotalCents - $totalDiscountCents !== $totalPriceCents) {
                throw new RuntimeException('Không thể cân đối tổng tiền và giảm giá đơn hàng.');
            }

            $totalPrice = self::formatMoneyCents($totalPriceCents);
            $totalDiscount = self::formatMoneyCents($totalDiscountCents);
            [$inserted, $orderId] = $this->orders->createReservedCustomerOrder(
                $userId,
                $userId === null ? null : $addressId,
                $fullName,
                $phone,
                $email,
                $address,
                $totalPrice,
                $totalDiscount,
                $paymentMethod
            );
            if ($inserted !== 1) {
                throw new RuntimeException('Không thể lưu đơn hàng.');
            }

            foreach ($products as $product) {
                $productId = (int) $product['id'];
                $quantity = (int) $product['quantity'];

                if ($this->products->decrementStock($productId, $quantity) !== 1) {
                    throw new DomainException('Số lượng tồn kho vừa thay đổi. Vui lòng thử đặt hàng lại.');
                }

                $lineInserted = $this->orderItems->createSnapshotLine(
                    $orderId,
                    $productId,
                    (string) $product['sku'],
                    (string) $product['attributes_snapshot'],
                    (string) $product['category_name_snapshot'],
                    (string) $product['name'],
                    $quantity,
                    $product['effective_price_decimal'],
                    $product['original_price_decimal'],
                    $product['discount_amount_decimal'],
                    $product['line_total_decimal'],
                    $product['image'] === null ? null : (string) $product['image']
                );
                if ($lineInserted !== 1) {
                    throw new RuntimeException('Không thể lưu chi tiết sản phẩm của đơn hàng.');
                }
            }

            $orderCheck = $this->orders->findAmountConsistency($orderId);
            if (!$orderCheck
                || (int) $orderCheck['item_count'] !== count($products)
                || self::parseMoneyCents((string) $orderCheck['total_price']) !== $totalPriceCents
                || self::parseMoneyCents((string) $orderCheck['discount_amount']) !== $totalDiscountCents
                || self::parseMoneyCents((string) $orderCheck['line_total_sum']) !== $totalPriceCents
                || self::parseMoneyCents((string) $orderCheck['calculated_line_total']) !== $totalPriceCents
                || self::parseMoneyCents((string) $orderCheck['item_discount_sum']) !== $totalDiscountCents
                || self::parseMoneyCents((string) $orderCheck['gross_subtotal']) !== $grossSubtotalCents
                || self::parseMoneyCents((string) $orderCheck['calculated_payable']) !== $totalPriceCents) {
                throw new RuntimeException('Tổng tiền đơn hàng không khớp với chi tiết sản phẩm.');
            }

            $actorName = $userId !== null
                ? mb_substr((string) ($sessionUsername ?? 'Khách hàng'), 0, 100, 'UTF-8')
                : 'Khách vãng lai';
            $this->history->log($orderId, 'Chờ xác nhận', null, 'Đơn hàng được tạo.', $actorName);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        // Phản hồi tự động không được làm đơn hàng đã commit bị báo thất bại.
        if ($userId !== null) {
            try {
                (new TuDongTraLoiService($this->db))->send($userId, 'order_thankyou');
            } catch (Throwable $e) {
                error_log('Order confirmation auto-reply failed: ' . $e->getMessage());
            }
        }

        return [
            'order_id' => $orderId,
            'subtotal' => self::formatMoneyCents($grossSubtotalCents),
            'total_price' => $totalPrice,
            'discount_amount' => $totalDiscount,
            'items' => array_map(static function (array $product): array {
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
            }, $products),
        ];
    }

    /** Chuỗi tiền "123.45" (tối đa 10 chữ số phần nguyên, 2 chữ số thập phân) => số xu; null nếu sai định dạng. */
    public static function parseMoneyCents($value): ?int
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

    /** Số xu => chuỗi decimal "123.45". */
    public static function formatMoneyCents(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    // ─────────────────────────── Hủy đơn ───────────────────────────

    /**
     * Khách hàng hủy đơn đang chờ xác nhận (kèm lý do): hoàn tồn kho đang giữ, ghi lịch sử.
     * DomainException = xung đột hiển thị cho khách (409); lỗi khác = lỗi hệ thống.
     */
    public function cancelCustomerOrder(int $userId, int $orderId, string $reason, string $actorName): void
    {
        $this->db->beginTransaction();
        try {
            $order = $this->orders->lockForCustomerCancel($orderId, $userId);
            if (!$order) {
                throw new DomainException('Không tìm thấy đơn hàng của bạn.');
            }

            if (
                QuyTrinhTrangThaiDon::normalize((string) $order['status']) !== 'Chờ xác nhận'
                || !QuyTrinhTrangThaiDon::canTransition((string) $order['status'], 'Đã hủy')
            ) {
                throw new DomainException('Chỉ có thể hủy đơn hàng khi đơn đang chờ xác nhận.');
            }
            $previousStatus = (string) $order['status'];

            if ($order['inventory_status'] === 'reserved') {
                if (!(new TonKhoDonHangService($this->db))->releaseReserved($orderId)) {
                    throw new RuntimeException('Không thể hoàn tồn kho cho đơn hàng.');
                }
            } elseif ($order['inventory_status'] !== 'legacy') {
                throw new DomainException('Tồn kho của đơn hàng đã được xử lý; vui lòng tải lại trang.');
            }

            if ($this->orders->cancelByCustomer($orderId, $userId, $reason) !== 1) {
                throw new DomainException('Trạng thái đơn hàng đã thay đổi. Vui lòng tải lại trang.');
            }

            $this->history->log($orderId, 'Đã hủy', $previousStatus, $reason, $actorName);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // ─────────────────────────── Tra cứu ───────────────────────────

    /** Tra cứu một đơn (kèm chi tiết sản phẩm) theo mã đơn thuộc tài khoản khách; null nếu không có. */
    public function lookup(int $orderId, int $userId): ?array
    {
        $order = $this->orders->findForCustomerLookup($orderId, $userId);
        if (!$order) {
            return null;
        }
        $order['items'] = $this->orderItems->listByOrderOrdered($orderId);

        return $order;
    }

    /** Chi tiết đơn của khách kèm sản phẩm (cờ is_reviewed) và lịch sử trạng thái; null nếu không có. */
    public function customerOrderDetail(int $orderId, int $userId): ?array
    {
        $order = $this->orders->findDetailForCustomer($orderId, $userId);
        if (!$order) {
            return null;
        }
        $order['items'] = $this->orderItems->listByOrderWithReviewFlagOrdered($orderId, $userId);
        $order['status_history'] = $this->history->listForOrder($orderId);

        return $order;
    }

    /**
     * Danh sách "Đơn hàng của tôi" (đã kiểm tra bộ lọc) kèm số đơn theo nhóm và phân trang.
     *
     * @return array{orders:array, counts:array<string,int>, pagination:array}
     */
    public function customerOrders(int $userId, string $statusFilter, string $timeFilter, string $search, int $page, int $pageSize): array
    {
        (new HetHanDonHangService($this->db))->ensureSchema();

        $counts = [
            'all' => 0,
            'processing' => 0,
            'shipping' => 0,
            'delivered' => 0,
            'cancelled' => 0,
            'payment_pending' => 0,
        ];
        $statusToGroup = [];
        foreach (self::STATUS_GROUPS as $group => $statuses) {
            foreach ($statuses as $status) {
                $statusToGroup[$status] = $group;
            }
        }
        foreach ($this->orders->countByStatusForCustomerList($userId, $timeFilter, $search) as $countRow) {
            $statusCount = (int) $countRow['total'];
            $counts['all'] += $statusCount;
            $counts['payment_pending'] += (int) $countRow['payment_pending'];
            $group = $statusToGroup[$countRow['status']] ?? null;
            if ($group !== null) {
                $counts[$group] += $statusCount;
            }
        }

        $filter = [];
        if ($statusFilter === 'payment_pending') {
            $filter = 'payment_pending';
        } elseif ($statusFilter !== 'all') {
            $filter = self::STATUS_GROUPS[$statusFilter];
        }

        $total = $this->orders->countForCustomerList($userId, $timeFilter, $search, $filter);
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $orders = [];
        foreach ($this->orders->findPageForCustomerList($userId, $timeFilter, $search, $filter, $pageSize, $offset) as $row) {
            $row['items'] = [];
            $orders[(int) $row['id']] = $row;
        }
        foreach ($this->orderItems->listByOrdersWithReviewFlag(array_keys($orders), $userId) as $item) {
            $orders[(int) $item['order_id']]['items'][] = $item;
        }

        return [
            'orders' => array_values($orders),
            'counts' => $counts,
            'pagination' => [
                'page' => $page,
                'per_page' => $pageSize,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }
}
