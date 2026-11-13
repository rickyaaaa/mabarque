<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('queue_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('live_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('match_number');
            $table->string('note')->nullable();
            $table->timestamp('played_at')->useCurrent();
            $table->timestamps();

            $table->unique(['queue_entry_id', 'match_number']);
            $table->index(['live_session_id', 'played_at']);
            $table->index('played_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
