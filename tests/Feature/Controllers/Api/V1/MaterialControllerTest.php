<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Enums\Materials\CleaningFrequency;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\User;
use XetaSuite\Models\Zone;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create headquarters site
    $this->headquarters = Site::factory()->create(['is_headquarters' => true]);

    // Create a regular site
    $this->regularSite = Site::factory()->create(['is_headquarters' => false]);

    // Create another regular site
    $this->otherSite = Site::factory()->create(['is_headquarters' => false]);

    // Create permissions
    $permissions = [
        'material.viewAny',
        'material.view',
        'material.create',
        'material.update',
        'material.delete',
        'material.generateQrCode',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create role with all permissions
    $this->role = Role::create(['name' => 'material-manager', 'guard_name' => 'web']);
    $this->role->givePermissionTo($permissions);

    // Create zones that allow materials
    $this->zoneWithMaterials = Zone::factory()->forSite($this->regularSite)->create([
        'name' => 'Zone With Materials',
        'allow_material' => true,
    ]);

    $this->otherSiteZone = Zone::factory()->forSite($this->otherSite)->create([
        'name' => 'Other Site Zone',
        'allow_material' => true,
    ]);
});

// ============================================================================
// INDEX TESTS
// ============================================================================

describe('index', function (): void {
    it('returns only materials for zones on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Material::factory()->count(3)->forZone($this->zoneWithMaterials)->create();
        Material::factory()->count(2)->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('returns paginated list with proper structure', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Material::factory()->count(5)->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'description', 'zone_id', 'zone',
                        'created_by_id', 'created_by_name',
                        'cleaning_alert', 'cleaning_alert_email',
                        'incident_count', 'maintenance_count', 'cleaning_count',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    });

    it('returns materials ordered by name', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Material::factory()->forZone($this->zoneWithMaterials)->create(['name' => 'Zebra Material']);
        Material::factory()->forZone($this->zoneWithMaterials)->create(['name' => 'Alpha Material']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials');

        $response->assertOk();
        $data = $response->json('data');
        expect($data[0]['name'])->toBe('Alpha Material');
        expect($data[1]['name'])->toBe('Zebra Material');
    });

    it('filters materials by zone_id', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $zone2 = Zone::factory()->forSite($this->regularSite)->create(['allow_material' => true]);

        Material::factory()->forZone($this->zoneWithMaterials)->create(['name' => 'Zone 1 Material']);
        Material::factory()->forZone($zone2)->create(['name' => 'Zone 2 Material']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials?zone_id={$this->zoneWithMaterials->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Zone 1 Material');
        expect($names)->not->toContain('Zone 2 Material');
    });

    it('filters materials by search term', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        Material::factory()->forZone($this->zoneWithMaterials)->create(['name' => 'Printer HP']);
        Material::factory()->forZone($this->zoneWithMaterials)->create(['name' => 'Coffee Machine']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials?search=Printer');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        expect($names)->toContain('Printer HP');
        expect($names)->not->toContain('Coffee Machine');
    });

    it('denies access for user without viewAny permission', function (): void {
        $roleWithoutViewAny = Role::create(['name' => 'limited-material', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutViewAny);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials');

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/materials');

        $response->assertUnauthorized();
    });
});

// ============================================================================
// SHOW TESTS
// ============================================================================

describe('show', function (): void {
    it('returns material details for material on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()
            ->forZone($this->zoneWithMaterials)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'name', 'description', 'zone_id', 'zone',
                    'created_by_id', 'created_by_name', 'creator',
                    'cleaning_alert', 'cleaning_alert_email',
                    'cleaning_alert_frequency_repeatedly', 'cleaning_alert_frequency_type',
                    'recipients', 'incident_count', 'maintenance_count', 'cleaning_count',
                    'created_at', 'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $material->id)
            ->assertJsonPath('data.name', $material->name);
    });

    it('includes recipients when cleaning alert is enabled', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $recipient = User::factory()->create();
        $recipient->sites()->attach($this->regularSite);

        $material = Material::factory()
            ->forZone($this->zoneWithMaterials)
            ->withCleaningAlert()
            ->create();
        $material->recipients()->attach($recipient);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertOk()
            ->assertJsonPath('data.cleaning_alert', true)
            ->assertJsonPath('data.recipients.0.id', $recipient->id);
    });

    it('denies access for material on different site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $material = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertForbidden();
    });

    it('denies access for user without view permission', function (): void {
        $roleWithoutView = Role::create(['name' => 'no-material-view', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutView);
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}");

        $response->assertForbidden();
    });

    it('returns 404 for non-existent material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials/99999');

        $response->assertNotFound();
    });
});

