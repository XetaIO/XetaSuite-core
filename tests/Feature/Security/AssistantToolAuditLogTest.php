<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use XetaSuite\Models\Item;
use XetaSuite\Models\Site;
use XetaSuite\Services\Assistant\AssistantToolRegistry;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->site = Site::factory()->create(['is_headquarters' => false]);
    foreach (['assistant.use', 'item.viewAny', 'item.view', 'item.delete'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $role = Role::create(['name' => 'tool-user', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use', 'item.viewAny', 'item.view', 'item.delete']);
    $this->user = createUserOnRegularSite($this->site, $role);
});

test('successful tool calls are written to the audit log', function (): void {
    $item = Item::factory()->forSite($this->site)->create();

    Log::spy();

    app(AssistantToolRegistry::class)->execute($this->user, 'get_item', ['item_id' => $item->id]);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($item): bool {
            return $message === 'assistant.tool_call'
                && $context['outcome'] === 'ok'
                && $context['tool'] === 'get_item'
                && $context['user_id'] === $this->user->id
                && ($context['args']['item_id'] ?? null) === $item->id;
        })->once();
});

test('denied tool calls are written to the audit log with denied outcome', function (): void {
    $role = Role::create(['name' => 'no-delete-audit', 'guard_name' => 'web']);
    $role->syncPermissions(['assistant.use', 'item.viewAny']);
    $user = createUserOnRegularSite($this->site, $role);

    $item = Item::factory()->forSite($this->site)->create();

    Log::spy();

    app(AssistantToolRegistry::class)->execute($user, 'delete_item', ['item_id' => $item->id]);

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context): bool {
            return $message === 'assistant.tool_call' && $context['outcome'] === 'denied';
        })->once();
});
