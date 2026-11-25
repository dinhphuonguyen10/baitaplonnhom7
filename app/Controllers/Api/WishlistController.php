<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Services\WishlistService;
use InvalidArgumentException;

/**
 * Controller API sản phẩm yêu thích (api/catalog/wishlist.php).
 *  - GET  ?type=ids|items : danh sách ID / chi tiết sản phẩm yêu thích
 *  - POST action=toggle|remove|sync : thả tim, bỏ tim, đồng bộ từ localStorage
 */
class WishlistController extends \App\Core\Controller
{
    private WishlistService $wishlist;

    public function __construct(?WishlistService $wishlist = null)
    {
        $this->wishlist = $wishlist ?? new WishlistService();
    }

    public function handle(): void
    {
        Response::jsonHeaders(true);
        $userId = Auth::customerId() ?? 0;

        if (Request::method() === 'GET') {
            $this->show($userId);
        }

        if (Request::method() === 'POST') {
            $this->update($userId);
        }
    }

    private function show(int $userId): void
    {
        if ($userId <= 0) {
            Response::json([
                'status' => 'guest',
                'is_logged_in' => false,
                'ids' => [],
                'items' => [],
                'count' => 0,
            ]);
        }

        if (Request::query('type', 'ids') === 'items') {
            $items = $this->wishlist->items($userId);
            $ids = array_column($items, 'id');
            Response::json([
                'status' => 'success',
                'is_logged_in' => true,
                'items' => $items,
                'ids' => $ids,
                'count' => count($ids),
            ]);
        }

        $ids = $this->wishlist->ids($userId);
        Response::json([
            'status' => 'success',
            'is_logged_in' => true,
            'ids' => $ids,
            'count' => count($ids),
        ]);
    }

    private function update(int $userId): void
    {
        // Đọc input JSON hoặc form post
        $data = json_decode(Request::rawBody(), true);
        if (!is_array($data)) {
            $data = $_POST;
        }

        $action = trim((string) ($data['action'] ?? 'toggle'));
        $productId = (int) ($data['product_id'] ?? 0);

        // Nếu chưa đăng nhập: yêu cầu đăng nhập để lưu trữ vào tài khoản
        if ($userId <= 0) {
            Response::json([
                'status' => 'unauthenticated',
                'message' => 'Vui lòng đăng nhập để lưu sản phẩm yêu thích vào tài khoản!',
                'require_login' => true,
            ]);
        }

        if ($action === 'sync') {
            $ids = $this->wishlist->sync($userId, $data['sync_ids'] ?? []);
            Response::json([
                'status' => 'success',
                'message' => 'Đã đồng bộ sản phẩm yêu thích!',
                'ids' => $ids,
                'count' => count($ids),
            ]);
        }

        try {
            $productName = $this->wishlist->requireProductName($productId);
        } catch (InvalidArgumentException $e) {
            Response::json(['status' => 'error', 'message' => $e->getMessage()]);
        }

        if ($action === 'toggle' || $action === 'remove') {
            $result = $action === 'toggle'
                ? $this->wishlist->toggle($userId, $productId, $productName)
                : $this->wishlist->remove($userId, $productId, $productName);

            Response::json([
                'status' => 'success',
                'favorited' => $result['favorited'],
                'product_id' => $productId,
                'count' => $result['count'],
                'message' => $result['message'],
            ]);
        }
    }
}