// ============================================================================
// STORE TESTS
// ============================================================================

describe('store', function (): void {
    it('creates a new material in zone on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', [
                'zone_id' => $this->zoneWithMaterials->id,
                'name' => 'New Material',
                'description' => 'A test material',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Material')
            ->assertJsonPath('data.description', 'A test material')
            ->assertJsonPath('data.zone_id', $this->zoneWithMaterials->id)
            ->assertJsonPath('data.created_by_id', $user->id);

        $this->assertDatabaseHas('materials', [
            'name' => 'New Material',
            'zone_id' => $this->zoneWithMaterials->id,
            'created_by_id' => $user->id,
        ]);
    });

    it('creates a material with cleaning alert enabled', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $recipient = User::factory()->create();
        $recipient->sites()->attach($this->regularSite);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', [
                'zone_id' => $this->zoneWithMaterials->id,
                'name' => 'Alert Material',
                'cleaning_alert' => true,
                'cleaning_alert_email' => true,
                'cleaning_alert_frequency_repeatedly' => 2,
                'cleaning_alert_frequency_type' => CleaningFrequency::WEEKLY->value,
                'recipients' => [$recipient->id],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.cleaning_alert', true)
            ->assertJsonPath('data.cleaning_alert_email', true)
            ->assertJsonPath('data.cleaning_alert_frequency_repeatedly', 2)
            ->assertJsonPath('data.cleaning_alert_frequency_type', CleaningFrequency::WEEKLY->value);

        // Check recipients are synced
        $material = Material::where('name', 'Alert Material')->first();
        expect($material->recipients)->toHaveCount(1);
        expect($material->recipients->first()->id)->toBe($recipient->id);
    });

    it('validates zone belongs to user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', [
                'zone_id' => $this->otherSiteZone->id, // Zone from another site
                'name' => 'New Material',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['zone_id']);
    });

    it('validates zone allows materials', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $zoneNoMaterials = Zone::factory()->forSite($this->regularSite)->create([
            'allow_material' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', [
                'zone_id' => $zoneNoMaterials->id,
                'name' => 'New Material',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['zone_id']);
    });

    it('validates recipients belong to current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherSiteUser = User::factory()->create();
        $otherSiteUser->sites()->attach($this->otherSite);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', [
                'zone_id' => $this->zoneWithMaterials->id,
                'name' => 'New Material',
                'cleaning_alert' => true,
                'recipients' => [$otherSiteUser->id],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['recipients.0']);
    });

    it('validates required fields', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['zone_id', 'name']);
    });

    it('denies access for user without create permission', function (): void {
        $roleWithoutCreate = Role::create(['name' => 'no-material-create', 'guard_name' => 'web']);
        $roleWithoutCreate->givePermissionTo('material.viewAny');
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutCreate);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/materials', [
                'zone_id' => $this->zoneWithMaterials->id,
                'name' => 'New Material',
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// UPDATE TESTS
// ============================================================================

describe('update', function (): void {
    it('updates a material on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'name' => 'Updated Material',
                'description' => 'Updated description',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Material')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('materials', [
            'id' => $material->id,
            'name' => 'Updated Material',
        ]);
    });

    it('updates material zone to another zone on same site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $zone2 = Zone::factory()->forSite($this->regularSite)->create(['allow_material' => true]);
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'zone_id' => $zone2->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.zone_id', $zone2->id);
    });

    it('enables cleaning alert and adds recipients', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $recipient = User::factory()->create();
        $recipient->sites()->attach($this->regularSite);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create([
            'cleaning_alert' => false,
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'cleaning_alert' => true,
                'cleaning_alert_email' => true,
                'cleaning_alert_frequency_type' => CleaningFrequency::DAILY->value,
                'recipients' => [$recipient->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.cleaning_alert', true);

        $material->refresh();
        expect($material->recipients)->toHaveCount(1);
    });

    it('disables cleaning alert and clears related fields', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $recipient = User::factory()->create();
        $recipient->sites()->attach($this->regularSite);

        $material = Material::factory()
            ->forZone($this->zoneWithMaterials)
            ->withCleaningAlert()
            ->create([
                'cleaning_alert_frequency_repeatedly' => 5,
                'cleaning_alert_frequency_type' => CleaningFrequency::WEEKLY->value,
            ]);
        $material->recipients()->attach($recipient);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'cleaning_alert' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.cleaning_alert', false)
            ->assertJsonPath('data.cleaning_alert_email', false);

        $material->refresh();
        expect($material->recipients)->toHaveCount(0);
        expect($material->cleaning_alert_frequency_repeatedly)->toBe(0);
    });

    it('validates zone belongs to user current site on update', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'zone_id' => $this->otherSiteZone->id,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['zone_id']);
    });

    it('denies access for material on different site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $material = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'name' => 'Updated',
            ]);

        $response->assertForbidden();
    });

    it('denies access for user without update permission', function (): void {
        $roleWithoutUpdate = Role::create(['name' => 'no-material-update', 'guard_name' => 'web']);
        $roleWithoutUpdate->givePermissionTo('material.viewAny');
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutUpdate);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/materials/{$material->id}", [
                'name' => 'Updated',
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// DESTROY TESTS
// ============================================================================

describe('destroy', function (): void {
    it('deletes a material on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        $materialId = $material->id;

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$materialId}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('materials', ['id' => $materialId]);
    });

    it('preserves material_name in related cleanings on delete', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create([
            'name' => 'Important Material',
        ]);
        $cleaning = Cleaning::factory()->forSite($this->regularSite)->create([
            'material_id' => $material->id,
            'material_name' => null,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$material->id}");

        $cleaning->refresh();
        expect($cleaning->material_name)->toBe('Important Material');
        expect($cleaning->material_id)->toBeNull();
    });

    it('preserves material_name in related incidents on delete', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create([
            'name' => 'Incident Material',
        ]);
        $incident = Incident::factory()->forSite($this->regularSite)->create([
            'material_id' => $material->id,
            'material_name' => null,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$material->id}");

        $incident->refresh();
        expect($incident->material_name)->toBe('Incident Material');
        expect($incident->material_id)->toBeNull();
    });

    it('preserves material_name in related maintenances on delete', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create([
            'name' => 'Maintenance Material',
        ]);
        $maintenance = Maintenance::factory()->forSite($this->regularSite)->create([
            'material_id' => $material->id,
            'material_name' => null,
        ]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$material->id}");

        $maintenance->refresh();
        expect($maintenance->material_name)->toBe('Maintenance Material');
        expect($maintenance->material_id)->toBeNull();
    });

    it('detaches items from material on delete', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        $item = Item::factory()->forSite($this->regularSite)->create();
        $material->items()->attach($item);

        expect($material->items)->toHaveCount(1);

        $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$material->id}");

        $item->refresh();
        expect($item->materials)->toHaveCount(0);
    });

    it('denies access for material on different site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $material = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$material->id}");

        $response->assertForbidden();
    });

    it('denies access for user without delete permission', function (): void {
        $roleWithoutDelete = Role::create(['name' => 'no-material-delete', 'guard_name' => 'web']);
        $roleWithoutDelete->givePermissionTo('material.viewAny');
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutDelete);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/materials/{$material->id}");

        $response->assertForbidden();
    });
});

