<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\ClaudeClient;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Client::class, fn () => new Client(
            apiKey: config('services.anthropic.key'),
            requestOptions: [
                // Das SDK setzt keinen Timeout durch, das muss der HTTP-Client tun.
                // Zwei Versuche bleiben so unter PHP_MAX_EXECUTION_TIME (99 s).
                'transporter' => new GuzzleClient(['timeout' => 45, 'connect_timeout' => 5]),
                'maxRetries' => 1,
            ],
        ));

        // Einstellungen und Statusprüfung sollen schnell scheitern, statt lange zu warten.
        $this->app->bind(ClaudeClient::QUICK_CLIENT, fn () => new Client(
            apiKey: config('services.anthropic.key'),
            requestOptions: [
                'transporter' => new GuzzleClient(['timeout' => 15, 'connect_timeout' => 5]),
                'maxRetries' => 0,
            ],
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
