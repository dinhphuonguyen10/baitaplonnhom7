<?php

namespace App\Models;

/**
 * Model ChiTietGioHang - sản phẩm trong giỏ hàng (bảng `cart_items`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class ChiTietGioHang extends BaseModel
{
    protected string $table = 'cart_items';

    // --- end of methods ---
}
