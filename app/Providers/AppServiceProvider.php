<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

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
        // Temporary slow query logging for Supabase performance diagnostics.
        // Enable by setting DB_SLOW_QUERY_LOG=true in .env
        if (env('DB_SLOW_QUERY_LOG', false)) {
            DB::listen(function (QueryExecuted $query): void {
                if ($query->time >= (int) env('DB_SLOW_QUERY_THRESHOLD_MS', 500)) {
                    Log::channel('stack')->warning('slow_query_detected', [
                        'connection' => $query->connectionName,
                        'time_ms' => $query->time,
                        'sql' => $query->sql,
                        'bindings_count' => is_array($query->bindings) ? count($query->bindings) : 0,
                        'bindings_sample' => is_array($query->bindings) ? array_slice($query->bindings, 0, 10) : [],
                        'request_url' => request()?->fullUrl(),
                        'request_method' => request()?->method(),
                    ]);
                }
            });
        }
    }
}
