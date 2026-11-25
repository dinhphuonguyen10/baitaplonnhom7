<?php

namespace App\Models;

/**
 * Model CauHinhWeb - cấu hình website (bảng `web_config`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class CauHinhWeb extends BaseModel
{
    protected string $table = 'web_config';

    // --- end of methods ---
}
