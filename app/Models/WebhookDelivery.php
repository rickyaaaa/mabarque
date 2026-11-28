<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kiriman webhook donasi yang lolos verifikasi kredensial (lihat DonationWebhookService). */
class WebhookDelivery extends Model
{
    /** Hasil yang berarti donasi belum tercatat dan perlu ditangani streamer. */
    public const PROBLEMS = ['unreadable', 'failed'];

    protected $fillable = [
        'platform_connection_id',
        'provider',
        'http_status',
        'result',
        'error',
        'payload',
        'donation_id',
        'replayed_at',
        'resolved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'http_status' => 'integer',
        'replayed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function platformConnection(): BelongsTo
    {
        return $this->belongsTo(PlatformConnection::class);
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /** Kiriman gagal dibaca/diproses yang belum ditangani. */
    public function scopeUnresolvedProblems(Builder $query): Builder
    {
        return $query->whereIn('webhook_deliveries.result', self::PROBLEMS)->whereNull('webhook_deliveries.resolved_at');
    }

    /** Isi payload asli provider (tanpa header). */
    public function body(): array
    {
        return (array) ($this->payload['payload'] ?? []);
    }

    /** Ringkasan isi untuk streamer, mis. "Mednes · MYR 0.25 · ≈ Rp 1.035". */
    public function summary(): string
    {
        $body = $this->body();
        $name = $body['supporter'] ?? $body['donator_name'] ?? $body['supporter_name'] ?? null;
        $amount = $body['amount'] ?? $body['amount_raw'] ?? $body['price'] ?? null;
        $currency = strtoupper((string) ($body['currency'] ?? 'IDR'));
        $settled = $body['amount_settled'] ?? null;

        return collect([
            is_scalar($name) && trim((string) $name) !== '' ? (string) $name : 'Tanpa nama',
            is_scalar($amount) ? ($currency === 'IDR' ? 'Rp '.$amount : "{$currency} {$amount}") : null,
            $currency !== 'IDR' && is_numeric($settled) ? '≈ Rp '.number_format((float) $settled, 0, ',', '.') : null,
        ])->filter()->join(' · ');
    }
}
