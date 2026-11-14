<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan "MVP = main lagi" (per streamer): pemain yang ditandai MVP pada sebuah game tidak berkurang sisa
 * game-nya. Match tetap dicatat (is_mvp) sebagai bukti di rekap, tetapi tidak memotong jatah dan tidak
 * dihitung ke batas game per sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('mvp_free_game')->default(false);
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->boolean('is_mvp')->default(false)->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('mvp_free_game');
        });

        Schema::table('matches', function (Blueprint $table): void {
            $table->dropColumn('is_mvp');
        });
    }
};
