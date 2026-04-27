<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use XetaSuite\Http\Controllers\Api\V1\Auth\MobileAuthController;
use XetaSuite\Http\Controllers\Api\V1\CalendarController;
use XetaSuite\Http\Controllers\Api\V1\CalendarEventController;
use XetaSuite\Http\Controllers\Api\V1\CleaningController;
use XetaSuite\Http\Controllers\Api\V1\CompanyController;
use XetaSuite\Http\Controllers\Api\V1\DashboardController;
use XetaSuite\Http\Controllers\Api\V1\EventCategoryController;
use XetaSuite\Http\Controllers\Api\V1\GlobalSearchController;
use XetaSuite\Http\Controllers\Api\V1\IncidentController;
use XetaSuite\Http\Controllers\Api\V1\ItemController;
use XetaSuite\Http\Controllers\Api\V1\ItemMovementController;
use XetaSuite\Http\Controllers\Api\V1\MaintenanceController;
use XetaSuite\Http\Controllers\Api\V1\MaterialController;
use XetaSuite\Http\Controllers\Api\V1\NotificationController;
use XetaSuite\Http\Controllers\Api\V1\PermissionController;
use XetaSuite\Http\Controllers\Api\V1\PersonalAccessTokenController;
use XetaSuite\Http\Controllers\Api\V1\QrCodeScanController;
use XetaSuite\Http\Controllers\Api\V1\RoleController;
use XetaSuite\Http\Controllers\Api\V1\SettingsController;
use XetaSuite\Http\Controllers\Api\V1\SiteController;
use XetaSuite\Http\Controllers\Api\V1\UserController;
use XetaSuite\Http\Controllers\Api\V1\UserLocaleController;
use XetaSuite\Http\Controllers\Api\V1\UserPasswordController;
use XetaSuite\Http\Controllers\Api\V1\UserSiteController;
use XetaSuite\Http\Controllers\Api\V1\VoiceChatController;
use XetaSuite\Http\Controllers\Api\V1\ZoneController;
use XetaSuite\Http\Resources\V1\Users\UserDetailResource;

/*
 |--------------------------------------------------------------------------
 | API Routes
 |--------------------------------------------------------------------------
 */

// Mobile authentication (no CSRF, returns Bearer PAT)
Route::post('/v1/auth/mobile-login', [MobileAuthController::class, 'login']);

