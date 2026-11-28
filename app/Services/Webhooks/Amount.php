<?php

namespace App\Services\Webhooks;

/**
 * Baca nominal dari payload provider: angka JSON (10000, 0.25) atau teks berformat
 * ("10000", "10.000", "Rp 10.000", "10,000.00", "10.000,50", "12,50").
 */
final class Amount
{
    /** Null bila tidak ada angka yang bisa dibaca. */
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        // Buang simbol mata uang, spasi, dan huruf: "Rp 10.000" → "10.000".
        $clean = preg_replace('/[^0-9.,\-]/', '', $value);
        if ($clean === null || ! preg_match('/\d/', $clean)) {
            return null;
        }

        $lastDot = strrpos($clean, '.');
        $lastComma = strrpos($clean, ',');

        if ($lastDot !== false && $lastComma !== false) {
            // Keduanya ada: pemisah yang paling belakang adalah desimal.
            [$thousands, $decimal] = $lastDot > $lastComma ? [',', '.'] : ['.', ','];
            $clean = str_replace([$thousands, $decimal], ['', '.'], $clean);
        } elseif ($lastDot !== false || $lastComma !== false) {
            $separator = $lastDot !== false ? '.' : ',';
            $parts = explode($separator, $clean);
            // Lebih dari satu pemisah, atau tepat 3 digit di belakang dengan bagian depan bukan 0 → ribuan.
            $isThousands = count($parts) > 2 || (strlen(end($parts)) === 3 && ltrim($parts[0], '-0') !== '');
            $clean = $isThousands ? str_replace($separator, '', $clean) : str_replace($separator, '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
