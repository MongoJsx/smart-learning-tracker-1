<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portfolio_interests')) {
            return;
        }

        Schema::create('portfolio_interests', function (Blueprint $table) {
            $table->id();
            $table->integer('portfolio_id');
            $table->string('interest_name', 150);
            $table->timestamps();
            $table->index('portfolio_id', 'idx_portfolio_interests_portfolio_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_interests');
    }
};
