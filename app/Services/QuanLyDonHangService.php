<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\DinhDangDonHangQuanTri;
use App\Models\DonHang;
use App\Models\LichSuTrangThaiDon;
use DomainException;
use RuntimeException;
use Throwable;

/**
 * Lớp QuanLyDonHangService - nghiệp vụ quản lý đơn hàng & thanh toán ở trang quản trị:
 * cập nhật trạng thái theo quy trình (QuyTrinhTrangThaiDon, giữ/hoàn tồn kho, lịch sử),
 * xác nhận thu COD / đối soát VietQR, tải danh sách và thống kê.
 */
class QuanLyDonHangService
{
    private Database $db;

    private DonHang $orders;

    public function __construct(?Database $db = null, ?DonHang $orders = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->orders = $orders ?? new DonHang($this->db);
    }

    /**
     * Cập nhật trạng thái đơn hàng theo các bước chuyển hợp lệ, trong một transaction:
     * hủy => hoàn tồn kho đang giữ; giao hàng => chốt tồn kho; ghi lịch sử trạng thái.
     * Trả về thông báo hiển thị cho quản trị viên.
     */
    public function updateStatus(int $orderId, string $newStatus, string $adminUsername = ''): string
    {
        if ($orderId < 1 || !in_array($newStatus, QuyTrinhTrangThaiDon::STATUSES, true)) {
            return 'Trạng thái đơn hàng không hợp lệ.';
        }

        try {
            $this->db->beginTransaction();
            $currentOrder = $this->orders->lockForAdminStatusChange($orderId);
            if (!$currentOrder) {
                throw new RuntimeException('Không tìm thấy đơn hàng.');
            }

            $currentStatus = (string) $currentOrder['status'];
            $normalizedCurrentStatus = QuyTrinhTrangThaiDon::normalize($currentStatus);
            if ($normalizedCurrentStatus === $newStatus) {
                $this->db->commit();

                return 'Đơn hàng đã ở trạng thái này.';
            }

            if (
                (int) $currentOrder['is_expired'] === 1
                && $currentOrder['inventory_status'] === 'reserved'
                && $normalizedCurrentStatus === 'Chờ xác nhận'
                && $newStatus !== 'Đã hủy'
            ) {
                throw new DomainException('Đơn hàng đã hết thời gian giữ tồn kho; không thể tiếp tục xử lý.');
            }
            if (
                $currentOrder['payment_method'] === 'BANK_QR'
                && $currentOrder['payment_status'] !== 'Đã thanh toán'
                && $newStatus !== 'Đã hủy'
            ) {
                throw new DomainException('Chỉ xử lý đơn thanh toán QR sau khi đã xác nhận thanh toán.');
            }
            if (!QuyTrinhTrangThaiDon::canTransition($currentStatus, $newStatus)) {
                throw new DomainException(
                    'Không thể chuyển trạng thái từ "' . $normalizedCurrentStatus . '" sang "' . $newStatus . '".'
                );
            }

            if ($newStatus === 'Đã hủy') {
                if ($currentOrder['inventory_status'] === 'reserved') {
                    if (!(new TonKhoDonHangService($this->db))->releaseReserved($orderId)) {
                        throw new RuntimeException('Không thể hoàn tồn kho cho đơn hàng.');
                    }
                } elseif ($currentOrder['inventory_status'] !== 'legacy') {
                    throw new DomainException('Tồn kho của đơn hàng đã được xử lý; không thể hủy trạng thái này.');
                }
                $affected = $this->orders->cancelByAdmin($orderId, $newStatus);
            } elseif ($newStatus === 'Đang giao hàng') {
                if ($currentOrder['inventory_status'] === 'released') {
                    throw new DomainException('Đơn hàng đã hoàn tồn kho và không thể chuyển sang giao hàng.');
                }
                $affected = $this->orders->shipByAdmin($orderId, $newStatus);
            } else {
                $affected = $this->orders->updateStatusForAdmin($orderId, $newStatus);
            }
            if ($affected !== 1) {
                throw new RuntimeException('Không thể cập nhật trạng thái đơn hàng.');
            }

            $actorName = mb_substr($adminUsername !== '' ? $adminUsername : 'Quản trị viên', 0, 100, 'UTF-8');
            $historyNote = $newStatus === 'Đã hủy'
                ? 'Đơn hàng được hủy bởi quản trị viên.'
                : 'Cập nhật trạng thái bởi quản trị viên.';
            (new LichSuTrangThaiDon($this->db))->log($orderId, $newStatus, $currentStatus, $historyNote, $actorName);

            $this->db->commit();

            return 'Đã cập nhật trạng thái đơn hàng.';
        } catch (DomainException $e) {
            $this->db->rollback();

            return $e->getMessage();
        } catch (Throwable $e) {
            $this->db->rollback();
            error_log('Admin order status update failed: ' . $e->getMessage());

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
     * Danh sách đơn hàng (key = id) kèm mảng items (snapshot SKU/danh mục/giá).
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
                    'sku' => $row['product_sku'],
                    'attributes' => $row['attributes_snapshot'],
                    'category' => $row['category_name_snapshot'],
                    'name' => $row['product_name'],
                    'quantity' => (int) $row['quantity'],
                    'price' => (float) $row['price'],
                    'original_price' => (float) $row['original_price'],
                    'discount_amount' => (float) $row['discount_amount'],
                    'line_total' => (float) $row['line_total'],
                ];
            }
        }

        return $orders;
    }

    /** Thống kê số đơn theo trạng thái (đã chuẩn hóa) và tình trạng thu COD. */
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
            'Đang chuẩn bị hàng' => 'preparing',
            'Đang giao hàng' => 'shipping',
            'Đã giao hàng' => 'completed',
            'Hoàn thành' => 'completed',
            'Đã hủy' => 'cancelled',
        ];

        foreach ($orders as $order) {
            $status = DinhDangDonHangQuanTri::normalize((string) $order['status']);
            if (isset($statusKeys[$status])) {
                $statCounts[$statusKeys[$status]]++;
            }
            if ($order['payment_method'] === 'COD') {
                if ($order['payment_status'] === 'Chưa thanh toán' && !DinhDangDonHangQuanTri::isCancelled($status)) {
                    $statCounts['cod_pending']++;
                } elseif ($order['payment_status'] === 'Đã thanh toán') {
                    $statCounts['cod_paid']++;
                }
            }
        }

        return $statCounts;
    }
}
