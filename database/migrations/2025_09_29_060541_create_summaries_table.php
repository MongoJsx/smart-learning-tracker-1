<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('summaries')) {
            return;
        }

        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_log_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('ai_model')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
