<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\Site;
use XetaSuite\Models\Company;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create headquarters site
    $this->headquarters = Site::factory()->create(['is_headquarters' => true]);

    // Create a regular site
    $this->regularSite = Site::factory()->create(['is_headquarters' => false]);

    // Create another regular site
    $this->otherSite = Site::factory()->create(['is_headquarters' => false]);

    // Create companies as item providers
    $this->company = Company::factory()->asItemProvider()->create(['name' => 'Test Company']);
    $this->company2 = Company::factory()->asItemProvider()->create(['name' => 'Other Company']);

    // Create permissions
    $permissions = [
        'item.viewAny',
        'item.view',
        'item.create',
        'item.update',
        'item.delete',
        'item-movement.viewAny',
        'item-movement.view',
        'item-movement.create',
        'item-movement.update',
        'item-movement.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create role with all permissions
    $this->role = Role::create(['name' => 'item-manager', 'guard_name' => 'web']);
    $this->role->givePermissionTo($permissions);

    // Create view-only role
    $this->viewOnlyRole = Role::create(['name' => 'item-viewer', 'guard_name' => 'web']);
    $this->viewOnlyRole->givePermissionTo(['item.viewAny', 'item.view']);
});

// ============================================================================
// INDEX TESTS
// ============================================================================

describe('index', function (): void {
    it('returns movements for an item', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->exit()
            ->forItem($item)
            ->withQuantity(3, 5.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns paginated list with proper structure', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'quantity',
                        'unit_price',
                        'total_price',
                        'company_id',
                        'company_name',
                        'company_invoice_number',
                        'invoice_date',
                        'movement_date',
                        'notes',
                        'created_by_id',
                        'created_by_name',
                        'created_at',
                    ],
                ],
                'links',
                'meta',
            ]);
    });

    it('can filter movements by type', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->count(3)
            ->create();

        ItemMovement::factory()
            ->exit()
            ->forItem($item)
            ->withQuantity(2, 5.00)
            ->createdBy($user)
            ->count(2)
            ->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements?type=entry");

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements?type=exit");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('cannot view movements from another site item', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherItem = Item::factory()->forSite($this->otherSite)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$otherItem->id}/movements");

        $response->assertForbidden();
    });

    it('requires item.viewAny permission', function (): void {
        $roleWithoutPermission = Role::create(['name' => 'no-permission', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutPermission);

        $item = Item::factory()->forSite($this->regularSite)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements");

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $item = Item::factory()->forSite($this->regularSite)->create();

        $response = $this->getJson("/api/v1/items/{$item->id}/movements");

        $response->assertUnauthorized();
    });
});

// ============================================================================
// SHOW TESTS
// ============================================================================

describe('show', function (): void {
    it('returns movement details with proper structure', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements/{$movement->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'quantity',
                    'unit_price',
                    'total_price',
                    'company_id',
                    'company_name',
                    'company_invoice_number',
                    'invoice_date',
                    'movement_date',
                    'notes',
                    'created_by_id',
                    'created_by_name',
                    'created_at',
                ],
            ]);
    });

    it('cannot view movement from another site item', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherItem = Item::factory()->forSite($this->otherSite)->create();
        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($otherItem)
            ->withQuantity(5, 10.00)
            ->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$otherItem->id}/movements/{$movement->id}");

        $response->assertForbidden();
    });

    it('requires item.viewAny permission', function (): void {
        $roleWithoutPermission = Role::create(['name' => 'no-permission', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutPermission);

        $item = Item::factory()->forSite($this->regularSite)->create();
        $movement = ItemMovement::factory()->entry()->forItem($item)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/items/{$item->id}/movements/{$movement->id}");

        $response->assertForbidden();
    });
});

// ============================================================================
// STORE TESTS
// ============================================================================

