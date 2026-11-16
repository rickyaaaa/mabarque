<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Donasi mata uang asing (mis. viewer Malaysia donasi 25 MYR lewat SociaBuzz) sebelumnya tercatat
 * "Rp 25". Sekarang `amount` selalu rupiah (untuk hitungan paket & total), sedangkan nominal dan mata
 * uang asli disimpan terpisah untuk ditampilkan. Donasi lama dibetulkan dari payload yang tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->string('currency', 3)->default('IDR')->after('amount');
            $table->decimal('original_amount', 14, 2)->nullable()->after('currency');
        });

        DB::table('donations')->where('provider', 'sociabuzz')->whereNotNull('payload')->orderBy('id')
            ->each(function (object $donation): void {
                $payload = json_decode((string) $donation->payload, true);
                $currency = strtoupper(trim((string) ($payload['currency'] ?? 'IDR')));

                if (! is_array($payload) || $currency === '' || $currency === 'IDR' || ! is_numeric($payload['amount'] ?? null)) {
                    return;
                }

                $settled = $payload['amount_settled'] ?? null;
                $rupiah = strtoupper((string) ($payload['currency_settled'] ?? '')) === 'IDR' && is_numeric($settled) ? (int) round((float) $settled) : 0;
                $delta = $rupiah - (int) $donation->amount;

                DB::table('donations')->where('id', $donation->id)->update([
                    'currency' => substr($currency, 0, 3),
                    'original_amount' => (float) $payload['amount'],
                    'amount' => $rupiah,
                ]);

                if ($donation->queue_entry_id) {
                    DB::table('queue_entries')->where('id', $donation->queue_entry_id)->update(['donation_amount' => $rupiah]);
                }

                if ($donation->status === 'queued' && $donation->live_session_id && $delta !== 0) {
                    $total = (int) DB::table('live_sessions')->where('id', $donation->live_session_id)->value('total_donation');
                    DB::table('live_sessions')->where('id', $donation->live_session_id)->update(['total_donation' => max(0, $total + $delta)]);
                }

                DB::table('donation_notifications')->where('donation_id', $donation->id)->orderBy('id')->each(function (object $notification) use ($rupiah, $currency, $payload): void {
                    $context = json_decode((string) $notification->context, true) ?: [];
                    $context = array_merge($context, ['amount' => $rupiah, 'currency' => $currency, 'original_amount' => (float) $payload['amount']]);
                    DB::table('donation_notifications')->where('id', $notification->id)->update(['context' => json_encode($context)]);
                });
            });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropColumn(['currency', 'original_amount']);
        });
    }
};
