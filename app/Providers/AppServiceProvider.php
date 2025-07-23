<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production or when APP_FORCE_HTTPS is true
        if (config('app.env') === 'production' || config('app.force_https', false)) {
            URL::forceScheme('https');
        }
        
        // Handle ngrok tunneling
        if (request()->header('x-forwarded-proto') === 'https' || 
            request()->header('x-forwarded-ssl') === 'on' ||
            $this->isNgrokRequest()) {
            URL::forceScheme('https');
        }
    }
    
    /**
     * Check if request is from ngrok
     */
    private function isNgrokRequest(): bool
    {
        if (!request()->hasHeader('host')) {
            return false;
        }
        
        $host = request()->header('host');
        return str_contains($host, 'ngrok.io') || 
               str_contains($host, 'ngrok.app') || 
               str_contains($host, 'ngrok-free.app') ||
               str_contains($host, 'ngrok.dev');
    }
}
