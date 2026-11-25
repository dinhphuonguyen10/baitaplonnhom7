<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Response;
use App\Services\ProductCatalogService;

/**
 * Controller API danh mục sản phẩm (api/catalog/get_products.php).
 */
class CatalogController extends \App\Core\Controller
{
    private ProductCatalogService $catalog;

    public function __construct(?ProductCatalogService $catalog = null)
    {
        $this->catalog = $catalog ?? new ProductCatalogService();
    }

    /**
     * GET api/catalog/get_products.php
     *  ?ids=1,2 | ?category=home|featured|suggest|new|latest
     *  | ?category=&search=&suggest=&sort=&page=&min_price=&max_price=&in_stock=&has_discount=&has_gift=
     */
    public function products(): void
    {
        Response::jsonHeaders(true);

        $result = $this->catalog->handleProductsRequest($_GET, Auth::customerId());

        Response::json($result['body'], $result['status']);
    }
}
