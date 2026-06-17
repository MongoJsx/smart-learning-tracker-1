<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subjects')) {
            Schema::create('subjects', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->integer('credits')->default(3);
                $table->string('level')->nullable(); // ระดับความยาก
                $table->json('tags')->nullable(); // ป้ายกำกับ
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
