<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            if (!Schema::hasColumn('portfolios', 'full_name')) {
                $table->string('full_name')->nullable()->after('title');
            }
            if (!Schema::hasColumn('portfolios', 'nickname')) {
                $table->string('nickname', 100)->nullable()->after('full_name');
            }
            if (!Schema::hasColumn('portfolios', 'age')) {
                $table->unsignedSmallInteger('age')->nullable()->after('date_of_birth');
            }
            if (!Schema::hasColumn('portfolios', 'ethnicity')) {
                $table->string('ethnicity', 100)->nullable()->after('age');
            }
            if (!Schema::hasColumn('portfolios', 'nationality')) {
                $table->string('nationality', 100)->nullable()->after('ethnicity');
            }
            if (!Schema::hasColumn('portfolios', 'religion')) {
                $table->string('religion', 100)->nullable()->after('nationality');
            }
            if (!Schema::hasColumn('portfolios', 'family_history')) {
                $table->text('family_history')->nullable()->after('religion');
            }
            if (!Schema::hasColumn('portfolios', 'father_name')) {
                $table->string('father_name')->nullable()->after('family_history');
            }
            if (!Schema::hasColumn('portfolios', 'father_phone')) {
                $table->string('father_phone', 50)->nullable()->after('father_name');
            }
            if (!Schema::hasColumn('portfolios', 'mother_name')) {
                $table->string('mother_name')->nullable()->after('father_phone');
            }
            if (!Schema::hasColumn('portfolios', 'mother_phone')) {
                $table->string('mother_phone', 50)->nullable()->after('mother_name');
            }
            if (!Schema::hasColumn('portfolios', 'education_history')) {
                $table->text('education_history')->nullable()->after('mother_phone');
            }
            if (!Schema::hasColumn('portfolios', 'special_abilities')) {
                $table->text('special_abilities')->nullable()->after('education_history');
            }
            if (!Schema::hasColumn('portfolios', 'awards_summary')) {
                $table->text('awards_summary')->nullable()->after('special_abilities');
            }
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('portfolios', 'full_name') ? 'full_name' : null,
                Schema::hasColumn('portfolios', 'nickname') ? 'nickname' : null,
                Schema::hasColumn('portfolios', 'age') ? 'age' : null,
                Schema::hasColumn('portfolios', 'ethnicity') ? 'ethnicity' : null,
                Schema::hasColumn('portfolios', 'nationality') ? 'nationality' : null,
                Schema::hasColumn('portfolios', 'religion') ? 'religion' : null,
                Schema::hasColumn('portfolios', 'family_history') ? 'family_history' : null,
                Schema::hasColumn('portfolios', 'father_name') ? 'father_name' : null,
                Schema::hasColumn('portfolios', 'father_phone') ? 'father_phone' : null,
                Schema::hasColumn('portfolios', 'mother_name') ? 'mother_name' : null,
                Schema::hasColumn('portfolios', 'mother_phone') ? 'mother_phone' : null,
                Schema::hasColumn('portfolios', 'education_history') ? 'education_history' : null,
                Schema::hasColumn('portfolios', 'special_abilities') ? 'special_abilities' : null,
                Schema::hasColumn('portfolios', 'awards_summary') ? 'awards_summary' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
