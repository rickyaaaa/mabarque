<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vip_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('player_name');
            $table->enum('provider', ['manual', 'saweria', 'sociabuzz', 'trakteer'])->default('manual');
            $table->unsignedInteger('donation_amount')->default(0);
            $table->unsignedInteger('matches_total')->default(0);
            $table->unsignedInteger('matches_played')->default(0);
            $table->enum('status', ['ready', 'offline', 'playing', 'done', 'skipped'])->default('ready');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            $table->index(['live_session_id', 'status', 'position']);
            $table->index(['live_session_id', 'joined_at']);
            $table->index('provider');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
