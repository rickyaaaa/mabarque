<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pilihan manual streamer untuk "Next game": nomor urut pilihan = urutan masuk party. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table): void {
            $table->unsignedInteger('next_order')->nullable()->after('joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table): void {
            $table->dropColumn('next_order');
        });
    }
};
