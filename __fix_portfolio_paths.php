<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Illuminate\Support\Facades\DB::table('portfolio_images')
  ->where('image_path', 'like', '/storage/%')
  ->update(['image_path' => Illuminate\Support\Facades\DB::raw("REPLACE(image_path, '/storage/', '/public/storage/')")]);

Illuminate\Support\Facades\DB::table('portfolios')
  ->whereNotNull('cover_image')
  ->where('cover_image', 'like', '/storage/%')
  ->update(['cover_image' => Illuminate\Support\Facades\DB::raw("REPLACE(cover_image, '/storage/', '/public/storage/')")]);

Illuminate\Support\Facades\DB::table('portfolios')
  ->whereNotNull('profile_image')
  ->where('profile_image', 'like', '/storage/%')
  ->update(['profile_image' => Illuminate\Support\Facades\DB::raw("REPLACE(profile_image, '/storage/', '/public/storage/')")]);

echo "done\n";
