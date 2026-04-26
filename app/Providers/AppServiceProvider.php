<?php

declare(strict_types=1);

namespace XetaSuite\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use XetaSuite\Models\Permission;
use XetaSuite\Models\Role;
use XetaSuite\Policies\PermissionPolicy;
use XetaSuite\Policies\RolePolicy;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Services\Ai\GroqProvider;
use XetaSuite\Settings\Settings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register the Settings class
        $this->app->singleton(function (Application $app): Settings {
            return new Settings($app['cache.store']);
        });

        // Bind the AI provider interface to the configured implementation
        $this->app->bind(LlmProvider::class, GroqProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configurePasswords();
        $this->configureCommands();
        $this->configureDates();
    }

    /**
     * Configure the application's passwords.
     */
    private function configurePasswords(): void
    {
        // Set default password rule for the application.
        Password::defaults(function () {
            $rule = Password::min(8);

            return App::isProduction()
                ? $rule->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                : $rule;
        });
    }

    /**
     * Configure the application's commands.
     */
    private function configureCommands(): void
    {
        DB::prohibitDestructiveCommands(
            App::isProduction()
        );
    }

    /**
     * Configure the application's dates.
     */
    private function configureDates(): void
    {
        Date::use(CarbonImmutable::class);
    }

    /**
     * Configure the application's models.
     */
    private function configureModels(): void
    {
        Model::shouldBeStrict();
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
    }
}
