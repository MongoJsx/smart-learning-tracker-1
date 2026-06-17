<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('learning_goal_targets')) {
            return;
        }

        Schema::table('learning_goal_targets', function (Blueprint $table) {
            if (!Schema::hasColumn('learning_goal_targets', 'subject_id')) {
                $table->foreignId('subject_id')->nullable()->after('user_id')->constrained('subjects')->nullOnDelete();
            }

            if (!Schema::hasColumn('learning_goal_targets', 'schedule_id') && Schema::hasTable('schedules')) {
                $table->foreignId('schedule_id')->nullable()->after('subject_id')->constrained('schedules')->nullOnDelete();
            }

            if (!Schema::hasColumn('learning_goal_targets', 'quest_type')) {
                $table->string('quest_type', 50)->default('quiz_mastery')->after('period_type');
            }

            if (!Schema::hasColumn('learning_goal_targets', 'title')) {
                $table->string('title', 255)->nullable()->after('quest_type');
            }

            if (!Schema::hasColumn('learning_goal_targets', 'target_value')) {
                $table->unsignedInteger('target_value')->default(0)->after('title');
            }

            if (!Schema::hasColumn('learning_goal_targets', 'current_value')) {
                $table->unsignedInteger('current_value')->default(0)->after('target_value');
            }

            if (!Schema::hasColumn('learning_goal_targets', 'reward_points')) {
                $table->unsignedInteger('reward_points')->default(10)->after('current_value');
            }

            if (!Schema::hasColumn('learning_goal_targets', 'status')) {
                $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->after('reward_points');
            }

            if (!Schema::hasColumn('learning_goal_targets', 'created_at')) {
                $table->timestamp('created_at')->nullable()->useCurrent();
            }

            if (!Schema::hasColumn('learning_goal_targets', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();
            }
        });

        try {
            DB::statement('ALTER TABLE `learning_goal_targets` MODIFY `period_start` DATETIME NOT NULL');
            DB::statement('ALTER TABLE `learning_goal_targets` MODIFY `period_end` DATETIME NOT NULL');
        } catch (\Throwable) {
            // Ignore; schema may already match or platform may differ.
        }

        try {
            DB::statement('ALTER TABLE `learning_goal_targets` DROP INDEX `learning_goal_targets_user_id_period_type_period_start_unique`');
        } catch (\Throwable) {
            // Ignore when the old unique index does not exist.
        }

        try {
            Schema::table('learning_goal_targets', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'period_type', 'period_start', 'subject_id', 'schedule_id'],
                    'learning_goal_targets_user_period_subject_schedule_unique'
                );
            });
        } catch (\Throwable) {
            // Ignore when the new unique index already exists.
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('learning_goal_targets')) {
            return;
        }

        try {
            Schema::table('learning_goal_targets', function (Blueprint $table) {
                $table->dropUnique('learning_goal_targets_user_period_subject_schedule_unique');
            });
        } catch (\Throwable) {
            // Ignore when index is missing.
        }

        try {
            Schema::table('learning_goal_targets', function (Blueprint $table) {
                $table->unique(
                    ['user_id', 'period_type', 'period_start'],
                    'learning_goal_targets_user_id_period_type_period_start_unique'
                );
            });
        } catch (\Throwable) {
            // Ignore when the old unique index already exists.
        }
    }
};
