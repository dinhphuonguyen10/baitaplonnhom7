<?php
// Điểm vào: định tuyến tới Controller qua App\Core\Router (xem app/routes.php)
require_once __DIR__ . '/../app/bootstrap.php';
App\Core\Router::dispatch();
