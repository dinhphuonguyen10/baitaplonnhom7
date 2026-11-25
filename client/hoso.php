<?php

// Điểm vào tương thích; App\Core\Router chuyển URL cũ tới HoSoController.
require_once __DIR__ . '/../app/bootstrap.php';
App\Core\Router::dispatch();