describe('store', function (): void {
    it('can create entry movement with required fields', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'item_entry_total' => 0,
                'item_entry_count' => 0,
            ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", [
                'type' => 'entry',
                'quantity' => 10,
                'unit_price' => 5.50,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'entry')
            ->assertJsonPath('data.quantity', 10);

        $this->assertDatabaseHas('item_movements', [
            'item_id' => $item->id,
            'type' => 'entry',
            'quantity' => 10,
            'created_by_id' => $user->id,
        ]);

        // Check item totals updated
        $item->refresh();
        expect($item->item_entry_total)->toBe(10);
        expect($item->item_entry_count)->toBe(1);
    });

    it('can create entry movement with company', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", [
                'type' => 'entry',
                'quantity' => 5,
                'unit_price' => 10.00,
                'movement_date' => now()->toDateString(),
                'company_id' => $this->company->id,
                'company_invoice_number' => 'INV-2024-001',
                'invoice_date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.company_id', $this->company->id)
            ->assertJsonPath('data.company_invoice_number', 'INV-2024-001');
    });

    it('can create exit movement', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'item_entry_total' => 20,
                'item_exit_total' => 0,
                'item_exit_count' => 0,
            ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", [
                'type' => 'exit',
                'quantity' => 5,
                'unit_price' => 10.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'exit');

        // Check item totals updated
        $item->refresh();
        expect($item->item_exit_total)->toBe(5);
        expect($item->item_exit_count)->toBe(1);
    });

    it('can create exit movement without movement_date (defaults to now)', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'item_entry_total' => 20,
                'item_exit_total' => 0,
                'item_exit_count' => 0,
            ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", [
                'type' => 'exit',
                'quantity' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'exit');

        $movement = ItemMovement::query()->latest('id')->first();
        expect($movement->movement_date)->not->toBeNull();
    });

    it('validates required fields', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'quantity']);
    });

    it('validates type is entry or exit', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", [
                'type' => 'invalid',
                'quantity' => 10,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('cannot create movement for item from another site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherItem = Item::factory()->forSite($this->otherSite)->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$otherItem->id}/movements", [
                'type' => 'entry',
                'quantity' => 10,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertForbidden();
    });

    it('requires item.update permission', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->viewOnlyRole);

        $item = Item::factory()->forSite($this->regularSite)->create();

        $response = $this->actingAs($user)
            ->postJson("/api/v1/items/{$item->id}/movements", [
                'type' => 'entry',
                'quantity' => 10,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $item = Item::factory()->forSite($this->regularSite)->create();

        $response = $this->postJson("/api/v1/items/{$item->id}/movements", [
            'type' => 'entry',
            'quantity' => 10,
            'unit_price' => 5.00,
            'movement_date' => now()->toDateString(),
        ]);

        $response->assertUnauthorized();
    });
});

// ============================================================================
// UPDATE TESTS
// ============================================================================

describe('update', function (): void {
    it('can update movement quantity', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        // Store the initial totals
        $item->refresh();
        $initialTotal = $item->item_entry_total;

        $response = $this->actingAs($user)
            ->putJson("/api/v1/items/{$item->id}/movements/{$movement->id}", [
                'quantity' => 15,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity', 15);

        // Check item totals increased by the difference (15 - 10 = +5)
        $item->refresh();
        expect($item->item_entry_total)->toBe($initialTotal + 5);
    });

    it('can update movement company', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/items/{$item->id}/movements/{$movement->id}", [
                'quantity' => 10,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
                'company_id' => $this->company2->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.company_id', $this->company2->id);
    });

    it('cannot update movement from another site item', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherItem = Item::factory()->forSite($this->otherSite)->create();
        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($otherItem)
            ->withQuantity(10, 5.00)
            ->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/items/{$otherItem->id}/movements/{$movement->id}", [
                'quantity' => 15,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertForbidden();
    });

    it('requires item.update permission', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->viewOnlyRole);

        $item = Item::factory()->forSite($this->regularSite)->create();
        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(10, 5.00)
            ->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/items/{$item->id}/movements/{$movement->id}", [
                'quantity' => 15,
                'unit_price' => 5.00,
                'movement_date' => now()->toDateString(),
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// DESTROY TESTS
// ============================================================================

describe('destroy', function (): void {
    it('can delete movement and update item totals', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        // Create item with initial totals at 0 - the movement creation will set them
        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'item_entry_total' => 0,
                'item_entry_count' => 0,
            ]);

        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        // After movement creation, totals should be updated by observer
        $item->refresh();
        expect($item->item_entry_total)->toBe(10);
        expect($item->item_entry_count)->toBe(1);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/items/{$item->id}/movements/{$movement->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('item_movements', [
            'id' => $movement->id,
        ]);

        // Check item totals updated back to 0
        $item->refresh();
        expect($item->item_entry_total)->toBe(0);
        expect($item->item_entry_count)->toBe(0);
    });

    it('can delete exit movement and update totals', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        // Create item with initial totals at 0 - the movement creation will set them
        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'item_entry_total' => 20,
                'item_exit_total' => 0,
                'item_exit_count' => 0,
            ]);

        $movement = ItemMovement::factory()
            ->exit()
            ->forItem($item)
            ->withQuantity(5, 10.00)
            ->createdBy($user)
            ->create();

        // After movement creation, exit totals should be updated by observer
        $item->refresh();
        expect($item->item_exit_total)->toBe(5);
        expect($item->item_exit_count)->toBe(1);

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/items/{$item->id}/movements/{$movement->id}");

        $response->assertNoContent();

        // Check exit totals decreased back to 0
        $item->refresh();
        expect($item->item_exit_total)->toBe(0);
        expect($item->item_exit_count)->toBe(0);
    });

    it('cannot delete movement from another site item', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $otherItem = Item::factory()->forSite($this->otherSite)->create();
        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($otherItem)
            ->withQuantity(10, 5.00)
            ->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/items/{$otherItem->id}/movements/{$movement->id}");

        $response->assertForbidden();
    });

    it('requires item.update permission', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->viewOnlyRole);

        $item = Item::factory()->forSite($this->regularSite)->create();
        $movement = ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(10, 5.00)
            ->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/items/{$item->id}/movements/{$movement->id}");

        $response->assertForbidden();
    });
});

