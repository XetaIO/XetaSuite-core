<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\Company;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\Zone;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create headquarters site
    $this->headquarters = Site::factory()->create(['is_headquarters' => true]);

    // Create a regular site
    $this->regularSite = Site::factory()->create(['is_headquarters' => false]);

    // Create permissions
    $permissions = [
        'company.viewAny',
        'company.view',
        'company.create',
        'company.update',
        'company.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create role with all permissions
    $this->role = Role::create(['name' => 'company-manager', 'guard_name' => 'web']);
    $this->role->givePermissionTo($permissions);
});

// ============================================================================
// INDEX TESTS
// ============================================================================

describe('index', function (): void {
    it('returns paginated list of companies for authorized user on headquarters', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        Company::factory()->count(25)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'maintenance_count', 'created_at'],
                ],
                'links',
                'meta',
            ])
            ->assertJsonCount(20, 'data'); // Default pagination
    });

    it('returns companies ordered by name by default', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        Company::factory()->create(['name' => 'Zebra Corp']);
        Company::factory()->create(['name' => 'Alpha Inc']);
        Company::factory()->create(['name' => 'Beta Ltd']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        expect($names)->toBe(['Alpha Inc', 'Beta Ltd', 'Zebra Corp']);
    });

    it('can search companies by name', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        Company::factory()->create(['name' => 'Alpha Corp']);
        Company::factory()->create(['name' => 'Beta Services']);
        Company::factory()->create(['name' => 'Gamma Alpha']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies?search=alpha');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search companies by description', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        Company::factory()->create(['name' => 'Company A', 'description' => 'Maintenance services']);
        Company::factory()->create(['name' => 'Company B', 'description' => 'Cleaning services']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies?search=maintenance');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Company A');
    });

    it('includes maintenance_count in response', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create();

        // Create zone and material for maintenances
        $zone = Zone::factory()->forSite($this->headquarters)->create(['allow_material' => true]);
        $material = Material::factory()->forZone($zone)->create();

        // Create maintenances and attach to company
        $maintenances = Maintenance::factory()->count(3)->forSite($this->headquarters)->forMaterial($material)->createdBy($user)->create();
        $company->maintenances()->attach($maintenances->pluck('id'));

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies');

        $response->assertOk()
            ->assertJsonPath('data.0.maintenance_count', 3);
    });

    it('denies access for user without viewAny permission', function (): void {
        $roleWithoutViewAny = Role::create(['name' => 'limited', 'guard_name' => 'web']);
        $user = createUserOnHeadquarters($this->headquarters, $roleWithoutViewAny);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies');

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/companies');

        $response->assertUnauthorized();
    });
});

// ============================================================================
// SHOW TESTS
// ============================================================================

describe('show', function (): void {
    it('returns company details for authorized user', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->createdBy($user)->create([
            'name' => 'Test Company',
            'description' => 'Test description',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'description', 'created_by_id', 'created_by_name', 'maintenance_count', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.name', 'Test Company')
            ->assertJsonPath('data.description', 'Test description');
    });

    it('includes creator information when loaded', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->createdBy($user)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/companies/{$company->id}");

        $response->assertOk()
            ->assertJsonPath('data.creator.id', $user->id)
            ->assertJsonPath('data.created_by_name', $user->full_name);
    });

    it('denies access for user without view permission', function (): void {
        $roleWithoutView = Role::create(['name' => 'no-view', 'guard_name' => 'web']);
        $roleWithoutView->givePermissionTo('company.viewAny');
        $user = createUserOnHeadquarters($this->headquarters, $roleWithoutView);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/companies/{$company->id}");

        $response->assertForbidden();
    });

    it('returns 404 for non-existent company', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/companies/99999');

        $response->assertNotFound();
    });
});

// ============================================================================
// STORE TESTS
// ============================================================================

describe('store', function (): void {
    it('can create a company with valid data', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => 'New Company',
                'description' => 'Company description',
                'types' => ['maintenance_provider'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'New Company')
            ->assertJsonPath('data.description', 'Company description')
            ->assertJsonPath('data.created_by_id', $user->id);

        $this->assertDatabaseHas('companies', [
            'name' => 'New Company',
            'description' => 'Company description',
            'created_by_id' => $user->id,
        ]);
    });

    it('can create a company without description', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => 'Company Without Description',
                'types' => ['item_provider'],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Company Without Description')
            ->assertJsonPath('data.description', null);
    });

    it('validates name is required', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'description' => 'Some description',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates name max length', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => str_repeat('a', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates description max length', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => 'Valid Name',
                'description' => str_repeat('a', 1001),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    });

    it('denies creation for user not on headquarters', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => 'New Company',
            ]);

        $response->assertForbidden();
    });

    it('denies creation for user without create permission', function (): void {
        $roleWithoutCreate = Role::create(['name' => 'no-create', 'guard_name' => 'web']);
        $roleWithoutCreate->givePermissionTo('company.viewAny');
        $user = createUserOnHeadquarters($this->headquarters, $roleWithoutCreate);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/companies', [
                'name' => 'New Company',
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// UPDATE TESTS
// ============================================================================

describe('update', function (): void {
    it('can update company name', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'New Name',
        ]);
    });

    it('can update company description', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create(['description' => 'Old description']);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'description' => 'New description',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.description', 'New description');
    });

    it('can set description to null', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create(['description' => 'Some description']);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'description' => null,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.description', null);
    });

    it('denies update for user not on headquarters', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'name' => 'New Name',
            ]);

        $response->assertForbidden();
    });

    it('denies update for user without update permission', function (): void {
        $roleWithoutUpdate = Role::create(['name' => 'no-update', 'guard_name' => 'web']);
        $roleWithoutUpdate->givePermissionTo('company.viewAny');
        $user = createUserOnHeadquarters($this->headquarters, $roleWithoutUpdate);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'name' => 'New Name',
            ]);

        $response->assertForbidden();
    });

    it('filters out invalid types when updating company', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create([
            'types' => ['item_provider'],
        ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'types' => ['item_provider', 'maintenance_provider'],
            ]);

        $response->assertOk();

        $company->refresh();
        expect($company->types->all())->toEqualCanonicalizing(['item_provider', 'maintenance_provider']);
    });

    it('rejects unknown enum values via validation', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/companies/{$company->id}", [
                'types' => ['hacker_value'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['types.0']);
    });
});

// ============================================================================
// DESTROY TESTS
// ============================================================================

describe('destroy', function (): void {
    it('can delete a company without maintenances', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
        ]);
    });

    it('cannot delete a company with maintenances', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $company = Company::factory()->create();

        // Create zone and material for maintenance
        $zone = Zone::factory()->forSite($this->headquarters)->create(['allow_material' => true]);
        $material = Material::factory()->forZone($zone)->create();

        $maintenance = Maintenance::factory()->forSite($this->headquarters)->forMaterial($material)->createdBy($user)->create();
        $company->maintenances()->attach($maintenance->id);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertUnprocessable();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
        ]);
    });

    it('denies delete for user not on headquarters', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertForbidden();
    });

    it('denies delete for user without delete permission', function (): void {
        $roleWithoutDelete = Role::create(['name' => 'no-delete', 'guard_name' => 'web']);
        $roleWithoutDelete->givePermissionTo('company.viewAny');
        $user = createUserOnHeadquarters($this->headquarters, $roleWithoutDelete);

        $company = Company::factory()->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/companies/{$company->id}");

        $response->assertForbidden();
    });
});
