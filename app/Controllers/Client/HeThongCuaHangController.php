<?php

namespace App\Controllers\Client;

use App\Models\CuaHang;

/**
 * Controller trang "Hệ thống cửa hàng" (client/htch.php): danh sách cửa hàng đang hoạt động.
 */
class HeThongCuaHangController extends BaseClientController
{
    private const EMPTY_STORE = ['id' => 0, 'name' => 'Chưa có cửa hàng', 'address' => '', 'phone' => '', 'hours' => ''];

    private CuaHang $stores;

    public function __construct(?CuaHang $stores = null)
    {
        $this->stores = $stores ?? new CuaHang();
    }

    protected function indexData(): array
    {
        $clientStores = $this->stores->findActiveForStorefront();

        return [
            'pageTitle' => 'Hệ thống cửa hàng - ÉCRIN JEWELRY',
            'bodyClass' => 'page-no-product-search',
            'clientStores' => $clientStores,
            'firstClientStore' => $clientStores[0] ?? self::EMPTY_STORE,
        ];
    }

    /** Action: hiển thị View client/stores. */
    public function index(): void
    {
        $this->render('client/he_thong_cua_hang', $this->indexData());
    }
}
