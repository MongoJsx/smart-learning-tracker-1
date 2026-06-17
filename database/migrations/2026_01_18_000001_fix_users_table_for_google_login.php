<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $needs = [
            'password' => !Schema::hasColumn('users', 'password'),
            'provider' => !Schema::hasColumn('users', 'provider'),
            'provider_id' => !Schema::hasColumn('users', 'provider_id'),
            'profile_pic' => !Schema::hasColumn('users', 'profile_pic'),
            'education_level' => !Schema::hasColumn('users', 'education_level'),
            'email_verified_at' => !Schema::hasColumn('users', 'email_verified_at'),
            'remember_token' => !Schema::hasColumn('users', 'remember_token'),
        ];

        if (in_array(true, $needs, true)) {
            Schema::table('users', function (Blueprint $table) use ($needs) {
                if ($needs['password']) {
                    $table->string('password')->nullable();
                }
                if ($needs['provider']) {
                    $table->string('provider')->nullable();
                }
                if ($needs['provider_id']) {
                    $table->string('provider_id')->nullable();
                }
                if ($needs['profile_pic']) {
                    $table->string('profile_pic')->nullable();
                }
                if ($needs['education_level']) {
                    $table->string('education_level')->nullable();
                }
                if ($needs['email_verified_at']) {
                    $table->timestamp('email_verified_at')->nullable();
                }
                if ($needs['remember_token']) {
                    $table->rememberToken();
                }
            });
        }

        if (DB::getDriverName() === 'mysql') {
            $idColumn = DB::selectOne("SHOW COLUMNS FROM `users` WHERE Field = 'id'");
            $extra = is_object($idColumn) && isset($idColumn->Extra) ? strtolower((string) $idColumn->Extra) : '';
            if ($idColumn && !str_contains($extra, 'auto_increment')) {
                DB::statement('ALTER TABLE `users` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        }
    }

    public function down(): void
    {
        // Intentional no-op: this migration aligns legacy schemas for login.
    }
};
