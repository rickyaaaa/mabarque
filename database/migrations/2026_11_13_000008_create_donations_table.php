<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('live_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vip_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('queue_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id');
            $table->string('provider');
            $table->string('donor_name');
            $table->string('donor_email')->nullable();
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['received', 'review', 'queued', 'ignored', 'refunded'])->default('received');
            $table->json('payload')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['platform_connection_id', 'external_id']);
            $table->index(['live_session_id', 'status', 'received_at']);
            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};

