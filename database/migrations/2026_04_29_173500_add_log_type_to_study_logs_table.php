<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('study_logs')) {
            return;
        }

        if (! Schema::hasColumn('study_logs', 'log_type')) {
            Schema::table('study_logs', function (Blueprint $table) {
                $table->string('log_type', 50)->default('study')->after('mood');
                $table->index('log_type');
            });
        }

        DB::table('study_logs')
            ->where(function ($query) {
                $query
                    ->where('title', 'like', 'สรุปเอกสาร:%')
                    ->orWhere('note', 'like', '%อัปโหลดไฟล์เพื่อสรุปเอกสาร%');
            })
            ->update(['log_type' => 'document_summary']);

        DB::table('study_logs')
            ->where(function ($query) {
                $query
                    ->where('title', 'like', 'สรุปเสียง%')
                    ->orWhere('note', 'like', '%อัปโหลดเสียงเพื่อสรุป%')
                    ->orWhere('note', 'like', '%อัดเสียงสดเพื่อสรุป%');
            })
            ->update(['log_type' => 'audio_summary']);

        if (Schema::hasTable('study_calendar_events')) {
            DB::table('study_calendar_events')
                ->whereIn('study_log_id', function ($query) {
                    $query->select('id')
                        ->from('study_logs')
                        ->whereIn('log_type', ['document_summary', 'audio_summary']);
                })
                ->delete();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('study_logs') || ! Schema::hasColumn('study_logs', 'log_type')) {
            return;
        }

        Schema::table('study_logs', function (Blueprint $table) {
            $table->dropIndex(['log_type']);
            $table->dropColumn('log_type');
        });
    }
};
