<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Akun streamer & Super Admin. Dijalankan paling awal karena seluruh tabel domain
 * (live_sessions, vip_packages, platform_connections, overlay_settings, overlay_tokens,
 * donation_notifications) memiliki foreign key user_id ke tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['super_admin', 'streamer'])->default('streamer');
            $table->enum('beta_status', ['pending', 'active', 'inactive'])->default('pending');

            // Identitas publik — tampil di halaman antrean publik & overlay OBS.
            $table->string('channel_name', 40);
            $table->string('slug', 24)->unique();
            $table->string('tagline', 70)->nullable();
            $table->enum('main_role', ['jungler', 'roamer', 'mid', 'gold', 'exp', 'flex'])->nullable();
            $table->string('live_schedule', 32)->nullable();
            $table->string('avatar_initials', 2)->nullable();
            $table->string('profile_accent', 7)->default('#f8a917');

            $table->rememberToken();
            $table->timestamps();

            $table->index(['role', 'beta_status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
