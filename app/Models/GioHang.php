<?php

namespace App\Models;

/**
 * Model GioHang - giỏ hàng (bảng `carts`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class GioHang extends BaseModel
{
    protected string $table = 'carts';

    // --- end of methods ---
}
