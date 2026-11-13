<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['saweria', 'sociabuzz', 'trakteer']);
            $table->string('label')->nullable();
            $table->text('webhook_url');
            $table->text('signing_secret')->nullable();
            $table->enum('status', ['pending', 'active', 'paused', 'error'])->default('pending'); // pending = belum pernah menerima webhook
            $table->timestamp('last_received_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error')->nullable();
            $table->json('last_payload')->nullable(); // contoh webhook terakhir (kredensial disamarkan) untuk mencocokkan format provider
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_connections');
    }
};

