<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$failures = [];

foreach ($app['router']->getRoutes() as $route) {
    /** @var Route $route */
    $action = $route->getActionName();
    if ($action === 'Closure' || ! str_contains($action, '@')) continue;
    [$controller, $method] = explode('@', $action, 2);
    if (! class_exists($controller)) $failures[] = "Missing controller {$controller} for {$route->uri()}";
    elseif (! method_exists($controller, $method)) $failures[] = "Missing action {$action} for {$route->uri()}";
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Route/controller integrity passed.\n");
