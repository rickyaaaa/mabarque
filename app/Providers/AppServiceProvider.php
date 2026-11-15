<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Provider paket khusus development. Sengaja TIDAK di-auto-discover (lihat composer.json
     * extra.laravel.dont-discover): di server produksi paket ini tidak terpasang, dan cache daftar
     * provider yang masih menyebutnya membuat Laravel gagal menyala (pernah menjatuhkan situs saat deploy).
     * Di lokal didaftarkan manual bila paketnya ada.
     */
    private const DEV_PROVIDERS = [
        \Laravel\Pail\PailServiceProvider::class,
        \Laravel\Sail\SailServiceProvider::class,
        \NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('production')) {
            return;
        }

        foreach (self::DEV_PROVIDERS as $provider) {
            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
