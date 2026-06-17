<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('profile_image')->nullable();
            $table->string('theme_color', 20)->default('#2563eb');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('portfolio_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('portfolios')->cascadeOnDelete();
            $table->string('project_name');
            $table->text('project_description')->nullable();
            $table->string('project_image')->nullable();
            $table->string('project_url')->nullable();
            $table->string('github_url')->nullable();
            $table->text('technologies')->nullable();
            $table->string('project_type', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('portfolio_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('portfolios')->cascadeOnDelete();
            $table->string('skill_name', 100);
            $table->string('skill_level', 50)->default('beginner');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_skills');
        Schema::dropIfExists('portfolio_projects');
        Schema::dropIfExists('portfolios');
    }
};
