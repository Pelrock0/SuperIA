<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected static $shouldDiscoverEvents = true;

    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
    ];

    #[\Override]
    public function register(): void
    {
        //
    }

    #[\Override]
    public function shouldDiscoverEvents(): bool
    {
        return true;
    }

    #[\Override]
    public function boot(): void
    {
        //
    }
}
