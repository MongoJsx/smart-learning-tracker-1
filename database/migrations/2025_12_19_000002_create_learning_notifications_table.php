<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_notifications')) {
            Schema::create('learning_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('study_log_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('calendar_event_id')->nullable()->constrained('study_calendar_events')->nullOnDelete();
                $table->string('title');
                $table->text('body')->nullable();
                $table->dateTime('notify_at');
                $table->dateTime('delivered_at')->nullable();
                $table->enum('channel', ['in_app', 'email', 'push', 'line'])->default('in_app');
                $table->enum('status', ['pending', 'sent', 'cancelled', 'failed'])->default('pending');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_notifications');
    }
};
