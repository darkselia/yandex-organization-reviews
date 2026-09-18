<?php

namespace App\Providers;

use App\Contracts\OrganizationParser;
use App\Services\YandexMaps\YandexOrganizationParser;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrganizationParser::class, YandexOrganizationParser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
