<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_goal_targets')) {
            return;
        }

        Schema::table('learning_goal_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('learning_goal_targets', 'quest_type')) {
                $table->string('quest_type', 50)->nullable()->after('period_type');
            }

            if (! Schema::hasColumn('learning_goal_targets', 'title')) {
                $table->string('title')->nullable()->after('quest_type');
            }

            if (! Schema::hasColumn('learning_goal_targets', 'target_value')) {
                $table->unsignedInteger('target_value')->default(0)->after('title');
            }

            if (! Schema::hasColumn('learning_goal_targets', 'current_value')) {
                $table->unsignedInteger('current_value')->default(0)->after('target_value');
            }

            if (! Schema::hasColumn('learning_goal_targets', 'reward_points')) {
                $table->unsignedInteger('reward_points')->default(10)->after('current_value');
            }

            if (! Schema::hasColumn('learning_goal_targets', 'status')) {
                $table->enum('status', ['pending', 'completed', 'failed'])->default('pending')->after('reward_points');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('learning_goal_targets')) {
            return;
        }

        Schema::table('learning_goal_targets', function (Blueprint $table) {
            foreach (['status', 'reward_points', 'current_value', 'target_value', 'title', 'quest_type'] as $column) {
                if (Schema::hasColumn('learning_goal_targets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
