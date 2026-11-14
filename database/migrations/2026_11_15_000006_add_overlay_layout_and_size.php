<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bentuk & ukuran overlay diatur streamer (bukan diperbesar di OBS, yang membuat gambar pecah):
 * vertikal/horizontal, lebar kartu, dan tinggi kartu (null = otomatis mengikuti isi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->string('layout', 12)->default('vertical');
            $table->unsignedSmallInteger('card_width')->default(440);
            $table->unsignedSmallInteger('card_height')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->dropColumn(['layout', 'card_width', 'card_height']);
        });
    }
};
