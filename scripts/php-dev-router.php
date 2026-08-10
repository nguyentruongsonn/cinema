<?php

declare(strict_types=1);

$publicPath = dirname(__DIR__).'/public';
$requestPath = urldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if ($requestPath !== '/' && is_file($publicPath.$requestPath)) {
    return false;
}

require $publicPath.'/index.php';
