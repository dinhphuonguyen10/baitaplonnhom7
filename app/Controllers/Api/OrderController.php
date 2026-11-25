<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\OrderExpiryService;
use App\Services\OrderService;
use DomainException;
use Throwable;

/**
 * Lớp OrderController - các API đơn hàng của khách (api/orders/*.php, client/huydon.php).
 */
class OrderController extends \App\Core\Controller
{
    private ?OrderService $service = null;

    /** POST api/orders/place_order.php - đặt hàng từ giỏ hàng (JSON). */
    public function place(): void
    {
        Response::jsonHeaders();
        if (!Request::isPost()) {
            Response::error('Phương thức yêu cầu không hợp lệ.', 405);
        }

        try {
            $input = $this->service()->validatePlaceOrderInput(Request::json());
        } catch (DomainException $e) {
            Response::error($e->getMessage());
        }

        try {
            $order = $this->service()->placeOrder($input, Auth::userId());
        } catch (DomainException $e) {
            Response::error($e->getMessage());
        } catch (Throwable $e) {
            error_log('Order placement failed: ' . $e->getMessage());
            Response::error('Không thể xử lý đơn hàng lúc này. Vui lòng thử lại sau.', 500);
        }

        Response::json([
            'status' => 'success',
            'message' => 'Đặt hàng thành công!',
            'order_id' => $order['order_id'],
            'total_price' => $order['total_price'],
            'items' => $order['items'],
        ]);
    }

    /** POST api/orders/cancel_order.php (và client/huydon.php) - khách hủy đơn đang chờ xác nhận. */
    public function cancel(): void
    {
        Response::jsonHeaders();

        // Phải đăng nhập
        $userId = Auth::userId();
        if ($userId === null) {
            $this->legacyJson(['status' => 'error', 'message' => 'Vui lòng đăng nhập để thực hiện hành động này']);
        }
        if (Auth::isAdminAreaRole((string) Session::get('role', 'customer'))) {
            $this->legacyJson(['status' => 'error', 'message' => 'Chức năng này chỉ dành cho tài khoản khách hàng.'], 403);
        }

        $data = json_decode(Request::rawBody(), true);
        if (!$data || !isset($data['order_id'])) {
            $this->legacyJson(['status' => 'error', 'message' => 'Dữ liệu không hợp lệ']);
        }

        try {
            $this->service()->cancelCustomerOrder($userId, intval($data['order_id']));
        } catch (DomainException $e) {
            $this->legacyJson(['status' => 'error', 'message' => $e->getMessage()]);
        }

        $this->legacyJson(['status' => 'success', 'message' => 'Đã hủy đơn hàng thành công']);
    }

    /** GET api/orders/lookup_order.php?query=... - tra cứu đơn theo mã đơn hoặc số điện thoại. */
    public function lookup(): void
    {
        Response::jsonHeaders();
        $query = Request::string('query', '', 'query');
        if (empty($query)) {
            $this->legacyJson(['status' => 'error', 'message' => 'Vui lòng nhập mã đơn hàng hoặc số điện thoại']);
        }

        $orders = $this->service()->lookup($query);
        if (!empty($orders)) {
            $this->legacyJson(['status' => 'success', 'orders' => $orders]);
        }
        $this->legacyJson(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng nào phù hợp!']);
    }

    /** GET api/orders/my_orders.php?status=...&time=... - đơn hàng của khách đang đăng nhập. */
    public function myOrders(): void
    {
        Response::jsonHeaders();
        $userId = Auth::userId();
        if ($userId === null) {
            $this->legacyJson(['status' => 'error', 'message' => 'Vui lòng đăng nhập để xem đơn hàng'], 401);
        }

        try {
            (new OrderExpiryService())->expireOverdueOrders();
        } catch (Throwable $e) {
            error_log('Customer order VietQR expiration sweep failed: ' . $e->getMessage());
            $this->legacyJson(['status' => 'error', 'message' => 'Không thể tải đơn hàng lúc này.'], 500);
        }

        $statusFilter = Request::query('status') !== null ? trim((string) Request::query('status')) : '';
        $timeFilter = Request::query('time') !== null ? trim((string) Request::query('time')) : '30days';

        $result = $this->service()->customerOrders($userId, $statusFilter, $timeFilter);
        $this->legacyJson([
            'status' => 'success',
            'orders' => $result['orders'],
            'counts' => $result['counts'],
        ]);
    }

    private function service(): OrderService
    {
        return $this->service ??= new OrderService();
    }

    /** Các API cũ trả JSON không kèm JSON_UNESCAPED_UNICODE - giữ nguyên định dạng. */
    private function legacyJson(array $data, int $status = 200): void
    {
        Response::json($data, $status, 0);
    }
}
