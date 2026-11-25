<?php

namespace App\Models;

/**
 * Model DonHang - đơn hàng (bảng `orders`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class DonHang extends BaseModel
{
    protected string $table = 'orders';

    // ───────────── Đơn hàng phía khách hàng (agentB) ─────────────

    /** Tra cứu đơn hàng theo mã đơn hoặc số điện thoại, mới nhất trước. */
    public function findByIdOrPhone(string $query): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM orders WHERE id = ? OR phone = ? ORDER BY created_at DESC',
            [$query, $query],
            'ss'
        );
    }

    /** Lấy chủ sở hữu và trạng thái của đơn (dùng khi khách hủy đơn). */
    public function findOwnerAndStatus(int $orderId): ?array
    {
        return $this->db->fetchOne('SELECT user_id, status FROM orders WHERE id = ?', [$orderId], 'i');
    }

    /** Đổi trạng thái đơn nếu trạng thái hiện tại chưa bị thay đổi. Trả về số dòng bị ảnh hưởng. */
    public function updateStatusIfUnchanged(int $orderId, string $newStatus, ?string $currentStatus): int
    {
        return $this->db->execute(
            'UPDATE orders SET status = ? WHERE id = ? AND status = ?',
            [$newStatus, $orderId, $currentStatus],
            'sis'
        );
    }

    /**
     * Danh sách đơn của khách theo khoảng thời gian (SQL Function fn_order_time_condition)
     * và (tuỳ chọn) danh sách trạng thái.
     */
    public function findForCustomer(int $userId, string $timeFilter, array $statuses = []): array
    {
        $sql = 'SELECT * FROM orders WHERE user_id = ? AND fn_order_time_condition(created_at, ?) = 1';
        $types = 'is';
        $params = [$userId, $timeFilter];
        if ($statuses !== []) {
            $sql .= ' AND status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
            $types .= str_repeat('s', count($statuses));
            $params = array_merge($params, array_values($statuses));
        }
        $sql .= ' ORDER BY created_at DESC';

        return $this->db->fetchAll($sql, $params, $types);
    }

    /** Đếm số đơn của khách theo từng trạng thái trong khoảng thời gian. */
    public function countByStatusForCustomer(int $userId, string $timeFilter): array
    {
        return $this->db->fetchAll(
            'SELECT status, COUNT(*) as cnt FROM orders WHERE user_id = ? AND fn_order_time_condition(created_at, ?) = 1 GROUP BY status',
            [$userId, $timeFilter],
            'is'
        );
    }

    /** Tạo đơn hàng mới (user_id/address_id có thể NULL), trả về ID đơn. */
    public function createCustomerOrder(
        ?int $userId,
        ?int $addressId,
        string $fullName,
        string $phone,
        string $email,
        string $address,
        float $totalPrice,
        string $paymentMethod
    ): int {
        return $this->db->insert(
            'INSERT INTO orders (user_id, address_id, full_name, phone, email, address, total_price, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $addressId, $fullName, $phone, $email, $address, $totalPrice, $paymentMethod],
            'iissssds'
        );
    }

    // ───────────── Thanh toán VietQR (agentB) ─────────────

    /** Thông tin đơn QR của khách cho trang thanh toán / làm mới mã QR. */
    public function findQrPaymentForCustomer(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, total_price, payment_method,
                    payment_status, status, created_at, payment_deadline_at,
                    UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
             FROM orders
             WHERE id = ? AND user_id = ?
             LIMIT 1',
            [$orderId, $userId],
            'ii'
        );
    }

    /** Đơn của khách cho trang thanh toán / biên nhận (kèm thông tin nhận hàng và cờ hết hạn giữ hàng). */
    public function findForPaymentPage(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, full_name, phone, email, address, total_price, payment_method,
                    payment_status, status, created_at,
                    payment_deadline_at,
                    UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline,
                    (expires_at IS NOT NULL AND expires_at <= NOW()) AS is_expired
             FROM orders
             WHERE id = ? AND user_id = ?
             LIMIT 1',
            [$orderId, $userId],
            'ii'
        );
    }

    /** Khóa đơn để đổi phương thức thanh toán (kèm cờ đã quá hạn). */
    public function lockQrOrderForMethodChange(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT payment_method, payment_status, status, payment_deadline_at,
                    payment_deadline_at <= NOW() AS deadline_passed
             FROM orders
             WHERE id = ? AND user_id = ?
             FOR UPDATE',
            [$orderId, $userId],
            'ii'
        );
    }

    /** Chuyển đơn VietQR còn hạn sang thanh toán khi nhận hàng. Trả về số dòng bị ảnh hưởng. */
    public function switchQrOrderToCod(int $orderId, int $userId): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET payment_method = 'COD',
                 payment_status = 'Chưa thanh toán',
                 payment_deadline_at = NULL,
                 expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
             WHERE id = ? AND user_id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status = 'Thanh toán thất bại'
               AND status IN ('Chờ xác nhận', 'Pending')
               AND payment_deadline_at > NOW()",
            [$orderId, $userId],
            'ii'
        );
    }

    /** Khóa đơn để ghi nhận kết quả thanh toán VietQR do khách báo. */
    public function lockQrOrderForPaymentReport(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, payment_method, payment_status, status, payment_deadline_at,
                    UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
             FROM orders
             WHERE id = ? AND user_id = ?
             FOR UPDATE',
            [$orderId, $userId],
            'ii'
        );
    }

    /** Ghi nhận thanh toán thất bại (mở hạn 30 phút nếu chưa có). */
    public function markQrPaymentFailed(int $orderId, int $userId, string $paymentStatus): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET payment_status = ?,
                 payment_deadline_at = COALESCE(
                     payment_deadline_at,
                     DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                 )
             WHERE id = ? AND user_id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status <> 'Đã thanh toán'
               AND status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')",
            [$paymentStatus, $orderId, $userId],
            'sii'
        );
    }

    /** Ghi nhận khách báo đã chuyển khoản (chờ cửa hàng xác nhận), xóa hạn thanh toán. */
    public function markQrPaymentAwaitingConfirmation(int $orderId, int $userId, string $paymentStatus): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET payment_status = ?,
                 payment_deadline_at = NULL
             WHERE id = ? AND user_id = ?
               AND payment_method = 'BANK_QR'
               AND payment_status <> 'Đã thanh toán'
               AND status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')
               AND (payment_deadline_at IS NULL OR payment_deadline_at > NOW())",
            [$paymentStatus, $orderId, $userId],
            'sii'
        );
    }

    /** Hạn thanh toán (UNIX timestamp) của đơn hoặc null. */
    public function paymentDeadlineTimestamp(int $orderId)
    {
        $row = $this->db->fetchOne(
            'SELECT UNIX_TIMESTAMP(payment_deadline_at) AS payment_deadline
             FROM orders
             WHERE id = ?',
            [$orderId],
            'i'
        );

        return $row['payment_deadline'] ?? null;
    }

    // ───────────── Quản trị đơn hàng (agentE) ─────────────

    /** Cập nhật trạng thái đơn hàng từ trang quản trị, trả về số dòng bị ảnh hưởng. */
    public function updateStatusForAdmin(int $orderId, string $status): int
    {
        return $this->db->execute('UPDATE orders SET status = ? WHERE id = ?', [$status, $orderId], 'si');
    }

    /**
     * Xác nhận đã thanh toán (COD đã thu tiền / VietQR đã đối soát).
     * Chỉ áp dụng cho đơn đúng phương thức, chưa thanh toán và chưa bị hủy. Trả về số dòng bị ảnh hưởng.
     */
    public function confirmPaymentForAdmin(int $orderId, string $paymentMethod): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET payment_status = 'Đã thanh toán'
             WHERE id = ?
               AND payment_method = ?
               AND payment_status <> 'Đã thanh toán'
               AND status NOT IN ('Đã hủy', 'Canceled', 'cancelled', 'huy')",
            [$orderId, $paymentMethod],
            'is'
        );
    }

    /**
     * Danh sách đơn hàng kèm từng dòng sản phẩm (LEFT JOIN order_items) cho trang quản trị,
     * mới nhất trước. Nếu bảng chưa có cột email thì trả về email rỗng.
     */
    public function findAdminListRowsWithItems(bool $hasEmailColumn): array
    {
        $emailField = $hasEmailColumn ? 'o.email' : "'' AS email";

        return $this->db->fetchAll(
            "SELECT o.id, o.full_name, o.phone, {$emailField}, o.address, o.total_price,
                    o.status, o.payment_status, o.payment_method, o.created_at,
                    oi.product_sku, oi.attributes_snapshot, oi.category_name_snapshot,
                    oi.product_name, oi.quantity,
                    oi.price, oi.original_price, oi.discount_amount, oi.line_total
             FROM orders o
             LEFT JOIN order_items oi ON oi.order_id = o.id
             ORDER BY o.created_at DESC, o.id DESC, oi.id ASC"
        );
    }

    /** Bảng orders đã có cột email chưa (SHOW COLUMNS không hỗ trợ tham số bind nên dùng truy vấn tĩnh). */
    public function hasEmailColumnForAdmin(): bool
    {
        return $this->db->fetchAll("SHOW COLUMNS FROM orders LIKE 'email'") !== [];
    }

    // ───────────── Đặt hàng giữ tồn kho / hủy / chi tiết (portOrders) ─────────────

    /**
     * Tạo đơn hàng giữ tồn kho (inventory_status = reserved) với hạn giữ hàng:
     * BANK_QR 30 phút, còn lại 24 giờ. Tiền truyền dạng chuỗi decimal. Trả về [số dòng thêm, ID đơn].
     *
     * @return array{0:int, 1:int}
     */
    public function createReservedCustomerOrder(
        ?int $userId,
        ?int $addressId,
        string $fullName,
        string $phone,
        string $email,
        string $address,
        string $totalPrice,
        string $discountAmount,
        string $paymentMethod
    ): array {
        $affected = $this->db->execute(
            "INSERT INTO orders
                (user_id, address_id, full_name, phone, email, address, total_price, shipping_fee, discount_amount,
                 payment_method, inventory_status, inventory_reserved_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, 'reserved', NOW(),
                     CASE WHEN ? = 'BANK_QR' THEN DATE_ADD(NOW(), INTERVAL 30 MINUTE)
                          ELSE DATE_ADD(NOW(), INTERVAL 24 HOUR) END)",
            [$userId, $addressId, $fullName, $phone, $email, $address, $totalPrice, $discountAmount, $paymentMethod, $paymentMethod],
            'iissssssss'
        );

        return [$affected, $this->db->lastInsertId()];
    }

    /** Tổng hợp số tiền đơn và chi tiết để kiểm tra tính nhất quán sau khi tạo đơn. */
    public function findAmountConsistency(int $orderId): ?array
    {
        return $this->db->fetchOne(
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
             GROUP BY o.id',
            [$orderId],
            'i'
        );
    }

    /** Khóa đơn của khách để hủy (trạng thái + tình trạng tồn kho). */
    public function lockForCustomerCancel(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, status, inventory_status
             FROM orders
             WHERE id = ? AND user_id = ?
             FOR UPDATE',
            [$orderId, $userId],
            'ii'
        );
    }

    /** Khách hủy đơn đang chờ xác nhận kèm lý do. Trả về số dòng bị ảnh hưởng. */
    public function cancelByCustomer(int $orderId, int $userId, string $reason): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET status = 'Đã hủy', cancelled_at = NOW(), cancellation_reason = ?
             WHERE id = ? AND user_id = ? AND status IN ('Chờ xác nhận', 'Pending')",
            [$reason, $orderId, $userId],
            'sii'
        );
    }

    /** Tra cứu một đơn theo mã đơn thuộc tài khoản khách. */
    public function findForCustomerLookup(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, full_name, phone, address, total_price, status, payment_status,
                    payment_method, created_at
             FROM orders
             WHERE id = ? AND user_id = ?
             LIMIT 1',
            [$orderId, $userId],
            'ii'
        );
    }

    /** Chi tiết một đơn của khách (api/orders/chi_tiet_don_hang.php). */
    public function findDetailForCustomer(int $orderId, int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, full_name, phone, email, address, total_price, shipping_fee,
                    discount_amount, status, payment_status, payment_method, created_at,
                    cancelled_at, cancellation_reason
             FROM orders
             WHERE id = ? AND user_id = ?
             LIMIT 1',
            [$orderId, $userId],
            'ii'
        );
    }

    /**
     * Điều kiện WHERE cho "Đơn hàng của tôi": theo khách, khoảng thời gian và (tuỳ chọn) mã đơn.
     *
     * @return array{0:array<int,string>, 1:string, 2:array}
     */
    private function customerListBaseWhere(int $userId, string $timeFilter, string $search): array
    {
        $where = ['o.user_id = ?'];
        $types = 'i';
        $params = [$userId];
        if ($timeFilter === '30days') {
            $where[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        } elseif ($timeFilter === '6months') {
            $where[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)';
        }
        if ($search !== '') {
            $where[] = 'o.id = ?';
            $types .= 's';
            $params[] = $search;
        }

        return [$where, $types, $params];
    }

    /** Số đơn theo trạng thái (kèm số đơn VietQR đang chờ thanh toán lại) cho "Đơn hàng của tôi". */
    public function countByStatusForCustomerList(int $userId, string $timeFilter, string $search): array
    {
        [$where, $types, $params] = $this->customerListBaseWhere($userId, $timeFilter, $search);

        return $this->db->fetchAll(
            "SELECT status, COUNT(*) AS total,
                    SUM(
                        CASE
                            WHEN payment_method = 'BANK_QR'
                             AND payment_status = 'Thanh toán thất bại'
                             AND payment_deadline_at > NOW()
                             AND status IN ('Chờ xác nhận', 'Pending')
                            THEN 1 ELSE 0
                        END
                    ) AS payment_pending
             FROM orders o
             WHERE " . implode(' AND ', $where) . '
             GROUP BY status',
            $params,
            $types
        );
    }

    /**
     * Điều kiện lọc trạng thái: 'payment_pending', danh sách trạng thái hoặc [] (tất cả).
     *
     * @param string|array $statusFilter
     * @return array{0:array<int,string>, 1:string, 2:array}
     */
    private function customerListWhere(int $userId, string $timeFilter, string $search, $statusFilter): array
    {
        [$where, $types, $params] = $this->customerListBaseWhere($userId, $timeFilter, $search);
        if ($statusFilter === 'payment_pending') {
            $where[] = "o.payment_method = 'BANK_QR'
            AND o.payment_status = 'Thanh toán thất bại'
            AND o.payment_deadline_at > NOW()
            AND o.status IN ('Chờ xác nhận', 'Pending')";
        } elseif (is_array($statusFilter) && $statusFilter !== []) {
            $where[] = 'o.status IN (' . implode(',', array_fill(0, count($statusFilter), '?')) . ')';
            $types .= str_repeat('s', count($statusFilter));
            array_push($params, ...array_values($statusFilter));
        }

        return [$where, $types, $params];
    }

    /** @param string|array $statusFilter xem customerListWhere() */
    public function countForCustomerList(int $userId, string $timeFilter, string $search, $statusFilter): int
    {
        [$where, $types, $params] = $this->customerListWhere($userId, $timeFilter, $search, $statusFilter);

        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) AS total FROM orders o WHERE ' . implode(' AND ', $where),
            $params,
            $types
        );
    }

    /** Một trang đơn hàng của khách, mới nhất trước. @param string|array $statusFilter */
    public function findPageForCustomerList(
        int $userId,
        string $timeFilter,
        string $search,
        $statusFilter,
        int $limit,
        int $offset
    ): array {
        [$where, $types, $params] = $this->customerListWhere($userId, $timeFilter, $search, $statusFilter);
        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll(
            'SELECT o.id, o.full_name, o.phone, o.email, o.address, o.total_price,
                    o.shipping_fee, o.discount_amount, o.status, o.payment_status,
                    o.payment_method, o.payment_deadline_at,
                    UNIX_TIMESTAMP(o.payment_deadline_at) AS payment_deadline,
                    UNIX_TIMESTAMP(o.expires_at) AS inventory_expires_at,
                    o.created_at, o.cancelled_at, o.cancellation_reason
             FROM orders o
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.created_at DESC, o.id DESC
             LIMIT ? OFFSET ?',
            $params,
            $types . 'ii'
        );
    }

    // ───────────── Quản trị: quy trình trạng thái (portOrders) ─────────────

    /** Khóa đơn để admin đổi trạng thái (kèm cờ đã hết hạn giữ tồn kho). */
    public function lockForAdminStatusChange(int $orderId): ?array
    {
        return $this->db->fetchOne(
            'SELECT status, inventory_status, payment_method, payment_status,
                    (expires_at IS NOT NULL AND expires_at <= NOW()) AS is_expired
             FROM orders
             WHERE id = ?
             FOR UPDATE',
            [$orderId],
            'i'
        );
    }

    /** Admin hủy đơn: ghi thời điểm và lý do mặc định nếu chưa có. Trả về số dòng bị ảnh hưởng. */
    public function cancelByAdmin(int $orderId, string $status): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET status = ?, cancelled_at = COALESCE(cancelled_at, NOW()),
                 cancellation_reason = COALESCE(NULLIF(cancellation_reason, ''), 'Hủy bởi quản trị viên')
             WHERE id = ?",
            [$status, $orderId],
            'si'
        );
    }

    /** Admin chuyển sang giao hàng: tồn kho đang giữ được chốt (committed). Trả về số dòng bị ảnh hưởng. */
    public function shipByAdmin(int $orderId, string $status): int
    {
        return $this->db->execute(
            "UPDATE orders
             SET status = ?,
                 inventory_status = CASE
                     WHEN inventory_status = 'reserved' THEN 'committed'
                     ELSE inventory_status
                 END
             WHERE id = ?",
            [$status, $orderId],
            'si'
        );
    }

    // --- end of methods ---
}
