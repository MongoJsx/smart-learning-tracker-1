<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_email_logs')) {
            Schema::create('notification_email_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('learning_notification_id')->nullable()->constrained('learning_notifications')->nullOnDelete();
                $table->string('to_email');
                $table->string('subject');
                $table->enum('provider', ['gmail_smtp', 'gmail_api'])->default('gmail_smtp');
                $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
                $table->dateTime('sent_at')->nullable();
                $table->text('error_message')->nullable();
                $table->string('message_id')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('learning_notification_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_logs');
    }
};
