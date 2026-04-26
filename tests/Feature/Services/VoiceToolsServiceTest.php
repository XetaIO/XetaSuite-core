<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\Cleaning;
use XetaSuite\Models\Incident;
use XetaSuite\Models\Item;
use XetaSuite\Models\ItemMovement;
use XetaSuite\Models\Maintenance;
use XetaSuite\Models\Material;
use XetaSuite\Models\Site;
use XetaSuite\Models\Zone;
use XetaSuite\Services\VoiceToolsService;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);
    $this->otherSite = Site::factory()->create(['is_headquarters' => false]);

    $role = Role::create(['name' => 'voice-user', 'guard_name' => 'web']);
    $this->user = createUserOnRegularSite($this->site, $role);

    $this->zone = Zone::factory()->uniqueForSite($this->site)->create();
    $this->material = Material::factory()->forSite($this->site)->forZone($this->zone)->create();
    $this->item = Item::factory()->forSite($this->site)->create();

    $this->service = app(VoiceToolsService::class);
});

// ---------------------------------------------------------------------------
// Calendar Events
// ---------------------------------------------------------------------------

describe('calendar events', function (): void {
    test('list_calendar_events returns only events for the current site', function (): void {
        CalendarEvent::factory()->forSite($this->site)->count(3)->create();
        CalendarEvent::factory()->forSite($this->otherSite)->count(2)->create();

        $result = json_decode($this->service->execute($this->user, 'list_calendar_events', []), true);

        expect($result['total'])->toBe(3);
    });

    test('list_calendar_events respects per_page limit', function (): void {
        CalendarEvent::factory()->forSite($this->site)->count(10)->create();

        $result = json_decode($this->service->execute($this->user, 'list_calendar_events', ['per_page' => 2]), true);

        expect($result['total'])->toBe(2);
    });

    test('list_calendar_events filters by start date', function (): void {
        CalendarEvent::factory()->forSite($this->site)->create(['start_at' => '2020-01-01 09:00:00', 'end_at' => '2020-01-01 11:00:00']);
        CalendarEvent::factory()->forSite($this->site)->create(['start_at' => '2030-01-01 09:00:00', 'end_at' => '2030-01-01 11:00:00']);

        $result = json_decode($this->service->execute($this->user, 'list_calendar_events', ['start' => '2025-01-01']), true);

        expect($result['total'])->toBe(1);
    });

    test('get_calendar_event returns the correct event', function (): void {
        $event = CalendarEvent::factory()->forSite($this->site)->create(['title' => 'Team Meeting']);

        $result = json_decode($this->service->execute($this->user, 'get_calendar_event', ['event_id' => $event->id]), true);

        expect($result['title'])->toBe('Team Meeting');
    });

    test('get_calendar_event returns error JSON when event not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_calendar_event', ['event_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_calendar_event creates a new event in the database', function (): void {
        $this->service->execute($this->user, 'create_calendar_event', [
            'title' => 'New Year Party',
            'start_at' => '2025-12-31T22:00:00',
            'end_at' => '2026-01-01T02:00:00',
        ]);

        $this->assertDatabaseHas('calendar_events', [
            'title' => 'New Year Party',
            'site_id' => $this->site->id,
        ]);
    });

    test('create_calendar_event returns success JSON with id', function (): void {
        $result = json_decode($this->service->execute($this->user, 'create_calendar_event', [
            'title' => 'Sprint Review',
            'start_at' => '2025-07-15T14:00:00',
        ]), true);

        expect($result['success'])->toBeTrue();
        expect($result)->toHaveKey('id');
    });

    test('update_calendar_event updates the event title', function (): void {
        $event = CalendarEvent::factory()->forSite($this->site)->create(['title' => 'Old Title']);

        $this->service->execute($this->user, 'update_calendar_event', [
            'event_id' => $event->id,
            'title' => 'New Title',
        ]);

        $this->assertDatabaseHas('calendar_events', ['id' => $event->id, 'title' => 'New Title']);
    });

    test('delete_calendar_event removes the event from the database', function (): void {
        $event = CalendarEvent::factory()->forSite($this->site)->create();

        $this->service->execute($this->user, 'delete_calendar_event', ['event_id' => $event->id]);

        $this->assertDatabaseMissing('calendar_events', ['id' => $event->id]);
    });
});

// ---------------------------------------------------------------------------
// Cleanings
// ---------------------------------------------------------------------------

describe('cleanings', function (): void {
    test('list_cleanings returns only cleanings for the current site', function (): void {
        Cleaning::factory()->forSite($this->site)->count(4)->create();
        Cleaning::factory()->forSite($this->otherSite)->count(2)->create();

        $result = json_decode($this->service->execute($this->user, 'list_cleanings', []), true);

        expect($result['total'])->toBe(4);
    });

    test('list_cleanings filters by type', function (): void {
        Cleaning::factory()->forSite($this->site)->withType('casual')->count(3)->create();
        Cleaning::factory()->forSite($this->site)->withType('weekly')->count(2)->create();

        $result = json_decode($this->service->execute($this->user, 'list_cleanings', ['type' => 'weekly']), true);

        expect($result['total'])->toBe(2);
    });

    test('list_cleanings filters by search term', function (): void {
        Cleaning::factory()->forSite($this->site)->create(['description' => 'Nettoyage salle de réunion']);
        Cleaning::factory()->forSite($this->site)->create(['description' => 'Nettoyage cuisine']);

        $result = json_decode($this->service->execute($this->user, 'list_cleanings', ['search' => 'cuisine']), true);

        expect($result['total'])->toBe(1);
    });

    test('get_cleaning returns the correct cleaning', function (): void {
        $cleaning = Cleaning::factory()->forSite($this->site)->create(['description' => 'Clean windows']);

        $result = json_decode($this->service->execute($this->user, 'get_cleaning', ['cleaning_id' => $cleaning->id]), true);

        expect($result['description'])->toBe('Clean windows');
    });

    test('get_cleaning returns error JSON when not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_cleaning', ['cleaning_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_cleaning creates a new cleaning in the database', function (): void {
        $this->service->execute($this->user, 'create_cleaning', [
            'material_id' => $this->material->id,
            'description' => 'Cleaned the filters',
            'type' => 'casual',
        ]);

        $this->assertDatabaseHas('cleanings', [
            'material_id' => $this->material->id,
            'description' => 'Cleaned the filters',
            'site_id' => $this->site->id,
        ]);
    });

    test('update_cleaning updates the description', function (): void {
        $cleaning = Cleaning::factory()->forSite($this->site)->create(['description' => 'Old desc']);

        $this->service->execute($this->user, 'update_cleaning', [
            'cleaning_id' => $cleaning->id,
            'description' => 'Updated desc',
        ]);

        $this->assertDatabaseHas('cleanings', ['id' => $cleaning->id, 'description' => 'Updated desc']);
    });

    test('delete_cleaning removes the cleaning from the database', function (): void {
        $cleaning = Cleaning::factory()->forSite($this->site)->create();

        $this->service->execute($this->user, 'delete_cleaning', ['cleaning_id' => $cleaning->id]);

        $this->assertDatabaseMissing('cleanings', ['id' => $cleaning->id]);
    });
});

// ---------------------------------------------------------------------------
// Incidents
// ---------------------------------------------------------------------------

describe('incidents', function (): void {
    test('list_incidents returns only incidents for the current site', function (): void {
        Incident::factory()->forSite($this->site)->count(3)->create();
        Incident::factory()->forSite($this->otherSite)->count(5)->create();

        $result = json_decode($this->service->execute($this->user, 'list_incidents', []), true);

        expect($result['total'])->toBe(3);
    });

    test('list_incidents filters by search term', function (): void {
        Incident::factory()->forSite($this->site)->create(['description' => 'Water leak in basement']);
        Incident::factory()->forSite($this->site)->create(['description' => 'Electrical fault']);

        $result = json_decode($this->service->execute($this->user, 'list_incidents', ['search' => 'water']), true);

        expect($result['total'])->toBe(1);
    });

    test('get_incident returns the correct incident', function (): void {
        $incident = Incident::factory()->forSite($this->site)->create(['description' => 'Pipe burst']);

        $result = json_decode($this->service->execute($this->user, 'get_incident', ['incident_id' => $incident->id]), true);

        expect($result['description'])->toBe('Pipe burst');
    });

    test('get_incident returns error JSON when not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_incident', ['incident_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_incident with material_id creates incident for that material', function (): void {
        $result = json_decode($this->service->execute($this->user, 'create_incident', [
            'description' => 'Panel overheating',
            'material_id' => $this->material->id,
        ]), true);

        expect($result['success'])->toBeTrue();
        $this->assertDatabaseHas('incidents', ['description' => 'Panel overheating']);
    });

    test('create_incident without material_id auto-resolves the first site material', function (): void {
        $result = json_decode($this->service->execute($this->user, 'create_incident', [
            'description' => 'Auto-resolved material incident',
        ]), true);

        expect($result['success'])->toBeTrue();
        $this->assertDatabaseHas('incidents', [
            'description' => 'Auto-resolved material incident',
            'material_id' => $this->material->id,
        ]);
    });

    test('update_incident updates the status', function (): void {
        $incident = Incident::factory()->forSite($this->site)->create(['status' => 'open']);

        $this->service->execute($this->user, 'update_incident', [
            'incident_id' => $incident->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('incidents', ['id' => $incident->id, 'status' => 'in_progress']);
    });

    test('delete_incident removes the incident from the database', function (): void {
        $incident = Incident::factory()->forSite($this->site)->create();

        $this->service->execute($this->user, 'delete_incident', ['incident_id' => $incident->id]);

        $this->assertDatabaseMissing('incidents', ['id' => $incident->id]);
    });
});

// ---------------------------------------------------------------------------
// Item Movements
// ---------------------------------------------------------------------------

describe('item movements', function (): void {
    test('list_item_movements returns only movements for the current site', function (): void {
        $otherItem = Item::factory()->forSite($this->otherSite)->create();
        ItemMovement::factory()->forItem($this->item)->entry()->count(3)->create();
        ItemMovement::factory()->forItem($otherItem)->entry()->count(2)->create();

        $result = json_decode($this->service->execute($this->user, 'list_item_movements', []), true);

        expect($result['total'])->toBe(3);
    });

    test('list_item_movements filters by type', function (): void {
        ItemMovement::factory()->forItem($this->item)->entry()->count(2)->create();
        ItemMovement::factory()->forItem($this->item)->exit()->count(1)->create();

        $result = json_decode($this->service->execute($this->user, 'list_item_movements', ['type' => 'exit']), true);

        expect($result['total'])->toBe(1);
    });

    test('get_item_movement returns the correct movement', function (): void {
        $movement = ItemMovement::factory()->forItem($this->item)->entry()->create(['quantity' => 42]);

        $result = json_decode($this->service->execute($this->user, 'get_item_movement', ['movement_id' => $movement->id]), true);

        expect($result['quantity'])->toBe(42);
    });

    test('get_item_movement returns error JSON when not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_item_movement', ['movement_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_item_movement creates an entry movement in the database', function (): void {
        $result = json_decode($this->service->execute($this->user, 'create_item_movement', [
            'item_id' => $this->item->id,
            'type' => 'entry',
            'quantity' => 10,
        ]), true);

        expect($result['success'])->toBeTrue();
        expect($result['quantity'])->toBe(10);
    });

    test('update_item_movement updates the quantity', function (): void {
        $movement = ItemMovement::factory()->forItem($this->item)->entry()->create(['quantity' => 5]);

        $this->service->execute($this->user, 'update_item_movement', [
            'movement_id' => $movement->id,
            'quantity' => 20,
        ]);

        $this->assertDatabaseHas('item_movements', ['id' => $movement->id, 'quantity' => 20]);
    });

    test('delete_item_movement removes the movement from the database', function (): void {
        $movement = ItemMovement::factory()->forItem($this->item)->entry()->create();

        $this->service->execute($this->user, 'delete_item_movement', ['movement_id' => $movement->id]);

        $this->assertDatabaseMissing('item_movements', ['id' => $movement->id]);
    });
});

// ---------------------------------------------------------------------------
// Items
// ---------------------------------------------------------------------------

describe('items', function (): void {
    test('list_items returns only items for the current site', function (): void {
        Item::factory()->forSite($this->site)->count(3)->create();
        Item::factory()->forSite($this->otherSite)->count(4)->create();

        $result = json_decode($this->service->execute($this->user, 'list_items', []), true);

        // +1 for $this->item created in beforeEach
        expect($result['total'])->toBe(4);
    });

    test('list_items maps current_stock correctly', function (): void {
        Item::factory()->forSite($this->site)->create([
            'name' => 'Stock Item',
            'item_entry_total' => 100,
            'item_exit_total' => 35,
        ]);

        $result = json_decode($this->service->execute($this->user, 'list_items', ['search' => 'Stock Item']), true);

        expect($result['data'][0]['current_stock'])->toBe(65);
    });

    test('list_items filters by search term', function (): void {
        Item::factory()->forSite($this->site)->create(['name' => 'Hydraulic pump']);
        Item::factory()->forSite($this->site)->create(['name' => 'Electric motor']);

        $result = json_decode($this->service->execute($this->user, 'list_items', ['search' => 'hydraulic']), true);

        expect($result['total'])->toBe(1);
        expect($result['data'][0]['name'])->toBe('Hydraulic pump');
    });

    test('get_item returns the correct item', function (): void {
        $item = Item::factory()->forSite($this->site)->create(['name' => 'Special Valve']);

        $result = json_decode($this->service->execute($this->user, 'get_item', ['item_id' => $item->id]), true);

        expect($result['name'])->toBe('Special Valve');
    });

    test('get_item returns error JSON when not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_item', ['item_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_item creates a new item in the database', function (): void {
        $this->service->execute($this->user, 'create_item', [
            'name' => 'Screw M6',
            'reference' => 'SCR-M6',
        ]);

        $this->assertDatabaseHas('items', [
            'name' => 'Screw M6',
            'reference' => 'SCR-M6',
            'site_id' => $this->site->id,
        ]);
    });

    test('update_item updates the item name', function (): void {
        $item = Item::factory()->forSite($this->site)->create(['name' => 'Old Name']);

        $this->service->execute($this->user, 'update_item', [
            'item_id' => $item->id,
            'name' => 'New Name',
        ]);

        $this->assertDatabaseHas('items', ['id' => $item->id, 'name' => 'New Name']);
    });

    test('delete_item removes the item from the database', function (): void {
        $item = Item::factory()->forSite($this->site)->create();

        $this->service->execute($this->user, 'delete_item', ['item_id' => $item->id]);

        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    });
});

// ---------------------------------------------------------------------------
// Maintenances
// ---------------------------------------------------------------------------

describe('maintenances', function (): void {
    test('list_maintenances returns only maintenances for the current site', function (): void {
        Maintenance::factory()->forSite($this->site)->count(3)->create();
        Maintenance::factory()->forSite($this->otherSite)->count(2)->create();

        $result = json_decode($this->service->execute($this->user, 'list_maintenances', []), true);

        expect($result['total'])->toBe(3);
    });

    test('list_maintenances filters by status', function (): void {
        Maintenance::factory()->forSite($this->site)->create(['status' => 'planned']);
        Maintenance::factory()->forSite($this->site)->create(['status' => 'planned']);
        Maintenance::factory()->forSite($this->site)->create(['status' => 'completed']);

        $result = json_decode($this->service->execute($this->user, 'list_maintenances', ['status' => 'planned']), true);

        expect($result['total'])->toBe(2);
    });

    test('list_maintenances filters by search term', function (): void {
        Maintenance::factory()->forSite($this->site)->create(['description' => 'Replace cooling fan']);
        Maintenance::factory()->forSite($this->site)->create(['description' => 'Oil change']);

        $result = json_decode($this->service->execute($this->user, 'list_maintenances', ['search' => 'cooling']), true);

        expect($result['total'])->toBe(1);
    });

    test('get_maintenance returns the correct maintenance', function (): void {
        $maintenance = Maintenance::factory()->forSite($this->site)->create(['description' => 'Fix pump bearings']);

        $result = json_decode($this->service->execute($this->user, 'get_maintenance', ['maintenance_id' => $maintenance->id]), true);

        expect($result['description'])->toBe('Fix pump bearings');
    });

    test('get_maintenance returns error JSON when not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_maintenance', ['maintenance_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_maintenance creates a new maintenance in the database', function (): void {
        $this->service->execute($this->user, 'create_maintenance', [
            'description' => 'Annual inspection',
            'material_id' => $this->material->id,
            'reason' => 'Scheduled inspection',
        ]);

        $this->assertDatabaseHas('maintenances', [
            'description' => 'Annual inspection',
            'site_id' => $this->site->id,
        ]);
    });

    test('update_maintenance updates the status', function (): void {
        $maintenance = Maintenance::factory()->forSite($this->site)->create(['status' => 'planned']);

        $this->service->execute($this->user, 'update_maintenance', [
            'maintenance_id' => $maintenance->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('maintenances', ['id' => $maintenance->id, 'status' => 'in_progress']);
    });

    test('delete_maintenance removes the maintenance from the database', function (): void {
        $maintenance = Maintenance::factory()->forSite($this->site)->create();

        $this->service->execute($this->user, 'delete_maintenance', ['maintenance_id' => $maintenance->id]);

        $this->assertDatabaseMissing('maintenances', ['id' => $maintenance->id]);
    });
});

// ---------------------------------------------------------------------------
// Materials
// ---------------------------------------------------------------------------

describe('materials', function (): void {
    test('list_materials returns only materials for the current site', function (): void {
        $otherZone = Zone::factory()->uniqueForSite($this->otherSite)->create();
        Material::factory()->forSite($this->site)->forZone($this->zone)->count(3)->create();
        Material::factory()->forSite($this->otherSite)->forZone($otherZone)->count(4)->create();

        $result = json_decode($this->service->execute($this->user, 'list_materials', []), true);

        // +1 for $this->material created in beforeEach
        expect($result['total'])->toBe(4);
    });

    test('list_materials filters by search term', function (): void {
        Material::factory()->forSite($this->site)->forZone($this->zone)->create(['name' => 'Compressor A12']);
        Material::factory()->forSite($this->site)->forZone($this->zone)->create(['name' => 'Pump B7']);

        $result = json_decode($this->service->execute($this->user, 'list_materials', ['search' => 'compressor']), true);

        expect($result['total'])->toBe(1);
    });

    test('get_material returns the correct material', function (): void {
        $material = Material::factory()->forSite($this->site)->forZone($this->zone)->create(['name' => 'Heat exchanger']);

        $result = json_decode($this->service->execute($this->user, 'get_material', ['material_id' => $material->id]), true);

        expect($result['name'])->toBe('Heat exchanger');
    });

    test('get_material returns error JSON when not found', function (): void {
        $result = json_decode($this->service->execute($this->user, 'get_material', ['material_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });

    test('create_material creates a new material in the database', function (): void {
        $this->service->execute($this->user, 'create_material', [
            'name' => 'New Conveyor',
            'zone_id' => $this->zone->id,
        ]);

        $this->assertDatabaseHas('materials', [
            'name' => 'New Conveyor',
            'zone_id' => $this->zone->id,
        ]);
    });

    test('update_material updates the material name', function (): void {
        $material = Material::factory()->forSite($this->site)->forZone($this->zone)->create(['name' => 'Old Name']);

        $this->service->execute($this->user, 'update_material', [
            'material_id' => $material->id,
            'name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('materials', ['id' => $material->id, 'name' => 'Updated Name']);
    });

    test('delete_material removes the material from the database', function (): void {
        $material = Material::factory()->forSite($this->site)->forZone($this->zone)->create();

        $this->service->execute($this->user, 'delete_material', ['material_id' => $material->id]);

        $this->assertDatabaseMissing('materials', ['id' => $material->id]);
    });
});

// ---------------------------------------------------------------------------
// Dashboard
// ---------------------------------------------------------------------------

describe('dashboard', function (): void {
    test('get_dashboard_stats returns correct counts', function (): void {
        // Open incidents (not resolved/closed)
        Incident::factory()->forSite($this->site)->create(['status' => 'open']);
        Incident::factory()->forSite($this->site)->create(['status' => 'in_progress']);
        Incident::factory()->forSite($this->site)->create(['status' => 'resolved']);

        // Pending maintenances (not resolved/cancelled)
        Maintenance::factory()->forSite($this->site)->create(['status' => 'planned']);
        Maintenance::factory()->forSite($this->site)->create(['status' => 'completed']);

        // Low-stock items (exit >= entry)
        Item::factory()->forSite($this->site)->create(['item_entry_total' => 5, 'item_exit_total' => 10]);
        Item::factory()->forSite($this->site)->create(['item_entry_total' => 10, 'item_exit_total' => 10]);
        Item::factory()->forSite($this->site)->create(['item_entry_total' => 10, 'item_exit_total' => 5]);

        $result = json_decode($this->service->execute($this->user, 'get_dashboard_stats', []), true);

        expect($result['open_incidents'])->toBe(2);
        expect($result['pending_maintenances'])->toBe(2);
        expect($result['low_stock_items'])->toBe(3);
    });

    test('get_dashboard_stats does not include data from other sites', function (): void {
        Incident::factory()->forSite($this->otherSite)->create(['status' => 'open']);

        $result = json_decode($this->service->execute($this->user, 'get_dashboard_stats', []), true);

        expect($result['open_incidents'])->toBe(0);
    });
});

// ---------------------------------------------------------------------------
// Unknown tool / exception handling
// ---------------------------------------------------------------------------

describe('error handling', function (): void {
    test('returns error JSON for an unknown tool name', function (): void {
        $result = json_decode($this->service->execute($this->user, 'nonexistent_tool', []), true);

        expect($result)->toHaveKey('error');
        expect($result['error'])->toContain('nonexistent_tool');
    });

    test('catches exceptions and returns error JSON', function (): void {
        // Pass a non-existent ID to trigger a ModelNotFoundException
        $result = json_decode($this->service->execute($this->user, 'get_cleaning', ['cleaning_id' => 999999]), true);

        expect($result)->toHaveKey('error');
    });
});
