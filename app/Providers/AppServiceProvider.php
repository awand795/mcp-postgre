<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
        // Octane Database Reconnect to prevent "has gone away" or dropped connections
        if (class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            Event::listen(\Laravel\Octane\Events\RequestReceived::class, function () {
                $connections = ['pgsql', 'pgsql_mbi'];
                
                foreach ($connections as $connection) {
                    try {
                        if (DB::connection($connection)->getPdo()) {
                            DB::connection($connection)->getPdo()->query('SELECT 1');
                        }
                    } catch (\Exception $e) {
                        DB::connection($connection)->reconnect();
                    }
                }
            });
        }
    }
}