// ============================================================================
// AVAILABLE ZONES TESTS
// ============================================================================

describe('availableZones', function (): void {
    it('returns only zones that allow materials on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $zoneNoMaterials = Zone::factory()->forSite($this->regularSite)->create([
            'name' => 'No Materials Zone',
            'allow_material' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials/available-zones');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);

        $zoneIds = collect($response->json('data'))->pluck('id');
        expect($zoneIds)->toContain($this->zoneWithMaterials->id);
        expect($zoneIds)->not->toContain($zoneNoMaterials->id);
        expect($zoneIds)->not->toContain($this->otherSiteZone->id);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/materials/available-zones');

        $response->assertUnauthorized();
    });
});

// ============================================================================
// AVAILABLE RECIPIENTS TESTS
// ============================================================================

describe('availableRecipients', function (): void {
    it('returns only users with access to current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $siteUser = User::factory()->create(['first_name' => 'Site', 'last_name' => 'User']);
        $siteUser->sites()->attach($this->regularSite);

        $otherUser = User::factory()->create(['first_name' => 'Other', 'last_name' => 'User']);
        $otherUser->sites()->attach($this->otherSite);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/materials/available-recipients');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'full_name', 'email']]]);

        $userIds = collect($response->json('data'))->pluck('id');
        expect($userIds)->toContain($user->id); // Current user has access
        expect($userIds)->toContain($siteUser->id);
        expect($userIds)->not->toContain($otherUser->id);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/materials/available-recipients');

        $response->assertUnauthorized();
    });
});

