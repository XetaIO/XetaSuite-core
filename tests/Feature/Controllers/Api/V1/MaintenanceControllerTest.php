<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\Company;
use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\User;
use XetaSuite\Models\Zone;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->headquarters = Site::factory()->headquarters()->create();
    $this->regularSite = Site::factory()->create();
    $this->zone = Zone::factory()->forSite($this->regularSite)->create();
    $this->material = Material::factory()->forSite($this->regularSite)->forZone($this->zone)->create();

    // Create permissions
    $permissions = [
        'maintenance.view',
        'maintenance.viewAny',
        'maintenance.create',
        'maintenance.update',
        'maintenance.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create role with all permissions
    $this->role = Role::create(['name' => 'maintenance-manager', 'guard_name' => 'web']);
    $this->role->givePermissionTo($permissions);

    // Create role without any permissions
    $this->roleWithoutPermissions = Role::create(['name' => 'basic-user', 'guard_name' => 'web']);
});

/*
|--------------------------------------------------------------------------
| INDEX - List maintenances
|--------------------------------------------------------------------------
*/

describe('index', function (): void {
    it('lists maintenances for current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Maintenance::factory()
            ->count(3)
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->createdBy($user)
            ->create();

        // Other site maintenance (should not be returned)
        Maintenance::factory()->forSite($this->headquarters)->create();

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('filters maintenances by status', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->planned()
            ->createdBy($user)
            ->create();

        Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->completed()
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances?status=planned');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'planned');
    });

    it('filters maintenances by type', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->corrective()
            ->createdBy($user)
            ->create();

        Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->preventive()
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances?type=corrective');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'corrective');
    });

    it('searches maintenances by description', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->createdBy($user)
            ->create(['description' => 'Repair pump motor']);

        Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->createdBy($user)
            ->create(['description' => 'Replace filter']);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances?search=pump');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.description', 'Repair pump motor');
    });

    it('requires maintenance.viewAny permission', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->roleWithoutPermissions);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances');

        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| SHOW - View a maintenance
|--------------------------------------------------------------------------
*/

describe('show', function (): void {
    it('returns maintenance details', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)->getJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $maintenance->id)
            ->assertJsonPath('data.description', $maintenance->description)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'description',
                    'type',
                    'type_label',
                    'realization',
                    'realization_label',
                    'status',
                    'status_label',
                    'material_id',
                    'material_name',
                    'created_by_id',
                    'created_by_name',
                    'started_at',
                    'resolved_at',
                    'created_at',
                ],
            ]);
    });

    it('prevents viewing maintenance from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaintenance = Maintenance::factory()
            ->forSite($this->headquarters)
            ->create();

        $response = $this->actingAs($user)->getJson("/api/v1/maintenances/{$otherMaintenance->id}");

        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| STORE - Create a maintenance
|--------------------------------------------------------------------------
*/

describe('store', function (): void {
    it('creates a maintenance with minimal data', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Test maintenance description',
            'realization' => 'internal',
            'operator_ids' => [$user->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Test maintenance description');

        $this->assertDatabaseHas('maintenances', [
            'description' => 'Test maintenance description',
            'site_id' => $this->regularSite->id,
        ]);
    });

    it('creates a maintenance with material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'material_id' => $this->material->id,
            'description' => 'Repair material',
            'type' => 'corrective',
            'realization' => 'internal',
            'operator_ids' => [$user->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.material_id', $this->material->id);
    });

    it('creates a maintenance with incidents', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $incident1 = Incident::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->reportedBy($user)
            ->create();
        $incident2 = Incident::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->reportedBy($user)
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Fix incidents',
            'incident_ids' => [$incident1->id, $incident2->id],
            'realization' => 'internal',
            'operator_ids' => [$user->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.incident_count', 2);

        // Verify incidents are linked
        expect($incident1->fresh()->maintenance_id)->toBe($response->json('data.id'));
        expect($incident2->fresh()->maintenance_id)->toBe($response->json('data.id'));
    });

    it('creates a maintenance with operators for internal realization', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $operator = User::factory()->create();
        $operator->sites()->attach($this->regularSite->id);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Internal maintenance',
            'realization' => 'internal',
            'operator_ids' => [$user->id, $operator->id],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.operators');
    });

    it('creates a maintenance with companies for external realization', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $company = Company::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'External maintenance',
            'realization' => 'external',
            'company_ids' => [$company->id],
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.companies');
    });

    it('creates a maintenance with both operators and companies', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $company = Company::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Mixed maintenance',
            'realization' => 'both',
            'operator_ids' => [$user->id],
            'company_ids' => [$company->id],
        ]);

        $response->assertCreated()
            ->assertJsonCount(1, 'data.operators')
            ->assertJsonCount(1, 'data.companies');
    });

    it('creates item movements for spare parts', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'item_entry_total' => 10,
                'current_price' => 25.50,
            ]);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Maintenance with spare parts',
            'realization' => 'internal',
            'operator_ids' => [$user->id],
            'item_movements' => [
                ['item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated();

        // Verify item movement was created
        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'type' => 'exit',
            'quantity' => 2,
            'movable_type' => Maintenance::class,
        ]);

        // Verify stock was reduced
        expect($item->fresh()->current_stock)->toBe(8);
    });

    it('validates operators required for internal realization', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Test maintenance',
            'realization' => 'internal',
            // No operator_ids
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('operator_ids');
    });

    it('validates companies required for external realization', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Test maintenance',
            'realization' => 'external',
            // No company_ids
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('company_ids');
    });

    it('validates spare parts stock availability', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['item_entry_total' => 5]);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Maintenance with spare parts',
            'realization' => 'internal',
            'operator_ids' => [$user->id],
            'item_movements' => [
                ['item_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('item_movements.0.quantity');
    });

    it('requires maintenance.create permission', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->roleWithoutPermissions);

        $response = $this->actingAs($user)->postJson('/api/v1/maintenances', [
            'description' => 'Test',
        ]);

        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| UPDATE - Update a maintenance
