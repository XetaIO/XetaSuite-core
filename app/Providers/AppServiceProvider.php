<?php

declare(strict_types=1);

namespace XetaSuite\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use XetaSuite\Models\Permission;
use XetaSuite\Models\Role;
use XetaSuite\Policies\PermissionPolicy;
use XetaSuite\Policies\RolePolicy;
use XetaSuite\Contracts\Ai\LlmProvider;
use XetaSuite\Services\Ai\GroqProvider;
use XetaSuite\Services\Ai\OpenAiProvider;
use XetaSuite\Services\Assistant\AssistantToolRegistry;
use XetaSuite\Services\Assistant\Tools\CalendarEvents\CreateCalendarEventTool;
use XetaSuite\Services\Assistant\Tools\CalendarEvents\DeleteCalendarEventTool;
use XetaSuite\Services\Assistant\Tools\CalendarEvents\GetCalendarEventTool;
use XetaSuite\Services\Assistant\Tools\CalendarEvents\ListCalendarEventsTool;
use XetaSuite\Services\Assistant\Tools\CalendarEvents\UpdateCalendarEventTool;
use XetaSuite\Services\Assistant\Tools\Cleanings\CreateCleaningTool;
use XetaSuite\Services\Assistant\Tools\Cleanings\DeleteCleaningTool;
use XetaSuite\Services\Assistant\Tools\Cleanings\GetCleaningTool;
use XetaSuite\Services\Assistant\Tools\Cleanings\ListCleaningsTool;
use XetaSuite\Services\Assistant\Tools\Cleanings\UpdateCleaningTool;
use XetaSuite\Services\Assistant\Tools\Dashboard\GetDashboardStatsTool;
use XetaSuite\Services\Assistant\Tools\Incidents\CreateIncidentTool;
use XetaSuite\Services\Assistant\Tools\Incidents\DeleteIncidentTool;
use XetaSuite\Services\Assistant\Tools\Incidents\GetIncidentTool;
use XetaSuite\Services\Assistant\Tools\Incidents\ListIncidentsTool;
use XetaSuite\Services\Assistant\Tools\Incidents\UpdateIncidentTool;
use XetaSuite\Services\Assistant\Tools\ItemMovements\CreateItemMovementTool;
use XetaSuite\Services\Assistant\Tools\ItemMovements\DeleteItemMovementTool;
use XetaSuite\Services\Assistant\Tools\ItemMovements\GetItemMovementTool;
use XetaSuite\Services\Assistant\Tools\ItemMovements\ListItemMovementsTool;
use XetaSuite\Services\Assistant\Tools\ItemMovements\UpdateItemMovementTool;
use XetaSuite\Services\Assistant\Tools\Items\CreateItemTool;
use XetaSuite\Services\Assistant\Tools\Items\DeleteItemTool;
use XetaSuite\Services\Assistant\Tools\Items\GetItemTool;
use XetaSuite\Services\Assistant\Tools\Items\ListItemsTool;
use XetaSuite\Services\Assistant\Tools\Items\UpdateItemTool;
use XetaSuite\Services\Assistant\Tools\Maintenances\CreateMaintenanceTool;
use XetaSuite\Services\Assistant\Tools\Maintenances\DeleteMaintenanceTool;
use XetaSuite\Services\Assistant\Tools\Maintenances\GetMaintenanceTool;
use XetaSuite\Services\Assistant\Tools\Maintenances\ListMaintenancesTool;
use XetaSuite\Services\Assistant\Tools\Maintenances\UpdateMaintenanceTool;
use XetaSuite\Services\Assistant\Tools\Materials\CreateMaterialTool;
use XetaSuite\Services\Assistant\Tools\Materials\DeleteMaterialTool;
use XetaSuite\Services\Assistant\Tools\Materials\GetMaterialTool;
use XetaSuite\Services\Assistant\Tools\Materials\ListMaterialsTool;
use XetaSuite\Services\Assistant\Tools\Materials\UpdateMaterialTool;
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
        $this->app->bind(LlmProvider::class, function (): LlmProvider {
            return match (config('services.ai.provider')) {
                'openai' => new OpenAiProvider(),
                default => new GroqProvider(),
            };
        });

        // Tag all assistant tools and bind the registry resolving the tagged collection
        $this->app->tag([
            ListCalendarEventsTool::class,
            GetCalendarEventTool::class,
            CreateCalendarEventTool::class,
            UpdateCalendarEventTool::class,
            DeleteCalendarEventTool::class,
            ListCleaningsTool::class,
            GetCleaningTool::class,
            CreateCleaningTool::class,
            UpdateCleaningTool::class,
            DeleteCleaningTool::class,
            ListIncidentsTool::class,
            GetIncidentTool::class,
            CreateIncidentTool::class,
            UpdateIncidentTool::class,
            DeleteIncidentTool::class,
            ListItemMovementsTool::class,
            GetItemMovementTool::class,
            CreateItemMovementTool::class,
            UpdateItemMovementTool::class,
            DeleteItemMovementTool::class,
            ListItemsTool::class,
            GetItemTool::class,
            CreateItemTool::class,
            UpdateItemTool::class,
            DeleteItemTool::class,
            ListMaintenancesTool::class,
            GetMaintenanceTool::class,
            CreateMaintenanceTool::class,
            UpdateMaintenanceTool::class,
            DeleteMaintenanceTool::class,
            ListMaterialsTool::class,
            GetMaterialTool::class,
            CreateMaterialTool::class,
            UpdateMaterialTool::class,
            DeleteMaterialTool::class,
            GetDashboardStatsTool::class,
        ], 'assistant.tools');

        $this->app->singleton(AssistantToolRegistry::class, function (Application $app): AssistantToolRegistry {
            return new AssistantToolRegistry($app->tagged('assistant.tools'));
        });
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
        $this->configureRateLimiting();
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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(600)->by($request->user()?->id));

        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('assistant-chat', fn (Request $request) => [
            Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()),
            Limit::perHour(200)->by($request->user()?->id ?: $request->ip()),
        ]);

        // Mobile login: limit by email + IP (5/min) and by IP alone (20/min) to slow brute-force.
        RateLimiter::for('mobile-login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);

        // Password reset / setup resend endpoints (in addition to reCAPTCHA).
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
