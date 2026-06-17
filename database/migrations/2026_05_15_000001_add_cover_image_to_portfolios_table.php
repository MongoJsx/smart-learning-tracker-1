<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('portfolios', 'cover_image')) {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->string('cover_image', 255)->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('portfolios', 'cover_image')) {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->dropColumn('cover_image');
            });
        }
    }
};
