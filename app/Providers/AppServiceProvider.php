<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (
            filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOLEAN)
            || request()->header('X-Forwarded-Proto') === 'https'
            || request()->isSecure()
        ) {
            URL::forceScheme('https');
            $appUrl = config('app.url');
            if (is_string($appUrl) && str_starts_with($appUrl, 'http://')) {
                $httpsUrl = 'https://' . substr($appUrl, 7);
                config([
                    'app.url' => $httpsUrl,
                    'laravel-usp-theme.app_url' => $httpsUrl,
                ]);
            }
        }
    }
}
