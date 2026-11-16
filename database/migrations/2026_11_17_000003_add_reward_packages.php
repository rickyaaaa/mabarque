<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket "Reward lain" (mis. Follback IG 600rb): harga → hadiah berupa keterangan bebas, tanpa jumlah game,
 * tidak pernah masuk antrean mabar. Donasi yang mendapat reward berstatus `reward` dan ditandai selesai
 * oleh streamer (`reward_done_at`). Kolom status/type diubah dari enum ke string agar status baru muat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vip_packages', function (Blueprint $table): void {
            $table->string('kind', 10)->default('mabar')->after('name');
            $table->string('reward_label', 80)->nullable()->after('kind');
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->string('status', 20)->default('received')->change();
            $table->timestamp('reward_done_at')->nullable()->after('processed_at');
        });

        Schema::table('donation_notifications', function (Blueprint $table): void {
            $table->string('type', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vip_packages', function (Blueprint $table): void {
            $table->dropColumn(['kind', 'reward_label']);
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn('reward_done_at');
        });
    }
};
