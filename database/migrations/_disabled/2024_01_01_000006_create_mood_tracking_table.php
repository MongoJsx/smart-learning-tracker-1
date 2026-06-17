<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mood_logs')) {
            Schema::create('mood_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('subject_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('mood'); // happy, neutral, sad, stressed, motivated
                $table->integer('energy_level')->default(5); // 1-10
                $table->integer('focus_level')->default(5); // 1-10
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};