Route::group(['prefix' => 'v1', 'middleware' => ['auth:sanctum', 'throttle:api']], function () {

    // Dashboard stats & charts
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/charts', [DashboardController::class, 'chartsData']);

    // Global Search
    Route::get('/search', [GlobalSearchController::class, 'search']);
    Route::get('/search/types', [GlobalSearchController::class, 'types']);

    // Get authenticated user
    Route::get('/auth/user', function (Request $request) {
        return new UserDetailResource($request->user());
    });

    // Update user locale
    Route::patch('/user/locale', UserLocaleController::class);

    // Update user current site
    Route::patch('/user/site', UserSiteController::class);

    // Update user password
    Route::put('/user/password', UserPasswordController::class);

    // Personal Access Tokens (MCP authentication)
    Route::get('/tokens', [PersonalAccessTokenController::class, 'index']);
    Route::post('/tokens', [PersonalAccessTokenController::class, 'store']);
    Route::delete('/tokens/{tokenId}', [PersonalAccessTokenController::class, 'destroy']);

    // Notifications (authenticated user)
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread', [NotificationController::class, 'unread']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{id}', [NotificationController::class, 'destroy']);
    Route::delete('notifications', [NotificationController::class, 'destroyAll']);

    // Sites (headquarters only - enforced by SitePolicy)
    Route::apiResource('sites', SiteController::class);
    Route::get('sites/{site}/users', [SiteController::class, 'users']);
    Route::get('sites/{site}/members', [SiteController::class, 'members']);

    // Companies (headquarters only for management - enforced by CompanyPolicy)
    Route::apiResource('companies', CompanyController::class);
    Route::get('companies/{company}/items', [CompanyController::class, 'items']);
    Route::get('companies/{company}/maintenances', [CompanyController::class, 'maintenances']);
    Route::get('companies/{company}/stats', [CompanyController::class, 'stats']);

    // Zones (site-scoped - enforced by ZonePolicy)
    Route::get('zones/available-parents', [ZoneController::class, 'availableParents']);
    Route::get('zones/tree', [ZoneController::class, 'tree']);
    Route::apiResource('zones', ZoneController::class);
    Route::get('zones/{zone}/children', [ZoneController::class, 'children']);
    Route::get('zones/{zone}/materials', [ZoneController::class, 'materials']);

    // QR Code Scan (retrieve info about scanned material/item)
    Route::get('qr-scan/material/{material}', [QrCodeScanController::class, 'material']);
    Route::get('qr-scan/item/{item}', [QrCodeScanController::class, 'item']);

    // Materials (site-scoped - enforced by MaterialPolicy)
    Route::get('materials/available-zones', [MaterialController::class, 'availableZones']);
    Route::get('materials/available-recipients', [MaterialController::class, 'availableRecipients']);
    Route::apiResource('materials', MaterialController::class);
    Route::get('materials/{material}/stats', [MaterialController::class, 'stats']);
    Route::get('materials/{material}/qr-code', [MaterialController::class, 'qrCode']);
    Route::get('materials/{material}/incidents', [MaterialController::class, 'incidents']);
    Route::get('materials/{material}/maintenances', [MaterialController::class, 'maintenances']);
    Route::get('materials/{material}/cleanings', [MaterialController::class, 'cleanings']);
    Route::get('materials/{material}/items', [MaterialController::class, 'items']);

    // Items (site-scoped - enforced by ItemPolicy)
    Route::get('items/available-companies', [ItemController::class, 'availableCompanies']);
    Route::get('items/available-materials', [ItemController::class, 'availableMaterials']);
    Route::get('items/available-recipients', [ItemController::class, 'availableRecipients']);
    Route::apiResource('items', ItemController::class);
    Route::get('items/{item}/stats', [ItemController::class, 'stats']);
    // Get materials for a specific item
    Route::get('items/{item}/materials', [ItemController::class, 'materials']);
    // Get movements related to a specific item
    Route::get('items/{item}/movements', [ItemController::class, 'movements']);
    // Get price history with statistics for a specific item
    Route::get('items/{item}/price-history', [ItemController::class, 'priceHistory']);
    Route::get('items/{item}/qr-code', [ItemController::class, 'qrCode']);

    // Item Movements - Global list for current site
    Route::get('item-movements', [ItemMovementController::class, 'index']);
    // Item Movements (nested under items)
    Route::apiResource('items.movements', ItemMovementController::class)
        ->parameters([
            'movements' => 'movement',
        ])->except(['index']);

    // Incidents (site-scoped - enforced by IncidentPolicy)
    Route::get('incidents/available-materials', [IncidentController::class, 'availableMaterials']);
    Route::get('incidents/available-maintenances', [IncidentController::class, 'availableMaintenances']);
    Route::get('incidents/severity-options', [IncidentController::class, 'severityOptions']);
    Route::get('incidents/status-options', [IncidentController::class, 'statusOptions']);
    Route::apiResource('incidents', IncidentController::class);

    // Cleanings (site-scoped - enforced by CleaningPolicy)
    Route::get('cleanings/available-materials', [CleaningController::class, 'availableMaterials']);
    Route::get('cleanings/type-options', [CleaningController::class, 'typeOptions']);
    Route::apiResource('cleanings', CleaningController::class);

    // Maintenances (site-scoped - enforced by MaintenancePolicy)
    Route::get('maintenances/available-materials', [MaintenanceController::class, 'availableMaterials']);
    Route::get('maintenances/available-incidents', [MaintenanceController::class, 'availableIncidents']);
    Route::get('maintenances/available-operators', [MaintenanceController::class, 'availableOperators']);
    Route::get('maintenances/available-companies', [MaintenanceController::class, 'availableCompanies']);
    Route::get('maintenances/available-items', [MaintenanceController::class, 'availableItems']);
    Route::get('maintenances/type-options', [MaintenanceController::class, 'typeOptions']);
    Route::get('maintenances/status-options', [MaintenanceController::class, 'statusOptions']);
    Route::get('maintenances/realization-options', [MaintenanceController::class, 'realizationOptions']);
    Route::apiResource('maintenances', MaintenanceController::class);
    Route::get('maintenances/{maintenance}/incidents', [MaintenanceController::class, 'incidents']);
    Route::get('maintenances/{maintenance}/item-movements', [MaintenanceController::class, 'itemMovements']);

    // Settings Management (headquarters only - enforced by SettingPolicy)
    Route::get('settings/manage', [SettingsController::class, 'index']);
    Route::get('settings/{setting}', [SettingsController::class, 'show']);
    Route::put('settings/{setting}', [SettingsController::class, 'update']);

    // Settings Public (simple key-value for frontend)
    Route::get('settings', [SettingsController::class, 'public']);

    // Roles (headquarters only - enforced by RolePolicy)
    Route::get('roles/available-permissions', [RoleController::class, 'availablePermissions']);
    Route::apiResource('roles', RoleController::class);
    Route::get('roles/{role}/users', [RoleController::class, 'users']);

    // Permissions (headquarters only - enforced by PermissionPolicy)
    Route::get('permissions/available-roles', [PermissionController::class, 'availableRoles']);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('permissions', [PermissionController::class, 'store']);
    Route::get('permissions/{permission}', [PermissionController::class, 'show']);
    Route::put('permissions/{permission}', [PermissionController::class, 'update']);
    Route::delete('permissions/{permission}', [PermissionController::class, 'destroy']);
    Route::get('permissions/{permission}/roles', [PermissionController::class, 'roles']);

    // Users (enforced by UserPolicy)
    Route::get('users/available-sites', [UserController::class, 'availableSites']);
    Route::get('users/available-roles', [UserController::class, 'availableRoles']);
    Route::get('users/available-permissions', [UserController::class, 'availablePermissions']);
    Route::apiResource('users', UserController::class)->withTrashed(['show', 'update']);
    Route::post('users/{user}/restore', [UserController::class, 'restore'])->withTrashed();
    Route::get('users/{user}/roles-per-site', [UserController::class, 'rolesPerSite'])->withTrashed();
    Route::get('users/{user}/cleanings', [UserController::class, 'cleanings'])->withTrashed();
    Route::get('users/{user}/maintenances', [UserController::class, 'maintenances'])->withTrashed();
    Route::get('users/{user}/incidents', [UserController::class, 'incidents'])->withTrashed();

    // Calendar - Aggregated view (events, maintenances, incidents)
    Route::get('calendar', [CalendarController::class, 'index']);
    Route::get('calendar/today', [CalendarController::class, 'today']);

    // Event Categories (site-scoped - enforced by EventCategoryPolicy)
    Route::apiResource('event-categories', EventCategoryController::class);

    // Calendar Events (site-scoped - enforced by CalendarEventPolicy)
    Route::get('calendar-events/available-event-categories', [CalendarEventController::class, 'availableEventCategories']);
    Route::apiResource('calendar-events', CalendarEventController::class);
    Route::patch('calendar-events/{calendar_event}/dates', [CalendarEventController::class, 'updateDates']);

    // Voice Assistant (mobile — proxies AI API server-side to protect the API key)
    Route::post('voice/chat', [VoiceChatController::class, 'chat']);

});
