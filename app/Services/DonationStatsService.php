<?php

namespace App\Services;

use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Angka donasi untuk kartu ringkasan (Sesi live, Papan antrean, VIP & Donasi) — satu rumus untuk semua.
 *
 * Kejadian 2026-11-28: kartu "Nilai donasi Rp 47.434" berisi donasi yang Diabaikan + Perlu cek, padahal
 * belum ada yang masuk antrean, sehingga streamer mengira angkanya salah. Karena itu angka dipisah:
 * yang masuk antrean vs semua donasi (termasuk diabaikan & perlu cek). Refund tidak pernah dihitung.
 */
class DonationStatsService
{
    /** @return array{queued_count: int, queued_amount: int, all_count: int, all_amount: int} */
    public function forSession(LiveSession $session): array
    {
        return $this->summarize($session->donations());
    }

    /** Donasi yang diterima hari ini (dipakai saat streamer tidak sedang live). */
    public function forToday(User $user): array
    {
        return $this->summarize($user->donations()->whereDate('donations.received_at', today()));
    }

    private function summarize(Relation $donations): array
    {
        $row = $donations->toBase()
            ->where('donations.status', '!=', 'refunded')
            ->selectRaw("count(*) as all_count, coalesce(sum(donations.amount), 0) as all_amount,
                coalesce(sum(case when donations.status = 'queued' then 1 else 0 end), 0) as queued_count,
                coalesce(sum(case when donations.status = 'queued' then donations.amount else 0 end), 0) as queued_amount")
            ->first();

        return [
            'queued_count' => (int) ($row->queued_count ?? 0),
            'queued_amount' => (int) ($row->queued_amount ?? 0),
            'all_count' => (int) ($row->all_count ?? 0),
            'all_amount' => (int) ($row->all_amount ?? 0),
        ];
    }

    /** Keterangan kecil di bawah angka "Masuk antrean". */
    public static function caption(array $stats): string
    {
        return $stats['queued_count'].' donasi · semua donasi Rp '.number_format($stats['all_amount'], 0, ',', '.')
            .' ('.$stats['all_count'].', termasuk diabaikan & perlu cek)';
    }
}