|--------------------------------------------------------------------------
*/

describe('update', function (): void {
    it('updates a maintenance', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->createdBy($user)
            ->internal()
            ->create();
        $maintenance->operators()->attach($user->id);

        $response = $this->actingAs($user)->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'description' => 'Updated description',
            'status' => 'in_progress',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'Updated description')
            ->assertJsonPath('data.status', 'in_progress');
    });

    it('updates incidents assignment', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->internal()
            ->create();
        $maintenance->operators()->attach($user->id);

        $newIncident = Incident::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->reportedBy($user)
            ->create();

        $response = $this->actingAs($user)->putJson("/api/v1/maintenances/{$maintenance->id}", [
            'incident_ids' => [$newIncident->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.incident_count', 1);

        expect($newIncident->fresh()->maintenance_id)->toBe($maintenance->id);
    });

    it('prevents updating maintenance from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaintenance = Maintenance::factory()
            ->forSite($this->headquarters)
            ->create();

        $response = $this->actingAs($user)->putJson("/api/v1/maintenances/{$otherMaintenance->id}", [
            'description' => 'Updated',
        ]);

        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| DESTROY - Delete a maintenance
|--------------------------------------------------------------------------
*/

describe('destroy', function (): void {
    it('deletes a maintenance', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('maintenances', ['id' => $maintenance->id]);
    });

    it('unlinks incidents when deleting maintenance', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $incident = Incident::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->reportedBy($user)
            ->create(['maintenance_id' => $maintenance->id]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertNoContent();
        expect($incident->fresh()->maintenance_id)->toBeNull();
    });

    it('deletes related item movements', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['item_entry_total' => 10]);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()->create([
            'item_id' => $item->id,
            'type' => 'exit',
            'quantity' => 2,
            'movable_type' => Maintenance::class,
            'movable_id' => $maintenance->id,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/v1/maintenances/{$maintenance->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('item_movements', [
            'movable_type' => Maintenance::class,
            'movable_id' => $maintenance->id,
        ]);
    });

    it('prevents deleting maintenance from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaintenance = Maintenance::factory()
            ->forSite($this->headquarters)
            ->create();

        $response = $this->actingAs($user)->deleteJson("/api/v1/maintenances/{$otherMaintenance->id}");

        $response->assertForbidden();
    });
});

/*
|--------------------------------------------------------------------------
| OPTIONS - Dropdown options
|--------------------------------------------------------------------------
*/

describe('options', function (): void {
    it('returns available materials', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/available-materials');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    });

    it('returns available incidents', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Incident::factory()
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->reportedBy($user)
            ->create();

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/available-incidents');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'description', 'severity', 'status']]]);
    });

    it('returns available operators', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/available-operators');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'full_name', 'email']]]);
    });

    it('returns available companies', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        Company::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/available-companies');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    });

    it('returns available items', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['item_entry_total' => 10]);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/available-items');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'reference', 'current_stock', 'current_price']]]);
    });

    it('returns type options', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/type-options');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    });

    it('returns status options', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/status-options');

        $response->assertOk()
            ->assertJsonCount(4, 'data');
    });

    it('returns realization options', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenances/realization-options');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

/*
|--------------------------------------------------------------------------
| NESTED RESOURCES
|--------------------------------------------------------------------------
*/

describe('nested resources', function (): void {
    it('returns paginated incidents for a maintenance', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        Incident::factory()
            ->count(5)
            ->forSite($this->regularSite)
            ->forMaterial($this->material)
            ->reportedBy($user)
            ->create(['maintenance_id' => $maintenance->id]);

        $response = $this->actingAs($user)->getJson("/api/v1/maintenances/{$maintenance->id}/incidents");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('returns paginated item movements with total cost', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $maintenance = Maintenance::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['item_entry_total' => 100, 'current_price' => 10]);

        ItemMovement::factory()->create([
            'item_id' => $item->id,
            'type' => 'exit',
            'quantity' => 5,
            'unit_price' => 10,
            'total_price' => 50,
            'movable_type' => Maintenance::class,
            'movable_id' => $maintenance->id,
        ]);

        ItemMovement::factory()->create([
            'item_id' => $item->id,
            'type' => 'exit',
            'quantity' => 3,
            'unit_price' => 10,
            'total_price' => 30,
            'movable_type' => Maintenance::class,
            'movable_id' => $maintenance->id,
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/maintenances/{$maintenance->id}/item-movements");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total_cost', fn ($cost) => (float) $cost === 80.0);
    });
});
