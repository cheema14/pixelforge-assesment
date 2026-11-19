<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PixelforgeClient::class, fn () => new PixelforgeClient(
            (string) config('pixelforge.base_url'),
            (string) config('pixelforge.api_key'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::define('view-admin-reports', fn (User $user) => $user->is_admin);

        RateLimiter::for('generations', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('credits', fn (Request $request) => Limit::perMinute(10)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
