<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password')->nullable();
                $table->string('profile_pic')->nullable();
                $table->string('education_level')->nullable();
                $table->string('provider')->nullable();
                $table->string('provider_id')->nullable();
                $table->string('google_id')->nullable();
                $table->enum('role', ['admin', 'user'])->default('user');
                $table->rememberToken();
                $table->timestamps();
            });
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'google_id')) {
                if (Schema::hasColumn('users', 'provider_id')) {
                    $table->string('google_id')->nullable()->after('provider_id');
                } else {
                    $table->string('google_id')->nullable();
                }
            }

            if (!Schema::hasColumn('users', 'role')) {
                if (Schema::hasColumn('users', 'education_level')) {
                    $table->enum('role', ['admin', 'user'])->default('user')->after('education_level');
                } else {
                    $table->enum('role', ['admin', 'user'])->default('user');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) return;

        Schema::dropIfExists('users');
    }
};
