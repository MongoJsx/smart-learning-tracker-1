<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo config('database.default') . PHP_EOL;
echo config('database.connections.mysql.database') . PHP_EOL;
