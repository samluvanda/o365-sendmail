<?php

namespace O365Sendmail;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use O365Sendmail\Commands\PutEnv;
use O365Sendmail\O365Sendmail;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/mailers.php',
            'mail.mailers'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PutEnv::class
            ]);
        }

        Mail::extend('o365-sendmail', function (array $config = []) {
            return new O365Sendmail($config);
        });
    }
}
