<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan antrean per streamer: jumlah viewer dalam satu party (streamer + N, maks 4 — 5-stack MLBB)
 * dan batas game per pemain dalam satu sesi live (0 = tanpa batas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedTinyInteger('party_size')->default(4);
            $table->unsignedTinyInteger('session_match_cap')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['party_size', 'session_match_cap']);
        });
    }
};
