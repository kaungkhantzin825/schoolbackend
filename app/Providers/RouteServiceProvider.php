<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Signed-in users are limited per account, not per IP — whole offices
        // and universities share one NAT address, so an IP-only limit would
        // let a handful of staff lock out all their colleagues.
        RateLimiter::for('api', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(300)->by('user:' . $request->user()->id)
                : Limit::perMinute(120)->by('ip:' . $request->ip());
        });

        // Brute-force protection is keyed on the account being targeted, so a
        // shared office IP cannot be locked out by one person's typos.
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by('login-account:' . $email),
                Limit::perMinute(30)->by('login-ip:' . $request->ip()),
            ];
        });

        // Verification is the product's core action; an HR team working through
        // a batch of candidates must not be throttled mid-task.
        RateLimiter::for('verify', function (Request $request) {
            return $request->user()
                ? Limit::perMinute(120)->by('verify-user:' . $request->user()->id)
                : Limit::perMinute(60)->by('verify-ip:' . $request->ip());
        });

        // Signing up is a once-per-organisation action, so this stays tight.
        RateLimiter::for('registrations', function (Request $request) {
            return Limit::perHour(5)->by('register-ip:' . $request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(180)->by('search-ip:' . $request->ip());
        });
    }
}
