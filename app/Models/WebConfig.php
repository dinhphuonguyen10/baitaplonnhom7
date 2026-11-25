<?php

namespace App\Models;

/**
 * Model WebConfig - cấu hình website (bảng `web_config`).
 * Kế thừa CRUD cơ bản từ BaseModel: find, all, findBy, where, create, update, delete, count.
 */
class WebConfig extends BaseModel
{
    protected string $table = 'web_config';

    // --- end of methods ---
}
