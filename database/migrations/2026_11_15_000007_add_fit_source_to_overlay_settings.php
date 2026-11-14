<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overlay mengikuti ukuran Browser Source OBS (mengisi penuh), supaya streamer cukup mengatur ukuran
 * di satu tempat. Aktif secara default; "atur ukuran sendiri" memakai card_width/card_height.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->boolean('fit_source')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->dropColumn('fit_source');
        });
    }
};
