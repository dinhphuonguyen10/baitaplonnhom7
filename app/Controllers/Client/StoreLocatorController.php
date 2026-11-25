<?php

namespace App\Controllers\Client;

use App\Models\Store;

/**
 * Controller trang "Hệ thống cửa hàng" (client/htch.php): danh sách cửa hàng đang hoạt động.
 */
class StoreLocatorController extends BaseClientController
{
    private const EMPTY_STORE = ['id' => 0, 'name' => 'Chưa có cửa hàng', 'address' => '', 'phone' => '', 'hours' => ''];

    private Store $stores;

    public function __construct(?Store $stores = null)
    {
        $this->stores = $stores ?? new Store();
    }

    protected function indexData(): array
    {
        $clientStores = $this->stores->findActiveForStorefront();

        return [
            'pageTitle' => 'Hệ thống cửa hàng - ÉCRIN JEWELRY',
            'clientStores' => $clientStores,
            'firstClientStore' => $clientStores[0] ?? self::EMPTY_STORE,
        ];
    }

    /** Action: hiển thị View client/stores. */
    public function index(): void
    {
        $this->render('client/stores', $this->indexData());
    }
}
