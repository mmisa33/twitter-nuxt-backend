<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        $this->app->singleton(Auth::class, function ($app) {
            $serviceAccountPath = env('FIREBASE_CREDENTIALS');

            if (!$serviceAccountPath || !file_exists(base_path($serviceAccountPath))) {
                throw new \Exception('Firebase service account JSON not found at ' . base_path($serviceAccountPath));
            }

            $factory = (new Factory())
                ->withServiceAccount(base_path($serviceAccountPath));

            return $factory->createAuth();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