// ============================================================================
// STATS TESTS
// ============================================================================

describe('stats', function (): void {
    it('returns monthly statistics for a material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/stats");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'months',
                    'incidents',
                    'maintenances',
                    'cleanings',
                    'item_movements',
                ],
            ]);

        // Should have 12 months of data
        expect($response->json('data.months'))->toHaveCount(12);
        expect($response->json('data.incidents'))->toHaveCount(12);
        expect($response->json('data.maintenances'))->toHaveCount(12);
        expect($response->json('data.cleanings'))->toHaveCount(12);
        expect($response->json('data.item_movements'))->toHaveCount(12);
    });

    it('returns correct counts for material with data', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()
            ->forZone($this->zoneWithMaterials)
            ->createdBy($user)
            ->create();

        // Create 2 incidents this month
        Incident::factory()
            ->count(2)
            ->forMaterial($material)
            ->forSite($this->regularSite)
            ->create(['created_at' => now()]);

        // Create 1 maintenance this month
        Maintenance::factory()
            ->forMaterial($material)
            ->forSite($this->regularSite)
            ->create(['created_at' => now()]);

        // Create 3 cleanings this month
        Cleaning::factory()
            ->count(3)
            ->forMaterial($material)
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['created_at' => now()]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/stats");

        $response->assertOk();

        // Check that the last month (current) has the correct counts
        $incidents = $response->json('data.incidents');
        $maintenances = $response->json('data.maintenances');
        $cleanings = $response->json('data.cleanings');

        expect($incidents[11])->toBe(2); // Last month (current)
        expect($maintenances[11])->toBe(1);
        expect($cleanings[11])->toBe(3);
    });

    it('denies access for material on different site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/stats");

        $response->assertForbidden();
    });

    it('denies access for user without view permission', function (): void {
        $noPermRole = Role::create(['name' => 'no-perm', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $noPermRole);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/stats");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}/stats");

        $response->assertUnauthorized();
    });
});

// ============================================================================
// QR CODE TESTS
// ============================================================================

describe('qrCode', function (): void {
    it('generates QR code for material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/qr-code");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'svg',
                    'url',
                    'size',
                ],
            ])
            ->assertJsonPath('data.size', 200);
    });

    it('respects size parameter', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/qr-code?size=300");

        $response->assertOk()
            ->assertJsonPath('data.size', 300);
    });

    it('limits size between 100 and 400', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $responseTooSmall = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/qr-code?size=50");

        $responseTooSmall->assertOk()
            ->assertJsonPath('data.size', 100);

        $responseTooBig = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/qr-code?size=1000");

        $responseTooBig->assertOk()
            ->assertJsonPath('data.size', 400);
    });

    it('cannot generate QR code for material from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaterial = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$otherMaterial->id}/qr-code");

        $response->assertForbidden();
    });

    it('denies access for user without generateQrcode permission', function (): void {
        $noPermRole = Role::create(['name' => 'no-qr-perm', 'guard_name' => 'web']);
        $noPermRole->givePermissionTo('material.view');
        $user = createUserOnRegularSite($this->regularSite, $noPermRole);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/qr-code");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}/qr-code");

        $response->assertUnauthorized();
    });
});

