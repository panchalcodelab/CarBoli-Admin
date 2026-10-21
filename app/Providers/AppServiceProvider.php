<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\NotificationChannelManager;
use App\Policies\RolePolicy;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(NotificationChannelManager::class, fn (): NotificationChannelManager => NotificationChannelManager::make());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Role is a vendor (Spatie) model, so its policy is registered explicitly
        // rather than relying on Laravel's App\Models convention-based discovery.
        Gate::policy(Role::class, RolePolicy::class);

        Event::listen(Login::class, function (Login $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $event->user->forceFill(['last_login_at' => now()])->save();

            app(AuditLogService::class)->record(
                'auth',
                'user.login',
                $event->user,
                null,
                ['guard' => $event->guard],
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            app(AuditLogService::class)->record(
                'auth',
                'user.logout',
                $event->user,
                null,
                ['guard' => $event->guard],
            );
        });
    }
}
