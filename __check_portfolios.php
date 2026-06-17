<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$rows = Illuminate\Support\Facades\DB::table('portfolios')->orderByDesc('id')->limit(5)->get(['id','user_id','cover_image','profile_image']);
foreach ($rows as $r) {
  echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), PHP_EOL;
}
