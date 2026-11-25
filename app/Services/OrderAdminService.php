<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\AdminOrderFormatter;
use App\Models\Order;
use RuntimeException;
use Throwable;

/**
 * Lớp OrderAdminService - nghiệp vụ quản lý đơn hàng & thanh toán ở trang quản trị:
 * cập nhật trạng thái, xác nhận thu COD / đối soát VietQR, tải danh sách và thống kê.
 */
class OrderAdminService
{
    private Database $db;

    private Order $orders;

    public function __construct(?Database $db = null, ?Order $orders = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->orders = $orders ?? new Order($this->db);
    }

    /**
     * Cập nhật trạng thái đơn hàng. Trả về thông báo hiển thị cho quản trị viên.
     */
    public function updateStatus(int $orderId, string $newStatus): string
    {
        if ($orderId < 1 || !in_array($newStatus, AdminOrderFormatter::STATUSES, true)) {
            return 'Trạng thái đơn hàng không hợp lệ.';
        }

        try {
            $this->orders->updateStatusForAdmin($orderId, $newStatus);

            return 'Đã cập nhật trạng thái đơn hàng.';
        } catch (Throwable $e) {
            return 'Không thể cập nhật trạng thái đơn hàng.';
        }
    }

    /**
     * Xác nhận thanh toán (COD hoặc BANK_QR). Trả về ['type' => success|error, 'message' => ...].
     *
     * @param int|false $orderId
     */
    public function confirmPayment($orderId, string $paymentMethod): array
    {
        if ($orderId === false || $orderId < 1) {
            return ['type' => 'error', 'message' => 'Mã đơn hàng không hợp lệ.'];
        }

        try {
            $affected = $this->orders->confirmPaymentForAdmin((int) $orderId, $paymentMethod);
        } catch (Throwable $e) {
            error_log('Order payment update failed: ' . $e->getMessage());

            return ['type' => 'error', 'message' => 'Không thể cập nhật trạng thái thanh toán.'];
        }

        if ($affected !== 1) {
            return ['type' => 'error', 'message' => 'Đơn hàng không còn ở trạng thái chờ xác nhận thanh toán.'];
        }

        return [
            'type' => 'success',
            'message' => $paymentMethod === 'COD'
                ? 'Đã xác nhận thu tiền COD.'
                : 'Đã xác nhận đã đối soát chuyển khoản VietQR.',
        ];
    }

    /**
     * Danh sách đơn hàng (key = id) kèm mảng items.
     *
     * @throws RuntimeException với thông báo hiển thị khi không tải được dữ liệu
     */
    public function listOrders(): array
    {
        try {
            $hasEmailColumn = $this->orders->hasEmailColumnForAdmin();
        } catch (Throwable $e) {
            throw new RuntimeException('Không thể kiểm tra dữ liệu đơn hàng.', 0, $e);
        }

        try {
            $rows = $this->orders->findAdminListRowsWithItems($hasEmailColumn);
        } catch (Throwable $e) {
            error_log('Admin order list failed: ' . $e->getMessage());
            throw new RuntimeException('Không thể tải danh sách đơn hàng.', 0, $e);
        }

        $orders = [];
        foreach ($rows as $row) {
            $orderId = (int) $row['id'];
            if (!isset($orders[$orderId])) {
                $orders[$orderId] = [
                    'id' => $orderId,
                    'customer' => $row['full_name'],
                    'phone' => $row['phone'],
                    'email' => $row['email'],
                    'address' => $row['address'],
                    'total' => (float) $row['total_price'],
                    'status' => $row['status'],
                    'payment_status' => $row['payment_status'],
                    'payment_method' => $row['payment_method'],
                    'date' => $row['created_at'],
                    'items' => [],
                ];
            }
            if ($row['product_name'] !== null) {
                $orders[$orderId]['items'][] = [
                    'name' => $row['product_name'],
                    'quantity' => (int) $row['quantity'],
                    'price' => (float) $row['price'],
                ];
            }
        }

        return $orders;
    }

    /** Thống kê số đơn theo trạng thái và tình trạng thu COD. */
    public function statistics(array $orders): array
    {
        $statCounts = [
            'pending' => 0, 'confirmed' => 0, 'preparing' => 0,
            'shipping' => 0, 'delivering' => 0, 'completed' => 0, 'cancelled' => 0,
            'cod_pending' => 0, 'cod_paid' => 0,
        ];
        $statusKeys = [
            'Chờ xác nhận' => 'pending',
            'Đã xác nhận' => 'confirmed',
            'Chuẩn bị đơn hàng' => 'preparing',
            'Đang vận chuyển' => 'shipping',
            'Đang giao' => 'delivering',
            'Đã giao hàng' => 'completed',
            'Đã hủy' => 'cancelled',
        ];

        foreach ($orders as $order) {
            $status = AdminOrderFormatter::normalize((string) $order['status']);
            if (isset($statusKeys[$status])) {
                $statCounts[$statusKeys[$status]]++;
            }
            if ($order['payment_method'] === 'COD') {
                if ($order['payment_status'] === 'Chưa thanh toán' && !AdminOrderFormatter::isCancelled($status)) {
                    $statCounts['cod_pending']++;
                } elseif ($order['payment_status'] === 'Đã thanh toán') {
                    $statCounts['cod_paid']++;
                }
            }
        }

        return $statCounts;
    }
}
