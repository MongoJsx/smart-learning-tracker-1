<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_goal_targets')) {
            Schema::create('learning_goal_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('period_type', ['daily', 'weekly', 'monthly']);
                $table->date('period_start');
                $table->date('period_end');
                $table->unsignedInteger('target_sessions')->default(0);
                $table->unsignedInteger('target_minutes')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'period_type', 'period_start']);
                $table->index(['user_id', 'period_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_goal_targets');
    }
};
