<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('lessons')) {
            Schema::create('lessons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('content');
                $table->text('summary')->nullable();
                $table->integer('order')->default(0);
                $table->string('video_url')->nullable();
                $table->json('audio_notes')->nullable(); // จุดเสียงทับบันทึก
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
