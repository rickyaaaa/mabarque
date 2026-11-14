<?php

namespace App\Support;

/**
 * URL aset statis dengan penanda versi (?v=waktu ubah file).
 *
 * CDN Hostinger menyimpan /css dan /js selama 7 hari; tanpa penanda versi, pengunjung tetap mendapat file
 * lama setelah deploy. `git reset` saat deploy memperbarui waktu ubah file yang berubah, jadi URL-nya ikut
 * berganti hanya untuk file yang memang berubah.
 */
final class Asset
{
    /** @var array<string, string> */
    private static array $urls = [];

    public static function url(string $path): string
    {
        return self::$urls[$path] ??= asset($path).(is_file($file = public_path($path)) ? '?v='.filemtime($file) : '');
    }
}
