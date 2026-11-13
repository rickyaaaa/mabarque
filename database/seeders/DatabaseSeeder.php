<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo untuk pengembangan LOKAL saja (php artisan db:seed).
 *
 * Akun (password lokal: password123):
 * - admin@mabarqueue.test  — Super Admin
 * - andra@mabarqueue.test  — streamer beta aktif (@andrarizky) + 3 paket VIP
 * - nadia@mabarqueue.test  — streamer baru, menunggu persetujuan (pending)
 *
 * Di produksi, buat Super Admin lewat: php artisan mabarqueue:create-admin <email>
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Seeder demo tidak boleh dijalankan di produksi.');
        }

        User::factory()->superAdmin()->create([
            'name' => 'Admin MabarQueue',
            'email' => 'admin@mabarqueue.test',
            'channel_name' => 'Admin MabarQueue',
            'slug' => 'sa-admin-local',
        ]);

        $andra = User::factory()->create([
            'name' => 'Andra Rizky',
            'email' => 'andra@mabarqueue.test',
            'channel_name' => 'Andra Rizky',
            'slug' => 'andrarizky',
            'tagline' => 'Mabar santai, push mythic bareng viewer',
            'main_role' => 'jungler',
            'live_schedule' => 'Rabu & Sabtu, 19:00 WIB',
            'avatar_initials' => 'AR',
        ]);
        $andra->overlaySetting()->create();
        $andra->vipPackages()->createMany([
            ['name' => 'VIP 1 Match', 'price' => 15000, 'match_count' => 1, 'sort_order' => 1],
            ['name' => 'VIP 3 Match', 'price' => 35000, 'match_count' => 3, 'sort_order' => 2],
            ['name' => 'VIP 5 Match', 'price' => 50000, 'match_count' => 5, 'sort_order' => 3],
        ]);

        $nadia = User::factory()->pending()->create([
            'name' => 'Nadia Gaming',
            'email' => 'nadia@mabarqueue.test',
            'channel_name' => 'Nadia Gaming',
            'slug' => 'nadia-gaming',
        ]);
        $nadia->overlaySetting()->create();
    }
}
