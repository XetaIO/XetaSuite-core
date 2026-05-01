<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\CalendarEvent;
use XetaSuite\Models\EventCategory;
use XetaSuite\Models\Site;

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
        'calendarEvent.viewAny',
        'calendarEvent.view',
        'calendarEvent.create',
        'calendarEvent.update',
        'calendarEvent.delete',
    ];

    foreach ($permissions as $permission) {
        Permission::create(['name' => $permission, 'guard_name' => 'web']);
    }

    // Create role with all permissions
    $this->role = Role::create(['name' => 'calendar-manager', 'guard_name' => 'web']);
    $this->role->givePermissionTo($permissions);

    // Create a category for the site
    $this->category = EventCategory::factory()->forSite($this->regularSite)->create([
        'name' => 'Meetings',
        'color' => '#3b82f6',
    ]);
});

// ============================================================================
// INDEX TESTS
// ============================================================================

describe('index', function (): void {
    it('returns only events for user current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        CalendarEvent::factory()->count(3)->forSite($this->regularSite)->createdBy($user)->create();
        CalendarEvent::factory()->count(2)->forSite($this->otherSite)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('returns list with proper structure', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        CalendarEvent::factory()->count(3)->forSite($this->regularSite)->createdBy($user)->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'color',
                        'start_at',
                        'end_at',
                        'all_day',
                        'created_by_name',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    });

    it('can filter events by date range', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        CalendarEvent::factory()->forSite($this->regularSite)->createdBy($user)->create([
            'title' => 'Past Event',
            'start_at' => now()->subMonth(),
            'end_at' => now()->subMonth()->addHour(),
        ]);
        CalendarEvent::factory()->forSite($this->regularSite)->createdBy($user)->create([
            'title' => 'Current Event',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);
        CalendarEvent::factory()->forSite($this->regularSite)->createdBy($user)->create([
            'title' => 'Future Event',
            'start_at' => now()->addMonth(),
            'end_at' => now()->addMonth()->addHour(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events?start=' . now()->startOfWeek()->toDateString() . '&end=' . now()->endOfWeek()->addDay()->toDateString());

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Current Event');
    });

    it('denies access without permission', function (): void {
        $roleWithoutPermission = Role::create(['name' => 'no-access', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutPermission);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events');

        $response->assertForbidden();
    });
});

// ============================================================================
// STORE TESTS
// ============================================================================

describe('store', function (): void {
    it('creates a new event successfully', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $data = [
            'title' => 'Team Meeting',
            'description' => 'Weekly sync meeting',
            'event_category_id' => $this->category->id,
            'start_at' => now()->addDay()->toDateTimeString(),
            'end_at' => now()->addDay()->addHour()->toDateTimeString(),
            'all_day' => false,
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', $data);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Team Meeting')
            ->assertJsonPath('data.created_by_name', $user->full_name);

        $this->assertDatabaseHas('calendar_events', [
            'site_id' => $this->regularSite->id,
            'title' => 'Team Meeting',
            'created_by_id' => $user->id,
        ]);
    });

    it('creates an all-day event', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'title' => 'Holiday',
                'start_at' => now()->addWeek()->startOfDay()->toDateTimeString(),
                'all_day' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.all_day', true);
    });

    it('uses event color when provided', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'title' => 'Custom Color Event',
                'start_at' => now()->addDay()->toDateTimeString(),
                'color' => '#ff5733',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.color', '#ff5733');
    });

    it('uses category color when no custom color', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'title' => 'Category Color Event',
                'start_at' => now()->addDay()->toDateTimeString(),
                'event_category_id' => $this->category->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.color', '#3b82f6');
    });

    it('validates required title', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'start_at' => now()->addDay()->toDateTimeString(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    });

    it('validates required start_at', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'title' => 'No Start Date',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start_at']);
    });

    it('validates end_at after start_at', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'title' => 'Invalid Dates',
                'start_at' => now()->addDay()->toDateTimeString(),
                'end_at' => now()->subDay()->toDateTimeString(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at']);
    });

    it('denies create from headquarters', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/calendar-events', [
                'title' => 'HQ Event',
                'start_at' => now()->addDay()->toDateTimeString(),
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// SHOW TESTS
// ============================================================================

describe('show', function (): void {
    it('returns event details with relationships', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $event = CalendarEvent::factory()
            ->forSite($this->regularSite)
            ->withCategory($this->category)
            ->createdBy($user)
            ->create([
                'title' => 'Test Event',
            ]);

        $response = $this->actingAs($user)
            ->getJson("/api/v1/calendar-events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', 'Test Event')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'description',
                    'color',
                    'start_at',
                    'end_at',
                    'all_day',
                    'category',
                    'created_by',
                ],
            ]);
    });

    it('denies access to event from other site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $otherEvent = CalendarEvent::factory()->forSite($this->otherSite)->create();

        $response = $this->actingAs($user)
            ->getJson("/api/v1/calendar-events/{$otherEvent->id}");

        $response->assertForbidden();
    });
});

