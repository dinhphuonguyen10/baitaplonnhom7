<?php

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Services\StorefrontLayoutService;

/**
 * Lớp trừu tượng BaseClientController - lớp cha cho các controller trang cửa hàng.
 * Khi render, tự bổ sung dữ liệu layout chung (header: tài khoản, menu danh mục,
 * số sản phẩm yêu thích, logo) để View không phải tự truy vấn.
 */
abstract class BaseClientController extends Controller
{
    private ?StorefrontLayoutService $layout = null;

    protected function layoutService(): StorefrontLayoutService
    {
        return $this->layout ??= new StorefrontLayoutService();
    }

    protected function shareLayoutData(array $data): array
    {
        return array_merge($data, $this->layoutService()->header($data['bodyClass'] ?? null));
    }
}
