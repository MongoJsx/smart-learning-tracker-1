<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $rows = Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE 'personal_access_tokens'");
    echo 'tables=' . count($rows) . PHP_EOL;
    $rows2 = Illuminate\Support\Facades\DB::select('DESCRIBE personal_access_tokens');
    foreach ($rows2 as $r) {
        echo $r->Field . ' | ' . $r->Type . ' | ' . $r->Null . ' | ' . $r->Key . ' | ' . ($r->Default ?? 'NULL') . ' | ' . $r->Extra . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'ERR: ' . $e->getMessage() . PHP_EOL;
}