// ============================================================================
// INCIDENTS ENDPOINT TESTS
// ============================================================================

describe('incidents', function (): void {
    it('returns paginated incidents for a material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        Incident::factory()->count(3)->forMaterial($material)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/incidents");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'description', 'status', 'severity', 'material_id', 'created_at'],
                ],
                'links',
                'meta',
            ]);
    });

    it('filters incidents by search term', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        Incident::factory()->forMaterial($material)->create(['description' => 'Broken door']);
        Incident::factory()->forMaterial($material)->create(['description' => 'Leaking pipe']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/incidents?search=door");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        expect($response->json('data.0.description'))->toBe('Broken door');
    });

    it('cannot view incidents for material from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaterial = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$otherMaterial->id}/incidents");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}/incidents");

        $response->assertUnauthorized();
    });
});

// ============================================================================
// MAINTENANCES ENDPOINT TESTS
// ============================================================================

describe('maintenances', function (): void {
    it('returns paginated maintenances for a material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        Maintenance::factory()->count(3)->forSite($this->regularSite)->forMaterial($material)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/maintenances");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'description', 'type', 'status', 'material_id', 'created_at'],
                ],
                'links',
                'meta',
            ]);
    });

    it('filters maintenances by search term', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        Maintenance::factory()->forSite($this->regularSite)->forMaterial($material)->create(['description' => 'Annual checkup']);
        Maintenance::factory()->forSite($this->regularSite)->forMaterial($material)->create(['description' => 'Emergency repair']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/maintenances?search=checkup");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        expect($response->json('data.0.description'))->toBe('Annual checkup');
    });

    it('cannot view maintenances for material from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaterial = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$otherMaterial->id}/maintenances");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}/maintenances");

        $response->assertUnauthorized();
    });
});

// ============================================================================
// CLEANINGS ENDPOINT TESTS
// ============================================================================

describe('cleanings', function (): void {
    it('returns paginated cleanings for a material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        Cleaning::factory()->count(3)->forSite($this->regularSite)->forMaterial($material)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/cleanings");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'description', 'type', 'material_id', 'created_at'],
                ],
                'links',
                'meta',
            ]);
    });

    it('filters cleanings by search term', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        Cleaning::factory()->forSite($this->regularSite)->forMaterial($material)->create(['description' => 'Deep cleaning']);
        Cleaning::factory()->forSite($this->regularSite)->forMaterial($material)->create(['description' => 'Quick wipe']);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/cleanings?search=deep");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        expect($response->json('data.0.description'))->toBe('Deep cleaning');
    });

    it('cannot view cleanings for material from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaterial = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$otherMaterial->id}/cleanings");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}/cleanings");

        $response->assertUnauthorized();
    });
});

// ============================================================================
// ITEMS ENDPOINT TESTS
// ============================================================================

describe('items', function (): void {
    it('returns paginated items for a material', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        $items = Item::factory()->count(3)->forSite($this->regularSite)->create();
        $material->items()->attach($items->pluck('id'));

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/items");

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'reference', 'current_stock', 'created_at'],
                ],
                'links',
                'meta',
            ]);
    });

    it('filters items by search term', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();
        $item1 = Item::factory()->forSite($this->regularSite)->create(['name' => 'Filter Oil']);
        $item2 = Item::factory()->forSite($this->regularSite)->create(['name' => 'Brake Pad']);
        $material->items()->attach([$item1->id, $item2->id]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$material->id}/items?search=filter");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        expect($response->json('data.0.name'))->toBe('Filter Oil');
    });

    it('cannot view items for material from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherMaterial = Material::factory()->forZone($this->otherSiteZone)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/materials/{$otherMaterial->id}/items");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $material = Material::factory()->forZone($this->zoneWithMaterials)->create();

        $response = $this->getJson("/api/v1/materials/{$material->id}/items");

        $response->assertUnauthorized();
    });
});
