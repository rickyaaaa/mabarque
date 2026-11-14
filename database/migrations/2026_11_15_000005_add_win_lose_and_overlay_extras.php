<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skor Win/Lose per sesi live (dihitung per game party, bukan per pemain), hasil tiap match untuk rekap,
 * dan opsi overlay untuk menampilkan skor serta harga paket VIP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('wins')->default(0);
            $table->unsignedInteger('losses')->default(0);
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->string('result', 4)->nullable()->after('note');
        });

        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->boolean('show_score')->default(true);
            $table->boolean('show_packages')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('live_sessions', function (Blueprint $table): void {
            $table->dropColumn(['wins', 'losses']);
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->dropColumn('result');
        });

        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->dropColumn(['show_score', 'show_packages']);
        });
    }
};
