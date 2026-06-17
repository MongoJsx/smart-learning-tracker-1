<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'education_level')) {
                $table->string('education_level')->nullable()->after('profile_pic');
            }

            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('provider_id');
            }

            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('email_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $drops = [];

            foreach (['remember_token', 'email_verified_at', 'education_level'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $drops[] = $column;
                }
            }

            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
