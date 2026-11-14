<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detail pemain antrean: ID MLBB (boleh kosong — viewer kadang menulisnya di komentar), jenis layanan
 * (mabar VIP / mainin akun), dan catatan pribadi streamer. Opsi sensor nama & ID untuk tampilan publik.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table): void {
            $table->string('mlbb_id', 40)->nullable()->after('player_name');
            $table->string('service_type', 20)->default('vip')->after('mlbb_id');
            $table->text('note')->nullable()->after('service_type');
        });

        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->boolean('censor_names')->default(false)->after('transparent_mode');
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table): void {
            $table->dropColumn(['mlbb_id', 'service_type', 'note']);
        });

        Schema::table('overlay_settings', function (Blueprint $table): void {
            $table->dropColumn('censor_names');
        });
    }
};
