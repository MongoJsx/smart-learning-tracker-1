<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notification_email_settings')) {
            Schema::create('notification_email_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->boolean('email_enabled')->default(true);
                $table->string('email_address')->nullable();
                $table->enum('digest_type', ['daily', 'weekly'])->default('daily');
                $table->integer('days_ahead')->default(1);
                $table->time('send_time')->default('20:00:00');
                $table->string('timezone', 64)->default('Asia/Bangkok');
                $table->dateTime('last_sent_at')->nullable();
                $table->timestamps();

                $table->unique('user_id');
                $table->index(['email_enabled', 'send_time']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_settings');
    }
};
