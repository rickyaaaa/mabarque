<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overlay_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('accent_color', 7)->default('#D82A1C');
            $table->unsignedTinyInteger('font_scale')->default(100);
            $table->unsignedTinyInteger('max_players')->default(5);
            $table->boolean('show_now_playing')->default(true);
            $table->boolean('transparent_mode')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overlay_settings');
    }
};
