<?php

namespace App\Providers;

use App\Support\Ai\ClaudeClient;
use App\Support\Ai\ClaudeClientInterface;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind(ClaudeClientInterface::class, ClaudeClient::class);
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Override PermissionManager's UserCrudController with ours
        // so we can add is_active, plan, ai_daily_limit_override fields
        $this->app->booted(function () {
            $prefix = config('backpack.base.route_prefix', 'admin');
            \Route::group([
                'prefix' => $prefix,
                'middleware' => ['web', backpack_middleware()],
            ], function () {
                \Route::crud('user', \App\Http\Controllers\Admin\UserCrudController::class);
            });
        });
    }
}
