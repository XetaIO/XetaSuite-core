<?php

declare(strict_types=1);

namespace XetaSuite\Services;

use Throwable;
use XetaSuite\Actions\Calendar\CreateCalendarEvent;
use XetaSuite\Actions\Calendar\DeleteCalendarEvent;
use XetaSuite\Actions\Calendar\UpdateCalendarEvent;
use XetaSuite\Actions\Cleanings\CreateCleaning;
use XetaSuite\Actions\Cleanings\DeleteCleaning;
use XetaSuite\Actions\Cleanings\UpdateCleaning;
use XetaSuite\Actions\Incidents\CreateIncident;
use XetaSuite\Actions\Incidents\DeleteIncident;
use XetaSuite\Actions\Incidents\UpdateIncident;
use XetaSuite\Actions\ItemMovements\CreateItemMovement;
use XetaSuite\Actions\ItemMovements\DeleteItemMovement;
use XetaSuite\Actions\ItemMovements\UpdateItemMovement;
use XetaSuite\Actions\Items\CreateItem;
use XetaSuite\Actions\Items\DeleteItem;
use XetaSuite\Actions\Items\UpdateItem;
use XetaSuite\Actions\Maintenances\CreateMaintenance;
use XetaSuite\Actions\Maintenances\DeleteMaintenance;
use XetaSuite\Actions\Maintenances\UpdateMaintenance;
use XetaSuite\Actions\Materials\CreateMaterial;
use XetaSuite\Actions\Materials\DeleteMaterial;
use XetaSuite\Actions\Materials\UpdateMaterial;
use XetaSuite\Models\User;
use XetaSuite\Services\VoiceTools\HandlesCalendarEvents;
use XetaSuite\Services\VoiceTools\HandlesCleanings;
use XetaSuite\Services\VoiceTools\HandlesDashboard;
use XetaSuite\Services\VoiceTools\HandlesIncidents;
use XetaSuite\Services\VoiceTools\HandlesItemMovements;
use XetaSuite\Services\VoiceTools\HandlesItems;
use XetaSuite\Services\VoiceTools\HandlesMaintenances;
use XetaSuite\Services\VoiceTools\HandlesMaterials;

class VoiceToolsService
{
    use HandlesCalendarEvents;
    use HandlesCleanings;
    use HandlesDashboard;
    use HandlesIncidents;
    use HandlesItemMovements;
    use HandlesItems;
    use HandlesMaintenances;
    use HandlesMaterials;

    public function __construct(
        private readonly CreateCalendarEvent $createCalendarEvent,
        private readonly UpdateCalendarEvent $updateCalendarEvent,
        private readonly DeleteCalendarEvent $deleteCalendarEvent,
        private readonly CreateCleaning $createCleaning,
        private readonly UpdateCleaning $updateCleaning,
        private readonly DeleteCleaning $deleteCleaning,
        private readonly CreateIncident $createIncident,
        private readonly UpdateIncident $updateIncident,
        private readonly DeleteIncident $deleteIncident,
        private readonly CreateItemMovement $createItemMovement,
        private readonly UpdateItemMovement $updateItemMovement,
        private readonly DeleteItemMovement $deleteItemMovement,
        private readonly CreateItem $createItem,
        private readonly UpdateItem $updateItem,
        private readonly DeleteItem $deleteItem,
        private readonly CreateMaintenance $createMaintenance,
        private readonly UpdateMaintenance $updateMaintenance,
        private readonly DeleteMaintenance $deleteMaintenance,
        private readonly CreateMaterial $createMaterial,
        private readonly UpdateMaterial $updateMaterial,
        private readonly DeleteMaterial $deleteMaterial,
    ) {
    }

    /**
     * Execute a tool call by name and return a JSON-encoded result string.
     *
     * @param  array<string, mixed>  $args
     */
    public function execute(User $user, string $name, array $args): string
    {
        try {
            return match ($name) {
                // Calendar Events
                'list_calendar_events' => $this->listCalendarEvents($user, $args),
                'get_calendar_event' => $this->getCalendarEvent($user, $args),
                'create_calendar_event' => $this->createCalendarEventAction($user, $args),
                'update_calendar_event' => $this->updateCalendarEventAction($user, $args),
                'delete_calendar_event' => $this->deleteCalendarEventAction($user, $args),
                // Cleanings
                'list_cleanings' => $this->listCleanings($user, $args),
                'get_cleaning' => $this->getCleaning($user, $args),
                'create_cleaning' => $this->createCleaningAction($user, $args),
                'update_cleaning' => $this->updateCleaningAction($user, $args),
                'delete_cleaning' => $this->deleteCleaningAction($user, $args),
                // Incidents
                'list_incidents' => $this->listIncidents($user, $args),
                'get_incident' => $this->getIncident($user, $args),
                'create_incident' => $this->createIncidentAction($user, $args),
                'update_incident' => $this->updateIncidentAction($user, $args),
                'delete_incident' => $this->deleteIncidentAction($user, $args),
                // Item Movements
                'list_item_movements' => $this->listItemMovements($user, $args),
                'get_item_movement' => $this->getItemMovement($user, $args),
                'create_item_movement' => $this->createItemMovementAction($user, $args),
                'update_item_movement' => $this->updateItemMovementAction($user, $args),
                'delete_item_movement' => $this->deleteItemMovementAction($user, $args),
                // Items
                'list_items' => $this->listItems($user, $args),
                'get_item' => $this->getItem($user, $args),
                'create_item' => $this->createItemAction($user, $args),
                'update_item' => $this->updateItemAction($user, $args),
                'delete_item' => $this->deleteItemAction($user, $args),
                // Maintenances
                'list_maintenances' => $this->listMaintenances($user, $args),
                'get_maintenance' => $this->getMaintenance($user, $args),
                'create_maintenance' => $this->createMaintenanceAction($user, $args),
                'update_maintenance' => $this->updateMaintenanceAction($user, $args),
                'delete_maintenance' => $this->deleteMaintenanceAction($user, $args),
                // Materials
                'list_materials' => $this->listMaterials($user, $args),
                'get_material' => $this->getMaterial($user, $args),
                'create_material' => $this->createMaterialAction($user, $args),
                'update_material' => $this->updateMaterialAction($user, $args),
                'delete_material' => $this->deleteMaterialAction($user, $args),
                // Dashboard
                'get_dashboard_stats' => $this->getDashboardStats($user, $args),
                default => json_encode(['error' => "Outil inconnu : {$name}"]),
            };
        } catch (Throwable $e) {
            return json_encode(['error' => $e->getMessage()]);
        }
    }











}
