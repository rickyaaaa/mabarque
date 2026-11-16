<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Latar kartu overlay bisa diatur streamer: warna sendiri (null = putih bawaan) dan gambar
 * (disimpan di storage lokal, disajikan lewat /o/{token}/bg/{file}). `background_dim` = seberapa
 * tebal lapisan warna latar menutupi gambar (0–90%) supaya teks tetap terbaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->string('background_color', 7)->nullable();
            $table->string('background_image')->nullable();
            $table->unsignedTinyInteger('background_dim')->default(35);
        });
    }

    public function down(): void
    {
        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->dropColumn(['background_color', 'background_image', 'background_dim']);
        });
    }
};
