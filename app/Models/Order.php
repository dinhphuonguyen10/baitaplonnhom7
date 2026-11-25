<?php

namespace App\Models;

/**
 * Model Order - đơn hàng (bảng `orders`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Order extends BaseModel
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
                 payment_deadline_at = NULL
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
                    oi.product_name, oi.quantity, oi.price
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

    // --- end of methods ---
}
