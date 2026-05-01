<?php

declare(strict_types=1);

namespace XetaSuite\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use XetaSuite\Mcp\Tools\CalendarEvents\CreateCalendarEventTool;
use XetaSuite\Mcp\Tools\CalendarEvents\DeleteCalendarEventTool;
use XetaSuite\Mcp\Tools\CalendarEvents\GetCalendarEventTool;
use XetaSuite\Mcp\Tools\CalendarEvents\ListCalendarEventsTool;
use XetaSuite\Mcp\Tools\CalendarEvents\UpdateCalendarEventTool;
use XetaSuite\Mcp\Tools\Cleanings\CreateCleaningTool;
use XetaSuite\Mcp\Tools\Cleanings\DeleteCleaningTool;
use XetaSuite\Mcp\Tools\Cleanings\GetCleaningTool;
use XetaSuite\Mcp\Tools\Cleanings\ListCleaningsTool;
use XetaSuite\Mcp\Tools\Cleanings\UpdateCleaningTool;
use XetaSuite\Mcp\Tools\Incidents\CreateIncidentTool;
use XetaSuite\Mcp\Tools\Incidents\DeleteIncidentTool;
use XetaSuite\Mcp\Tools\Incidents\GetIncidentTool;
use XetaSuite\Mcp\Tools\Incidents\ListIncidentsTool;
use XetaSuite\Mcp\Tools\Incidents\UpdateIncidentTool;
use XetaSuite\Mcp\Tools\ItemMovements\CreateItemMovementTool;
use XetaSuite\Mcp\Tools\ItemMovements\DeleteItemMovementTool;
use XetaSuite\Mcp\Tools\ItemMovements\GetItemMovementTool;
use XetaSuite\Mcp\Tools\ItemMovements\ListItemMovementsTool;
use XetaSuite\Mcp\Tools\ItemMovements\UpdateItemMovementTool;
use XetaSuite\Mcp\Tools\Items\CreateItemTool;
use XetaSuite\Mcp\Tools\Items\DeleteItemTool;
use XetaSuite\Mcp\Tools\Items\GetItemTool;
use XetaSuite\Mcp\Tools\Items\ListItemsTool;
use XetaSuite\Mcp\Tools\Items\UpdateItemTool;
use XetaSuite\Mcp\Tools\Maintenances\CreateMaintenanceTool;
use XetaSuite\Mcp\Tools\Maintenances\DeleteMaintenanceTool;
use XetaSuite\Mcp\Tools\Maintenances\GetMaintenanceTool;
use XetaSuite\Mcp\Tools\Maintenances\ListMaintenancesTool;
use XetaSuite\Mcp\Tools\Maintenances\UpdateMaintenanceTool;
use XetaSuite\Mcp\Tools\Materials\CreateMaterialTool;
use XetaSuite\Mcp\Tools\Materials\DeleteMaterialTool;
use XetaSuite\Mcp\Tools\Materials\GetMaterialTool;
use XetaSuite\Mcp\Tools\Materials\ListMaterialsTool;
use XetaSuite\Mcp\Tools\Materials\UpdateMaterialTool;

#[Name('XetaSuite')]
#[Version('1.0.0')]
#[Instructions(
    'XetaSuite MCP server — multi-tenant ERP. ' .
    'Always pass site_id in each tool call, or ensure your account has a current site configured. ' .
    'Authentication uses Sanctum Bearer token (Personal Access Token). ' .
    'All operations are scoped to the resolved site and subject to role-based permissions.'
)]
class XetaSuiteServer extends Server
{
    protected array $tools = [
        // Calendar Events
        ListCalendarEventsTool::class,
        GetCalendarEventTool::class,
        CreateCalendarEventTool::class,
        UpdateCalendarEventTool::class,
        DeleteCalendarEventTool::class,

        // Cleanings
        ListCleaningsTool::class,
        GetCleaningTool::class,
        CreateCleaningTool::class,
        UpdateCleaningTool::class,
        DeleteCleaningTool::class,

        // Incidents
        ListIncidentsTool::class,
        GetIncidentTool::class,
        CreateIncidentTool::class,
        UpdateIncidentTool::class,
        DeleteIncidentTool::class,

        // Maintenances
        ListMaintenancesTool::class,
        GetMaintenanceTool::class,
        CreateMaintenanceTool::class,
        UpdateMaintenanceTool::class,
        DeleteMaintenanceTool::class,

        // Materials
        ListMaterialsTool::class,
        GetMaterialTool::class,
        CreateMaterialTool::class,
        UpdateMaterialTool::class,
        DeleteMaterialTool::class,

        // Items
        ListItemsTool::class,
        GetItemTool::class,
        CreateItemTool::class,
        UpdateItemTool::class,
        DeleteItemTool::class,

        // Item Movements
        ListItemMovementsTool::class,
        GetItemMovementTool::class,
        CreateItemMovementTool::class,
        UpdateItemMovementTool::class,
        DeleteItemMovementTool::class,
    ];
}
