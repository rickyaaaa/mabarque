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
    public const PROVIDERS = [
        'saweria' => ['label' => 'Saweria', 'short' => 'S'],
        'sociabuzz' => ['label' => 'SociaBuzz', 'short' => 'SB'],
        'trakteer' => ['label' => 'Trakteer', 'short' => 'T'],
    ];

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
            'signingSecret' => ['nullable', 'string', 'min:16', 'max:512'],
        ], [], ['signingSecret' => 'signing secret', 'label' => 'label']);

        try {
            $connection = $service->save(auth()->user(), [
                'provider' => $this->editing,
                'label' => trim($this->label) ?: null,
                'signing_secret' => $this->signingSecret ?: null,
            ]);
        } catch (ValidationException $exception) {
            $this->addError('signingSecret', $exception->validator->errors()->first('signing_secret'));

            return;
        }

        $name = self::PROVIDERS[$connection->provider]['label'];
        $this->close();
        $this->dispatch('notify', message: "{$name} tersimpan. Salin URL webhook ke dashboard {$name}.");
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
