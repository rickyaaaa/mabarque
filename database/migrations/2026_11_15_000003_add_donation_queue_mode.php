<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara donasi masuk antrean (per streamer):
 * - smart  : otomatis bila pesan donasi berisi ID MLBB atau kata "mabar"/"vip"; selain itu "Perlu cek"
 * - manual : semua donasi yang cukup nominalnya masuk "Perlu cek"
 * - auto   : semua donasi yang cukup nominalnya langsung masuk antrean
 * Pesan donasi disimpan supaya streamer bisa membacanya di kotak masuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('donation_queue_mode', 10)->default('smart');
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->text('message')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('donation_queue_mode');
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn('message');
        });
    }
};
