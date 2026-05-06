<?php

declare(strict_types=1);

use XetaSuite\Models\Company;
use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\Zone;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Results Per Type
    |--------------------------------------------------------------------------
    | How many results are returned per searchable type when none is provided
    | by the caller.
    */
    'results_per_type' => 5,

    /*
    |--------------------------------------------------------------------------
    | Searchable Types
    |--------------------------------------------------------------------------
    | Configures which models are exposed to the global search.
    |
    | Each entry accepts:
    |   - model:      Eloquent class to query
    |   - permission: Gate ability to check before searching
    |   - columns:    Columns matched with ILIKE (first column also drives ordering)
    |   - relations:  Relations to eager-load
    |   - hq_only:    If true, the type is excluded from non-HQ contexts
    */
    'searchable_types' => [
        'materials' => [
            'model' => Material::class,
            'permission' => 'material.view',
            'columns' => ['name', 'description'],
            'relations' => ['zone', 'site'],
            'hq_only' => false,
        ],
        'zones' => [
            'model' => Zone::class,
            'permission' => 'zone.view',
            'columns' => ['name'],
            'relations' => ['site', 'parent'],
            'hq_only' => false,
        ],
        'items' => [
            'model' => Item::class,
            'permission' => 'item.view',
            'columns' => ['name', 'reference', 'description'],
            'relations' => ['site', 'company'],
            'hq_only' => false,
        ],
        'incidents' => [
            'model' => Incident::class,
            'permission' => 'incident.view',
            'columns' => ['description'],
            'relations' => ['site', 'material', 'reporter'],
            'hq_only' => false,
        ],
        'maintenances' => [
            'model' => Maintenance::class,
            'permission' => 'maintenance.view',
            'columns' => ['description'],
            'relations' => ['site', 'material'],
            'hq_only' => false,
        ],
        'companies' => [
            'model' => Company::class,
            'permission' => 'company.view',
            'columns' => ['name', 'description'],
            'relations' => [],
            'hq_only' => false,
        ],
        'sites' => [
            'model' => Site::class,
            'permission' => 'site.view',
            'columns' => ['name'],
            'relations' => [],
            'hq_only' => true,
        ],
    ],
];