// ============================================================================
// UPDATE TESTS
// ============================================================================

describe('update', function (): void {
    it('updates event successfully', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $event = CalendarEvent::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'title' => 'Old Title',
            ]);

        $response = $this->actingAs($user)
            ->putJson("/api/v1/calendar-events/{$event->id}", [
                'title' => 'New Title',
                'description' => 'Updated description',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('calendar_events', [
            'id' => $event->id,
            'title' => 'New Title',
        ]);
    });

    it('denies update from headquarters', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);
        $event = CalendarEvent::factory()->forSite($this->regularSite)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/calendar-events/{$event->id}", [
                'title' => 'Updated',
            ]);

        $response->assertForbidden();
    });

    it('denies update to event from other site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $otherEvent = CalendarEvent::factory()->forSite($this->otherSite)->create();

        $response = $this->actingAs($user)
            ->putJson("/api/v1/calendar-events/{$otherEvent->id}", [
                'title' => 'Hacked',
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// UPDATE DATES TESTS (Drag & Drop)
// ============================================================================

describe('updateDates', function (): void {
    it('updates only dates for drag and drop', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $event = CalendarEvent::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'title' => 'Original Title',
                'start_at' => now(),
                'end_at' => now()->addHour(),
            ]);

        $newStart = now()->addDay();
        $newEnd = now()->addDay()->addHour();

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/calendar-events/{$event->id}/dates", [
                'start_at' => $newStart->toDateTimeString(),
                'end_at' => $newEnd->toDateTimeString(),
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Original Title');

        $event->refresh();
        expect($event->start_at->format('Y-m-d'))->toBe($newStart->format('Y-m-d'));
        expect($event->end_at->format('Y-m-d'))->toBe($newEnd->format('Y-m-d'));
    });

    it('updates all_day flag when resizing', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $event = CalendarEvent::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create([
                'all_day' => false,
            ]);

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/calendar-events/{$event->id}/dates", [
                'start_at' => now()->startOfDay()->toDateTimeString(),
                'all_day' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.all_day', true);
    });

    it('denies updateDates from headquarters', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);
        $event = CalendarEvent::factory()->forSite($this->regularSite)->create();

        $response = $this->actingAs($user)
            ->patchJson("/api/v1/calendar-events/{$event->id}/dates", [
                'start_at' => now()->addDay()->toDateTimeString(),
            ]);

        $response->assertForbidden();
    });
});

// ============================================================================
// DELETE TESTS
// ============================================================================

describe('destroy', function (): void {
    it('deletes event successfully', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $event = CalendarEvent::factory()
            ->forSite($this->regularSite)
            ->createdBy($user)
            ->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/calendar-events/{$event->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('calendar_events', [
            'id' => $event->id,
        ]);
    });

    it('denies delete from headquarters', function (): void {
        $user = createUserOnHeadquarters($this->headquarters, $this->role);
        $event = CalendarEvent::factory()->forSite($this->regularSite)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/calendar-events/{$event->id}");

        $response->assertForbidden();
    });

    it('denies delete of event from other site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);
        $otherEvent = CalendarEvent::factory()->forSite($this->otherSite)->create();

        $response = $this->actingAs($user)
            ->deleteJson("/api/v1/calendar-events/{$otherEvent->id}");

        $response->assertForbidden();
    });
});

// ============================================================================
// AVAILABLE EVENT CATEGORIES TESTS
// ============================================================================

describe('availableEventCategories', function (): void {
    it('returns categories for current site', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        // Create additional categories
        EventCategory::factory()->forSite($this->regularSite)->create(['name' => 'Training']);
        EventCategory::factory()->forSite($this->otherSite)->create(['name' => 'Other Site Category']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events/available-event-categories');

        $response->assertOk()
            ->assertJsonCount(2, 'data') // Meetings + Training (from beforeEach + this test)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'color'],
                ],
            ]);
    });

    it('can search categories by name', function (): void {
        $user = createUserOnRegularSite($this->regularSite, $this->role);

        EventCategory::factory()->forSite($this->regularSite)->create(['name' => 'Training']);
        EventCategory::factory()->forSite($this->regularSite)->create(['name' => 'Team Building']);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events/available-event-categories?search=train');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Training');
    });

    it('denies access without permission', function (): void {
        $roleWithoutPermission = Role::create(['name' => 'no-access', 'guard_name' => 'web']);
        $user = createUserOnRegularSite($this->regularSite, $roleWithoutPermission);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/calendar-events/available-event-categories');

        $response->assertForbidden();
    });
});
