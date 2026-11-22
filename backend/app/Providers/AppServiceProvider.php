<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Tenancy\Support\TenantContext::class);
        $this->app->bind(\App\Auth\Services\SmsGateway::class, \App\Auth\Services\LogSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\App\Billing\Events\InvoiceCreated::class, \App\Billing\Listeners\OnInvoiceCreated::class);
        $this->app->bind(\App\Credits\Services\PaymentGateway::class, \App\Credits\Services\FakePaymentGateway::class);
        $this->app->bind(\App\Assistant\Contracts\LlmClient::class, \App\Assistant\Services\RuleBasedLlmClient::class);
        $this->app->bind(\App\Assistant\Contracts\SpeechToText::class, \App\Assistant\Services\FakeSpeechToText::class);
        \Illuminate\Support\Facades\RateLimiter::for('otp', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perHour(5)->by('otp:'.$r->input('mobile')),
            \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('otp-ip:'.$r->ip()),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('login', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by('login:'.$r->ip().'|'.$r->input('login')));
    }
}
