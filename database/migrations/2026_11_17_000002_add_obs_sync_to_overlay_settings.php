<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sinkron dengan OBS: overlay (browser source) menerima event "mulai/berhenti streaming" dari OBS
 * dan memberi tahu server, supaya streamer tidak perlu klik mulai dua kali.
 * - obs_auto_start: sesi live dimulai otomatis saat OBS mulai streaming (bawaan aktif).
 * - obs_auto_end: sesi diakhiri otomatis saat OBS berhenti streaming (bawaan mati).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->boolean('obs_auto_start')->default(true);
            $table->boolean('obs_auto_end')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->dropColumn(['obs_auto_start', 'obs_auto_end']);
        });
    }
};
