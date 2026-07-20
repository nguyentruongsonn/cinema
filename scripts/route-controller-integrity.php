<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__) . '/vendor/autoload.php';

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$errors = [];
$checked = 0;

foreach ($app['router']->getRoutes() as $route) {
    $action = $route->getActionName();
    if ($action === 'Closure') {
        continue;
    }

    [$controller, $method] = str_contains($action, '@')
        ? explode('@', $action, 2)
        : [$action, '__invoke'];

    $checked++;
    if (!class_exists($controller)) {
        $errors[] = "{$route->uri()}: controller {$controller} does not exist";
        continue;
    }

    if (!method_exists($controller, $method)) {
        $errors[] = "{$route->uri()}: {$controller}@{$method} does not exist";
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Route-controller integrity failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

fwrite(STDOUT, "Route-controller integrity passed: {$checked} controller actions.\n");
