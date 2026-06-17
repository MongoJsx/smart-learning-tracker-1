<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE audio_summaries MODIFY transcript LONGTEXT NULL');
        DB::statement('ALTER TABLE audio_summaries MODIFY summary LONGTEXT NULL');
        DB::statement('ALTER TABLE audio_summaries MODIFY error_message TEXT NULL');

        DB::statement('ALTER TABLE audio_summaries_v2 MODIFY prompt TEXT NULL');
        DB::statement('ALTER TABLE audio_summaries_v2 MODIFY summary_text LONGTEXT NOT NULL');
        DB::statement('ALTER TABLE audio_summaries_v2 MODIFY error_message TEXT NULL');

        DB::statement('ALTER TABLE audio_transcription_segments MODIFY text TEXT NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE audio_transcription_segments MODIFY text VARCHAR(255) NOT NULL');

        DB::statement('ALTER TABLE audio_summaries_v2 MODIFY error_message VARCHAR(255) NULL');
        DB::statement('ALTER TABLE audio_summaries_v2 MODIFY summary_text VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE audio_summaries_v2 MODIFY prompt VARCHAR(255) NULL');

        DB::statement('ALTER TABLE audio_summaries MODIFY error_message VARCHAR(255) NULL');
        DB::statement('ALTER TABLE audio_summaries MODIFY summary VARCHAR(255) NULL');
        DB::statement('ALTER TABLE audio_summaries MODIFY transcript VARCHAR(255) NULL');
    }
};
