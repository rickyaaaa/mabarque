<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak setiap webhook donasi yang lolos verifikasi kredensial (audit + proses ulang).
 * Kejadian 2026-11-28: payload SociaBuzz MYR 0,25 ditolak 422 tanpa tersimpan, lalu SociaBuzz berhenti
 * mengirim — tidak ada jejak donasi sama sekali. Tabel ini memastikan kiriman yang gagal tetap terlihat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('platform_connection_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            // pending | donation | duplicate | test | ignored | unreadable | failed
            $table->string('result', 20)->default('pending');
            $table->text('error')->nullable();
            $table->json('payload')->nullable(); // headers + payload, kredensial disamarkan
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('replayed_at')->nullable();
            // Diisi saat kiriman gagal sudah ditangani (diproses ulang atau dimasukkan manual oleh streamer).
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['platform_connection_id', 'result', 'resolved_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
