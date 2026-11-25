<?php
// scripts/mo_ta_bang_khach_hang.php - In cấu trúc bảng users (chạy bằng CLI).
require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

foreach (Database::getInstance()->columns('users') as $row) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | NULL=' . $row['Null'] . ' | Default=' . $row['Default'] . "\n";
}
