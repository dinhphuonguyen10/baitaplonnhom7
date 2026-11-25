<?php

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DonHangService;
use DomainException;
use Throwable;

/**
 * Lớp DonHangController - các API đơn hàng của khách (api/orders/*.php, client/huydon.php).
 */
class DonHangController extends \App\Core\Controller
{
    private ?DonHangService $service = null;

    /** POST api/orders/dat_hang.php - đặt hàng từ giỏ hàng (JSON). */
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
            $order = $this->service()->placeOrder(
                $input,
                Session::get('role', '') === 'customer',
                Session::get('user_id'),
                Session::has('username') ? (string) Session::get('username') : null
            );
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
            'subtotal' => $order['subtotal'],
            'total_price' => $order['total_price'],
            'discount_amount' => $order['discount_amount'],
            'items' => $order['items'],
        ]);
    }

    /** POST api/orders/huy_don_hang.php (và client/huydon.php) - khách hủy đơn đang chờ xác nhận kèm lý do. */
    public function cancel(): void
    {
        Response::jsonHeaders(true);

        if (!$this->isCustomerSession()) {
            Response::error('Vui lòng đăng nhập để thực hiện thao tác này.', 401);
        }
        if (!Request::isPost()) {
            Response::error('Phương thức yêu cầu không hợp lệ.', 405);
        }

        $data = json_decode(Request::rawBody(), true);
        if (!is_array($data)) {
            Response::error('Dữ liệu yêu cầu không hợp lệ.', 400);
        }
        $orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
        $reason = trim((string) ($data['reason'] ?? ''));
        $csrfToken = (string) ($data['csrf_token'] ?? '');
        if ($orderId === false || $orderId < 1) {
            Response::error('Mã đơn hàng không hợp lệ.', 400);
        }
        if ($reason === '' || mb_strlen($reason, 'UTF-8') > 500) {
            Response::error('Vui lòng nhập lý do hủy, tối đa 500 ký tự.', 400);
        }
        if (!Session::verifyCsrf($csrfToken, DonHangService::CANCEL_CSRF_KEY)) {
            Response::error('Phiên thao tác không hợp lệ. Vui lòng tải lại trang.', 403);
        }

        $userId = (int) Session::get('user_id');
        $actorName = mb_substr((string) Session::get('username', 'Khách hàng'), 0, 100, 'UTF-8');

        try {
            $this->service()->cancelCustomerOrder($userId, (int) $orderId, $reason, $actorName);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 409);
        } catch (Throwable $e) {
            error_log('Order cancellation failed: ' . $e->getMessage());
            Response::error('Không thể hủy đơn hàng lúc này. Vui lòng thử lại sau.', 500);
        }

        Response::json(['status' => 'success', 'message' => 'Đã hủy đơn hàng thành công.']);
    }

    /** GET api/orders/tra_cuu_don_hang.php?query=... - tra cứu đơn theo mã đơn của khách đang đăng nhập. */
    public function lookup(): void
    {
        Response::jsonHeaders(true);
        if (!$this->isCustomerSession()) {
            Response::error('Vui lòng đăng nhập để tra cứu đơn hàng.', 401);
        }

        $query = Request::query('query');
        $query = is_string($query) ? trim($query) : '';
        $query = ltrim($query, '#');
        $orderId = filter_var($query, FILTER_VALIDATE_INT);
        if ($orderId === false || $orderId < 1) {
            Response::error('Vui lòng nhập mã đơn hàng hợp lệ.', 400);
        }

        try {
            $order = $this->service()->lookup((int) $orderId, (int) Session::get('user_id'));
        } catch (Throwable $e) {
            error_log('Order lookup failed: ' . $e->getMessage());
            Response::error('Không thể tra cứu đơn hàng lúc này.', 500);
        }
        if ($order === null) {
            Response::error('Không tìm thấy đơn hàng thuộc tài khoản của bạn.', 404);
        }

        Response::json(['status' => 'success', 'orders' => [$order]]);
    }

    /** GET api/orders/don_hang_cua_toi.php?status=&q=&time=&page=&per_page= - đơn hàng của khách đang đăng nhập. */
    public function myOrders(): void
    {
        Response::jsonHeaders(true);
        if (!$this->isCustomerSession()) {
            Response::error('Vui lòng đăng nhập để xem đơn hàng.', 401);
        }

        $userId = (int) Session::get('user_id');
        $statusFilter = Request::query('status');
        $statusFilter = is_string($statusFilter) ? trim($statusFilter) : 'all';
        if ($statusFilter !== 'all' && $statusFilter !== 'payment_pending'
            && !isset(DonHangService::STATUS_GROUPS[$statusFilter])) {
            Response::error('Bộ lọc trạng thái không hợp lệ.', 400);
        }

        $search = Request::query('q');
        $search = ltrim(is_string($search) ? trim($search) : '', '#');
        if ($search !== '' && (!ctype_digit($search) || strlen($search) > 10)) {
            Response::error('Mã đơn hàng chỉ gồm tối đa 10 chữ số.', 400);
        }

        $timeFilter = Request::query('time');
        $timeFilter = is_string($timeFilter) ? trim($timeFilter) : '30days';
        if (!in_array($timeFilter, ['30days', '6months', 'all'], true)) {
            $timeFilter = 'all';
        }

        $page = filter_var(Request::query('page', 1), FILTER_VALIDATE_INT);
        $pageSize = filter_var(Request::query('per_page', 5), FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > 1000000 || $pageSize === false || $pageSize < 1 || $pageSize > 20) {
            Response::error('Thông tin phân trang không hợp lệ.', 400);
        }

        try {
            $result = $this->service()->customerOrders($userId, $statusFilter, $timeFilter, $search, (int) $page, (int) $pageSize);
        } catch (Throwable $e) {
            error_log('Customer order list failed: ' . $e->getMessage());
            Response::error('Không thể tải danh sách đơn hàng lúc này.', 500);
        }

        Response::json([
            'status' => 'success',
            'orders' => $result['orders'],
            'counts' => $result['counts'],
            'pagination' => $result['pagination'],
        ]);
    }

    /** GET api/orders/chi_tiet_don_hang.php?order_id=... - chi tiết đơn kèm lịch sử trạng thái. */
    public function detail(): void
    {
        Response::jsonHeaders(true);
        if (!$this->isCustomerSession()) {
            Response::error('Vui lòng đăng nhập để xem đơn hàng.', 401);
        }
        if (Request::method() !== 'GET') {
            Response::error('Phương thức yêu cầu không hợp lệ.', 405);
        }

        $orderId = filter_var(Request::query('order_id'), FILTER_VALIDATE_INT);
        if ($orderId === false || $orderId < 1) {
            Response::error('Mã đơn hàng không hợp lệ.', 400);
        }

        try {
            $order = $this->service()->customerOrderDetail((int) $orderId, (int) Session::get('user_id'));
        } catch (Throwable $e) {
            error_log('Customer order detail failed: ' . $e->getMessage());
            Response::error('Không thể tải chi tiết đơn hàng lúc này.', 500);
        }
        if ($order === null) {
            Response::error('Không tìm thấy đơn hàng của bạn.', 404);
        }

        Response::json(['status' => 'success', 'order' => $order]);
    }

    /** Phiên khách hàng: role = customer và có user_id (như các API của main). */
    private function isCustomerSession(): bool
    {
        return Session::get('role', '') === 'customer' && Session::has('user_id');
    }

    private function service(): DonHangService
    {
        return $this->service ??= new DonHangService();
    }
}
