<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\Item;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\Zone;
use XetaSuite\Services\Assistant\AssistantToolRegistry;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->siteA = Site::factory()->create(['is_headquarters' => false]);
    $this->siteB = Site::factory()->create(['is_headquarters' => false]);

    foreach (['assistant.use', 'item.viewAny', 'item.view', 'item.delete', 'item.update', 'material.viewAny', 'material.view', 'material.delete'] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $this->role = Role::create(['name' => 'cross-site-attacker', 'guard_name' => 'web']);
    $this->role->syncPermissions([
        'assistant.use', 'item.viewAny', 'item.view', 'item.delete', 'item.update',
        'material.viewAny', 'material.view', 'material.delete',
    ]);

    $this->user = createUserOnRegularSite($this->siteA, $this->role);
});

test('delete_item refuses to delete an item from another site', function (): void {
    $itemOnSiteB = Item::factory()->forSite($this->siteB)->create();

    $registry = app(AssistantToolRegistry::class);
    $result = $registry->execute($this->user, 'delete_item', ['item_id' => $itemOnSiteB->id]);

    expect($result)->toContain('permission_denied');
    expect(Item::find($itemOnSiteB->id))->not->toBeNull();
});

test('get_item refuses to read an item from another site', function (): void {
    $itemOnSiteB = Item::factory()->forSite($this->siteB)->create();

    $registry = app(AssistantToolRegistry::class);
    $result = $registry->execute($this->user, 'get_item', ['item_id' => $itemOnSiteB->id]);

    expect($result)->toContain('permission_denied');
});

test('delete_material refuses to delete a material from another site', function (): void {
    $zone = Zone::factory()->forSite($this->siteB)->create();
    $material = Material::factory()->forZone($zone)->create();

    $registry = app(AssistantToolRegistry::class);
    $result = $registry->execute($this->user, 'delete_material', ['material_id' => $material->id]);

    expect($result)->toContain('permission_denied');
    expect(Material::find($material->id))->not->toBeNull();
});
