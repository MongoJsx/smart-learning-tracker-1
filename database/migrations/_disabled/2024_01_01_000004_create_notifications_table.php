<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('study_notifications')) {
            Schema::create('study_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('subject_id')->nullable()->constrained()->onDelete('cascade');
                $table->string('type'); // class_reminder, assignment, test
                $table->string('title');
                $table->text('message');
                $table->dateTime('notify_at');
                $table->boolean('is_read')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('study_notifications');
    }
};
