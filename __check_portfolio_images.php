<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = Illuminate\Support\Facades\DB::table('portfolio_images')->orderByDesc('id')->limit(10)->get(['id','portfolio_id','image_name','image_path','image_type']);
foreach ($rows as $r) {
  echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), PHP_EOL;
}
