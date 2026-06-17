<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('email_provider_accounts')) {
            Schema::create('email_provider_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 50);
                $table->string('auth_type', 32)->default('oauth');
                $table->string('provider_email')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->dateTime('token_expires_at')->nullable();
                $table->json('scopes')->nullable();
                $table->string('smtp_host')->nullable();
                $table->unsignedInteger('smtp_port')->nullable();
                $table->string('smtp_username')->nullable();
                $table->string('smtp_password')->nullable();
                $table->string('status', 32)->default('pending');
                $table->string('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'provider', 'auth_type']);
                $table->index(['provider', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_provider_accounts');
    }
};
