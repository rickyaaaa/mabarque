<?php

namespace App\Services;

use App\Models\User;
use InvalidArgumentException;

/**
 * Link halaman donasi streamer yang dibuka viewer dari kartu paket VIP.
 *
 * Hanya domain resmi platform yang diterima (https), supaya halaman publik tidak bisa dipakai
 * mengarahkan viewer ke situs lain. Disimpan di users.donation_links: {primary, urls: {platform: url}}.
 */
class DonationLinkService
{
    public const PLATFORMS = [
        'sociabuzz' => ['label' => 'SociaBuzz', 'hosts' => ['sociabuzz.com'], 'example' => 'https://sociabuzz.com/namakamu/tribe'],
        'saweria' => ['label' => 'Saweria', 'hosts' => ['saweria.co'], 'example' => 'https://saweria.co/namakamu'],
        'trakteer' => ['label' => 'Trakteer', 'hosts' => ['trakteer.id'], 'example' => 'https://trakteer.id/namakamu'],
    ];

    /**
     * @param  array<string, ?string>  $urls  platform => URL (kosong = tidak dipakai)
     *
     * @throws InvalidArgumentException URL bukan halaman platform yang dipilih
     */
    public function save(User $user, array $urls, ?string $primary): array
    {
        $clean = [];
        foreach (self::PLATFORMS as $platform => $info) {
            $url = trim((string) ($urls[$platform] ?? ''));
            if ($url !== '') {
                $clean[$platform] = $this->normalize($platform, $url);
            }
        }

        $primary = isset($clean[$primary ?? '']) ? $primary : array_key_first($clean);
        $value = $clean === [] ? null : ['primary' => $primary, 'urls' => $clean];

        $user->forceFill(['donation_links' => $value])->save();

        return $value ?? ['primary' => null, 'urls' => []];
    }

    /** @return array{primary: ?string, urls: array<string, string>} */
    public function settings(User $user): array
    {
        $stored = is_array($user->donation_links) ? $user->donation_links : [];
        $urls = array_intersect_key((array) ($stored['urls'] ?? []), self::PLATFORMS);

        return ['primary' => isset($urls[$stored['primary'] ?? '']) ? $stored['primary'] : array_key_first($urls), 'urls' => $urls];
    }

    /**
     * Link untuk halaman viewer, tombol utama paling depan.
     *
     * @return list<array{platform: string, label: string, url: string}>
     */
    public function forViewer(User $user): array
    {
        $settings = $this->settings($user);

        return collect($settings['urls'])
            ->map(fn (string $url, string $platform) => ['platform' => $platform, 'label' => self::PLATFORMS[$platform]['label'], 'url' => $url])
            ->sortBy(fn (array $link) => $link['platform'] === $settings['primary'] ? 0 : 1)
            ->values()
            ->all();
    }

    private function normalize(string $platform, string $url): string
    {
        $info = self::PLATFORMS[$platform];

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $hostOk = collect($info['hosts'])->contains(fn (string $allowed) => $host === $allowed || $host === 'www.'.$allowed);

        if ($parts === false || ! $hostOk || $path === '' || isset($parts['user']) || isset($parts['port'])) {
            throw new InvalidArgumentException("Link {$info['label']} harus halaman donasi di {$info['hosts'][0]}, mis. {$info['example']}.");
        }

        return 'https://'.$host.'/'.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
