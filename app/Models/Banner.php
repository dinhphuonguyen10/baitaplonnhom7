<?php

namespace App\Models;

/**
 * Model Banner - banner trang chủ (bảng `banners`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class Banner extends BaseModel
{
    protected string $table = 'banners';

    // --- end of methods ---
}
