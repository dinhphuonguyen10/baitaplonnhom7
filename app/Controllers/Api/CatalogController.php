<?php

namespace App\Controllers\Api;

use App\Core\Auth;
use App\Core\Request;
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

    /** GET api/catalog/get_products.php?category=&sort=&min_price=&max_price=&in_stock=&has_discount=&has_gift= */
    public function products(): void
    {
        Response::jsonHeaders(true);

        $filters = [
            'min_price' => Request::query('min_price') !== null ? (float) Request::query('min_price') : null,
            'max_price' => Request::query('max_price') !== null ? (float) Request::query('max_price') : null,
            'in_stock' => Request::query('in_stock') === '1',
            'has_discount' => Request::query('has_discount') === '1',
            'has_gift' => Request::query('has_gift') === '1',
        ];

        $products = $this->catalog->listProducts(
            Request::string('category', '', 'query'),
            Request::string('sort', '', 'query'),
            $filters,
            Auth::customerId()
        );

        Response::json($products);
    }
}
