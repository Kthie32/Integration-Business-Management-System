<?php

namespace App\Providers;

use App\Enums\SystemRole;
use App\Listeners\AuthenticationAuditSubscriber;
use App\Models\User;
use App\Services\System\SettingsService;
use App\Support\PermissionCatalog;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Blade::anonymousComponentPath(resource_path('views/layouts'), 'layouts');

        Password::defaults(fn (): Password => Password::min(8)->letters()->mixedCase()->numbers());

        /*
         * Super Administrators hold every catalogue permission, including ones added after installation.
         * Policy abilities (update, delete, ...) still run, so state and segregation-of-duty rules apply to them too.
         */
        Gate::before(function (User $user, string $ability): ?bool {
            if (PermissionCatalog::exists($ability) && $user->hasRole(SystemRole::SuperAdministrator->value)) {
                return true;
            }

            return null;
        });

        Event::subscribe(AuthenticationAuditSubscriber::class);

        $this->app->booted(fn () => $this->applyOrganizationName());
    }

    /**
     * Use the organization name from Settings as the application and email sender name.
     * Skipped silently while the database is not yet migrated.
     */
    private function applyOrganizationName(): void
    {
        rescue(function (): void {
            $name = $this->app->make(SettingsService::class)->organizationName();

            config(['app.name' => $name, 'mail.from.name' => $name]);
        }, report: false);
    }
}
