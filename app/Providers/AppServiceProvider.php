<?php

namespace App\Providers;

use App\Services\Essense\EssenseClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One shared, stateless client, built from config/services.php. Anything that type-hints
        // EssenseClient (controllers, commands, services) receives this instance.
        $this->app->singleton(EssenseClient::class, fn () => new EssenseClient(
            baseUrl: (string) config('services.essense.url'),
            apiKey: (string) config('services.essense.key'),
            timeout: (int) config('services.essense.timeout', 5),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
