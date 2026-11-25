<?php

namespace App\Controllers\Client;

/**
 * Lớp OrderTrackingController - trang "Đơn hàng của tôi" (client/tracuudh.php).
 * Danh sách đơn được tải bằng JavaScript qua api/orders/my_orders.php; trang chỉ cần dữ liệu layout.
 */
class OrderTrackingController extends BaseClientController
{
    protected function handle(): array
    {
        return [
            'pageTitle' => 'Đơn hàng của tôi - ÉCRIN JEWELRY',
            'bodyClass' => 'my-orders-page',
        ];
    }

    /** Action: hiển thị View client/order_tracking. */
    public function index(): void
    {
        $this->render('client/order_tracking', $this->handle());
    }
}
