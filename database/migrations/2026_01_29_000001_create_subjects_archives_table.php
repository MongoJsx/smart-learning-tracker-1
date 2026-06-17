<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subjects_archives')) {
            Schema::create('subjects_archives', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('original_subject_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('color', 20)->nullable();
                $table->unsignedInteger('target_hours')->nullable();
                $table->date('start_date')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->timestamp('archived_at')->nullable()->useCurrent();

                $table->index('user_id', 'idx_user_id');
                $table->index('original_subject_id', 'idx_original_subject_id');
                $table->index('archived_at', 'idx_archived_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subjects_archives')) {
            Schema::dropIfExists('subjects_archives');
        }
    }
};
