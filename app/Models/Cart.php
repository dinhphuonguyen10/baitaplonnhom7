<?php

namespace App\Models;

/**
 * Model Cart - giỏ hàng (bảng `carts`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Cart extends BaseModel
{
    protected string $table = 'carts';

    // --- end of methods ---
}
