<?php

namespace App\Services\Webhooks;

use Illuminate\Http\Request;

/** Format webhook per provider donasi: cara verifikasi keaslian + cara membaca field. */
interface ProviderAdapter
{
    /** Label kredensial di form koneksi, mis. "Stream key" (Saweria) atau "Webhook token" (Trakteer). */
    public function credentialLabel(): string;

    /** Benar bila request terbukti dikirim provider (memakai kredensial yang disimpan streamer). */
    public function verify(Request $request, array $payload, string $credential): bool;

    /**
     * Normalisasi payload. Null bila event bukan donasi (diabaikan dengan respons 2xx).
     *
     * @throws InvalidPayload payload donasi tidak lengkap
     */
    public function parse(array $payload): ?IncomingDonation;

    /** Nama header yang berisi kredensial/signature — nilainya disamarkan saat contoh payload disimpan. */
    public function secretHeaders(): array;
}
