<?php

namespace App\Providers;

use App\Contracts\ImageStorageContract;
use App\Mail\Transport\ZeptomailTransport;
use App\Services\Images\CloudinaryImageStorage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageStorageContract::class, fn () => new CloudinaryImageStorage(
            config('services.cloudinary.cloud_name'),
            config('services.cloudinary.api_key'),
            config('services.cloudinary.api_secret'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The frontend contract expects raw payloads, not Laravel's default
        // {"data": ...} resource wrapper — ApiResponses no longer adds its
        // own envelope either, so without this every response would still
        // gain a stray top-level "data" key.
        JsonResource::withoutWrapping();

        $this->configureMailTransport();
        $this->configurePasswordDefaults();
        $this->configureRateLimiting();
    }

    protected function configureMailTransport(): void
    {
        Mail::extend('zeptomail', fn () => new ZeptomailTransport(
            config('services.zeptomail.url'),
            config('services.zeptomail.token'),
        ));
    }

    protected function configurePasswordDefaults(): void
    {
        Password::defaults(function () {
            $rule = Password::min(8)->mixedCase()->numbers();

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('registration', fn (Request $request) => Limit::perHour(5)->by($request->ip()));

        // Resending a code is a distinct, lower-ceiling concern from
        // verifying one — kept as its own limiter so it can stay tight
        // without affecting verification attempts. Keyed by whichever
        // identifier the request actually carries (registrationId for
        // register/otp-verify contexts, email for reset/change-email,
        // challengeToken for 2FA resend/verify).
        RateLimiter::for('otp-resend', fn (Request $request) => Limit::perMinutes(10, 5)
            ->by($request->string('registrationId')->value()
                ?: $request->string('email')->value()
                ?: $request->string('challengeToken')->value()
                ?: $request->ip()));

        // Deliberately more generous than VerificationCodeService's own
        // per-code attempt cap (config('corpslink.otp.max_attempts'), 5 by
        // default): this just guards against request flooding, so the
        // domain-specific "too many attempts" error can still surface.
        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinutes(10, 15)
            ->by($request->string('registrationId')->value()
                ?: $request->string('email')->value()
                ?: $request->string('challengeToken')->value()
                ?: $request->ip()));

        // Deliberately more generous than LoginAction's own per-identifier
        // lockout (5 attempts / 15 min): this is just a coarse per-IP guard
        // against request flooding, not the primary brute-force defense.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)
            ->by($request->string('identifier')->value().'|'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perHour(3)
            ->by($request->string('email')->value() ?: $request->ip()));
    }
}