// ============================================================================
// AVAILABLE COMPANIES TESTS
// ============================================================================

describe('availableCompanies', function (): void {
    it('returns list of item provider companies', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/items/available-companies');

        $response->assertOk()
            ->assertJsonStructure([
                'companies' => [
                    '*' => ['id', 'name'],
                ],
            ]);

        // Should have at least our 2 companies
        expect(count($response->json('companies')))->toBeGreaterThanOrEqual(2);
    });

    it('requires item.viewAny permission', function (): void {
        $roleWithoutPermission = Role::create(['name' => 'no-permission', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutPermission);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/items/available-companies');

        $response->assertForbidden();
    });
});

// ============================================================================
// ALL MOVEMENTS TESTS
// ============================================================================

describe('all', function (): void {
    it('returns all movements for items on user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        // Create items on user's site
        $item1 = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['name' => 'Item One', 'reference' => 'REF-001']);

        $item2 = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['name' => 'Item Two', 'reference' => 'REF-002']);

        // Create movements for user's site items
        ItemMovement::factory()
            ->entry()
            ->forItem($item1)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->exit()
            ->forItem($item2)
            ->withQuantity(3, 5.00)
            ->createdBy($user)
            ->create();

        // Create item and movement on another site (should NOT be returned)
        $otherItem = Item::factory()
            ->forSite($this->otherSite)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($otherItem)
            ->withQuantity(5, 10.00)
            ->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('returns proper structure with item information', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['name' => 'Test Item', 'reference' => 'REF-123']);

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'quantity',
                        'unit_price',
                        'total_price',
                        'company_id',
                        'company_name',
                        'company_invoice_number',
                        'invoice_date',
                        'movement_date',
                        'notes',
                        'created_by_id',
                        'created_by_name',
                        'created_at',
                        'item_id',
                        'item' => [
                            'id',
                            'name',
                            'reference',
                        ],
                    ],
                ],
                'links',
                'meta',
            ]);

        expect($response->json('data.0.item.name'))->toBe('Test Item');
        expect($response->json('data.0.item.reference'))->toBe('REF-123');
    });

    it('can filter by type', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->count(3)
            ->create();

        ItemMovement::factory()
            ->exit()
            ->forItem($item)
            ->withQuantity(2, 5.00)
            ->createdBy($user)
            ->count(2)
            ->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements?type=entry');

        $response->assertOk()
            ->assertJsonCount(3, 'data');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements?type=exit');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search by item name', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item1 = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['name' => 'Cleaning Product']);

        $item2 = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create(['name' => 'Maintenance Tool']);

        ItemMovement::factory()
            ->entry()
            ->forItem($item1)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item2)
            ->withQuantity(5, 10.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements?search=Cleaning');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search by company name', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company) // Test Company
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->fromCompany($this->company2) // Other Company
            ->withQuantity(5, 10.00)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements?search=Test');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can sort by different fields', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $item = Item::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(5, 5.00)
            ->createdBy($user)
            ->create(['movement_date' => now()->subDays(2)]);

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(20, 5.00)
            ->createdBy($user)
            ->create(['movement_date' => now()->subDay()]);

        ItemMovement::factory()
            ->entry()
            ->forItem($item)
            ->withQuantity(10, 5.00)
            ->createdBy($user)
            ->create(['movement_date' => now()]);

        // Sort by quantity ascending
        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements?sort_by=quantity&sort_direction=asc');

        $response->assertOk();
        $quantities = collect($response->json('data'))->pluck('quantity')->toArray();
        expect($quantities)->toBe([5, 10, 20]);

        // Sort by quantity descending
        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements?sort_by=quantity&sort_direction=desc');

        $response->assertOk();
        $quantities = collect($response->json('data'))->pluck('quantity')->toArray();
        expect($quantities)->toBe([20, 10, 5]);
    });

    it('requires item.viewAny permission', function (): void {
        $roleWithoutPermission = Role::create(['name' => 'no-permission', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutPermission);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements');

        $response->assertForbidden();
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/v1/item-movements');

        $response->assertUnauthorized();
    });

    it('returns empty list when no movements exist', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/item-movements');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });
});
