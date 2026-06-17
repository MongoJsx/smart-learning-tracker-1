<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('study_environments')) {
            Schema::create('study_environments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description');
                $table->json('features'); // เงียบ, มีแสงสว่าง, มีโต๊ะทำงาน
                $table->string('noise_level'); // quiet, moderate, noisy
                $table->boolean('has_wifi')->default(false);
                $table->string('location')->nullable();
                $table->decimal('rating', 3, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_environment_preferences')) {
            Schema::create('user_environment_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->string('preferred_noise_level');
                $table->json('required_features');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_environment_preferences');
        Schema::dropIfExists('study_environments');
    }
};
