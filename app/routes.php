<?php
/**
 * app/routes.php - Bảng định tuyến của ứng dụng (MVC).
 *
 * Khóa: đường dẫn file công khai (tính từ thư mục gốc dự án).
 * Giá trị: [Lớp Controller, tên action, (tham số cho action)].
 *
 * App\Core\Router đọc bảng này để gọi đúng Controller@action cho mỗi request.
 */

use App\Controllers\Admin;
use App\Controllers\Api;
use App\Controllers\Client;

return [
    // ───────────── Cửa hàng (Client) ─────────────
    '/index.php' => [Client\HomeController::class, 'index'],
    '/client/bong_tai.php' => [Client\CategoryPageController::class, 'fixed', ['Bông tai', 'bong-tai']],
    '/client/day_chuyen.php' => [Client\CategoryPageController::class, 'fixed', ['Dây chuyền', 'day-chuyen']],
    '/client/nhan.php' => [Client\CategoryPageController::class, 'fixed', ['Nhẫn', 'nhan']],
    '/client/vong_tay.php' => [Client\CategoryPageController::class, 'fixed', ['Vòng tay', 'vong-tay']],
    '/client/category.php' => [Client\CategoryPageController::class, 'bySlug'],
    '/client/product.php' => [Client\ProductPageController::class, 'index'],
    '/client/danhgia.php' => [Client\ProductReviewController::class, 'submit'],
    '/client/cart.php' => [Client\CartPageController::class, 'index'],
    '/client/checkout.php' => [Client\CheckoutController::class, 'index'],
    '/client/payment.php' => [Client\PaymentPageController::class, 'index'],
    '/client/tracuudh.php' => [Client\OrderTrackingController::class, 'index'],
    '/client/huydon.php' => [Api\OrderController::class, 'cancel'],
    '/client/diachi.php' => [Client\AddressBookController::class, 'index'],
    '/client/wishlist.php' => [Client\WishlistPageController::class, 'index'],
    '/client/chat.php' => [Client\ChatController::class, 'index'],
    '/client/htch.php' => [Client\StoreLocatorController::class, 'index'],
    '/client/thong-tin.php' => [Client\InfoPageController::class, 'index'],
    '/client/quen_mat_khau.php' => [Client\ForgotPasswordPageController::class, 'index'],
    '/client/categories.php' => [Admin\CategoryController::class, 'index'],

    // ───────────── Quản trị (Admin) ─────────────
    '/admin/login.php' => [Admin\LoginController::class, 'index'],
    '/admin/logout.php' => [Admin\LoginController::class, 'logout'],
    '/admin/index.php' => [Admin\DashboardController::class, 'index'],
    '/admin/dashboard.php' => [Admin\DashboardController::class, 'legacyDashboard'],
    '/admin/manager/index.php' => [Admin\DashboardController::class, 'managerHome'],
    '/admin/staff/index.php' => [Admin\DashboardController::class, 'staffHome'],
    '/admin/payments.php' => [Admin\LegacyRedirectController::class, 'payments'],
    '/admin/products.php' => [Admin\ProductController::class, 'index'],
    '/admin/categories.php' => [Admin\CategoryController::class, 'index'],
    '/admin/reviews.php' => [Admin\ReviewController::class, 'index'],
    '/admin/orders.php' => [Admin\OrderController::class, 'index'],
    '/admin/users.php' => [Admin\UserController::class, 'index'],
    '/admin/stores.php' => [Admin\StoreController::class, 'index'],
    '/admin/banners.php' => [Admin\BannerController::class, 'index'],
    '/admin/chat.php' => [Admin\ChatController::class, 'index'],
    '/admin/auto_responses.php' => [Admin\AutoResponseController::class, 'index'],
    '/admin/auto_response_edit.php' => [Admin\AutoResponseController::class, 'edit'],
    '/admin/reports.php' => [Admin\ReportController::class, 'index'],

    // ───────────── API (JSON) ─────────────
    '/api/auth/check_session.php' => [Api\AuthController::class, 'checkSession'],
    '/api/auth/login.php' => [Api\AuthController::class, 'login'],
    '/api/auth/logout.php' => [Api\AuthController::class, 'logout'],
    '/api/auth/register.php' => [Api\AuthController::class, 'register'],
    '/api/auth/reset_password.php' => [Api\AuthController::class, 'resetPassword'],
    '/api/catalog/get_products.php' => [Api\CatalogController::class, 'products'],
    '/api/catalog/wishlist.php' => [Api\WishlistController::class, 'handle'],
    '/api/chat/admin_chat.php' => [Api\ChatController::class, 'adminChat'],
    '/api/chat/customer_get_messages.php' => [Api\ChatController::class, 'customerMessages'],
    '/api/chat/customer_send_message.php' => [Api\ChatController::class, 'customerSend'],
    '/api/chat/download_attachment.php' => [Api\ChatController::class, 'downloadAttachment'],
    '/api/chat/upload_attachment.php' => [Api\ChatController::class, 'uploadAttachment'],
    '/api/orders/cancel_order.php' => [Api\OrderController::class, 'cancel'],
    '/api/orders/lookup_order.php' => [Api\OrderController::class, 'lookup'],
    '/api/orders/my_orders.php' => [Api\OrderController::class, 'myOrders'],
    '/api/orders/place_order.php' => [Api\OrderController::class, 'place'],
    '/api/payment/change_vietqr_method.php' => [Api\PaymentController::class, 'changeMethod'],
    '/api/payment/confirm_demo.php' => [Api\PaymentController::class, 'confirmDemo'],
    '/api/payment/refresh_vietqr.php' => [Api\PaymentController::class, 'refresh'],
    '/api/payment/report_vietqr.php' => [Api\PaymentController::class, 'report'],
];
