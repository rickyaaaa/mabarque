<?php

namespace App\Livewire;

use App\Models\PlatformConnection;
use App\Services\PlatformConnectionService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Koneksi provider donasi di halaman /donations: hubungkan Saweria/SociaBuzz/Trakteer dengan signing secret,
 * salin URL webhook untuk dashboard provider, serta jeda/lanjutkan penerimaan donasi.
 * Signing secret tidak pernah ditampilkan kembali.
 */
class ConnectionManager extends Component
{
    /** 'credential' = istilah di dashboard provider; 'steps' = cara menyambungkan; 'verified' = format sudah terkonfirmasi. */
    public const PROVIDERS = [
        'saweria' => [
            'label' => 'Saweria', 'short' => 'S', 'credential' => 'Stream key', 'verified' => true,
            'steps' => 'Di dashboard Saweria buka Integrasi → Webhook, tempel URL webhook di bawah, lalu salin stream key kamu ke kolom ini.',
        ],
        'sociabuzz' => [
            'label' => 'SociaBuzz', 'short' => 'SB', 'credential' => 'Webhook token', 'verified' => false,
            'steps' => 'Di SociaBuzz buka pengaturan Webhook, tempel URL webhook, salin webhook token ke kolom ini, lalu tekan "Test Notification". Format SociaBuzz belum terkonfirmasi — cek contoh payload setelah tes.',
        ],
        'trakteer' => [
            'label' => 'Trakteer', 'short' => 'T', 'credential' => 'Webhook token', 'verified' => true,
            'steps' => 'Di Trakteer buka Integrasi → Webhook, tempel URL webhook, salin token dari halaman itu ke kolom ini, tekan "Send Webhook Test", lalu aktifkan webhook.',
        ],
    ];

    /** Provider yang contoh payload terakhirnya sedang dibuka. */
    public ?string $inspecting = null;

    public ?string $editing = null;

    public string $label = '';

    public string $signingSecret = '';

    public function open(string $provider): void
    {
        abort_unless(array_key_exists($provider, self::PROVIDERS), 404);

        $connection = $this->connection($provider);
        $this->resetValidation();
        $this->fill(['editing' => $provider, 'label' => $connection?->label ?? '', 'signingSecret' => '']);
    }

    public function close(): void
    {
        $this->editing = null;
        $this->signingSecret = '';
    }

    public function save(PlatformConnectionService $service): void
    {
        $this->validate([
            'editing' => ['required', Rule::in(array_keys(self::PROVIDERS))],
            'label' => ['nullable', 'string', 'max:80'],
            'signingSecret' => ['nullable', 'string', 'min:8', 'max:512'],
        ], [], ['signingSecret' => self::PROVIDERS[$this->editing]['credential'] ?? 'kredensial', 'label' => 'label']);

        try {
            $connection = $service->save(auth()->user(), [
                'provider' => $this->editing,
                'label' => trim($this->label) ?: null,
                'signing_secret' => $this->signingSecret ?: null,
            ]);
        } catch (ValidationException $exception) {
            $this->addError('signingSecret', self::PROVIDERS[$this->editing]['credential'].' wajib diisi supaya webhook bisa diverifikasi.');

            return;
        }

        $name = self::PROVIDERS[$connection->provider]['label'];
        $this->close();
        $this->dispatch('notify', message: "{$name} tersimpan. Salin URL webhook ke dashboard {$name}.");
    }

    public function inspect(string $provider): void
    {
        $this->inspecting = $this->inspecting === $provider ? null : $provider;
    }

    public function togglePause(string $provider, PlatformConnectionService $service): void
    {
        $connection = $this->connection($provider);
        abort_if($connection === null, 404);

        $connection = $connection->status === 'paused'
            ? $service->resume(auth()->user(), $connection)
            : $service->pause(auth()->user(), $connection);

        $name = self::PROVIDERS[$provider]['label'];
        $this->dispatch('notify', message: $connection->status === 'paused' ? "{$name} dijeda — donasi baru ditolak sementara." : "{$name} kembali menerima donasi.");
    }

    public function render(PlatformConnectionService $service)
    {
        $connections = auth()->user()->platformConnections()->get()->keyBy('provider');

        return view('livewire.connection-manager', [
            'providers' => collect(self::PROVIDERS)->map(fn (array $meta, string $key) => $meta + [
                'connection' => $connections->get($key),
                'endpoint' => $connections->has($key) ? $service->webhookEndpoint($connections->get($key)) : null,
            ]),
            'editingConnection' => $this->editing ? $connections->get($this->editing) : null,
        ]);
    }

    private function connection(string $provider): ?PlatformConnection
    {
        return auth()->user()->platformConnections()->where('provider', $provider)->first();
    }
}
