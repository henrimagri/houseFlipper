<?php

namespace App\Providers;

use App\Contracts\ItbiDatasetProviderInterface;
use App\Models\CommercializationAuthorization;
use App\Models\User;
use App\Services\Platform\DomainTelemetry;
use App\Services\Platform\EventRecorder;
use App\Services\RealEstateData\SaoPauloItbiDatasetProvider;
use App\Services\ResendTransportFactory;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ItbiDatasetProviderInterface::class, SaoPauloItbiDatasetProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DomainTelemetry::boot();
        Event::listen(MessageSent::class, function () {
            try {
                app(EventRecorder::class)->record('email', 'email.transport.accepted', ['outcome' => 'sent']);
            } catch (\Throwable) {
            }
        });
        if ($caBundle = config('services.http.ca_bundle')) {
            Http::globalOptions(['verify' => $caBundle]);
        }
        Mail::extend('resend', fn (array $config) => app(ResendTransportFactory::class)->make($config));
        Route::bind('capture', fn (string $value) => CommercializationAuthorization::query()->findOrFail($value));
        RateLimiter::for('verification', fn (Request $request) => [
            Limit::perMinute(1)->by('verify-user:'.$request->user()->id),
            Limit::perHour(5)->by('verify-hour:'.$request->user()->id),
            Limit::perHour(10)->by('verify-ip:'.$request->ip()),
        ]);
        RateLimiter::for('market-data', fn (Request $request) => [
            Limit::perMinute(6)->by('market-user:'.$request->user()->id),
            Limit::perMinute(12)->by('market-ip:'.$request->ip()),
        ]);
        RateLimiter::for('itbi-upload', fn (Request $request) => Limit::perMinute(2)->by('itbi-user:'.$request->user()->id));
        RateLimiter::for('document-upload', fn (Request $request) => [
            Limit::perMinute(10)->by('document-user:'.$request->user()->id),
            Limit::perMinute(20)->by('document-ip:'.$request->ip()),
        ]);
        RateLimiter::for('visualization-upload', fn (Request $request) => [
            Limit::perMinute(6)->by('visualization-user:'.$request->user()->id),
            Limit::perMinute(12)->by('visualization-ip:'.$request->ip()),
        ]);
        Gate::define('import-itbi', fn (User $user) => $user->getAllPermissions()->contains('name', 'itbi.import'));
        RateLimiter::for('visualization-edit', fn (Request $request) => Limit::perMinute(60)->by('scene-edit:'.$request->user()->id));
        RateLimiter::for('platform-admin', fn (Request $request) => Limit::perMinute(60)->by('platform-user:'.$request->user()?->id));
        RateLimiter::for('platform-action', fn (Request $request) => Limit::perMinute(10)->by('platform-action:'.$request->user()?->id));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', mb_strtolower(is_string($request->input('email')) ? $request->input('email') : '').':'.$request->ip())),
            Limit::perHour(30)->by('login-account-global:'.hash('sha256', mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : '')))),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('password-reset-request', fn (Request $request) => [
            Limit::perHour(10)->by('password-forgot-ip:'.$request->ip()),
            Limit::perHour(3)->by('password-forgot-account:'.hash('sha256', mb_strtolower(is_string($request->input('email')) ? $request->input('email') : ''))),
        ]);
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(10)->by('password-reset-ip:'.$request->ip()),
            Limit::perMinute(5)->by('password-reset-account:'.hash('sha256', mb_strtolower(is_string($request->input('email')) ? $request->input('email') : ''))),
        ]);
        RateLimiter::for('public-leads', fn (Request $request) => [
            Limit::perHour(8)->by('public-lead-ip:'.$request->ip()),
            Limit::perDay(30)->by('public-lead-ip-day:'.$request->ip()),
        ]);
        RateLimiter::for('saas-leads', fn (Request $request) => [
            Limit::perMinute(4)->by('saas-lead-ip:'.$request->ip()),
            Limit::perHour(8)->by('saas-lead-hour:'.$request->ip()),
            Limit::perDay(20)->by('saas-lead-day:'.$request->ip()),
        ]);
        RateLimiter::for('billing-webhook', fn (Request $request) => Limit::perMinute(60)->by('billing-webhook:'.$request->ip()));
    }
}
