<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use XetaSuite\Actions\Materials\CreateMaterial;
use XetaSuite\Actions\Materials\DeleteMaterial;
use XetaSuite\Actions\Materials\UpdateMaterial;
use XetaSuite\Http\Requests\V1\Materials\StoreMaterialRequest;
use XetaSuite\Http\Requests\V1\Materials\UpdateMaterialRequest;
use XetaSuite\Http\Resources\V1\Cleanings\CleaningResource;
use XetaSuite\Http\Resources\V1\Incidents\IncidentResource;
use XetaSuite\Http\Resources\V1\Items\ItemResource;
use XetaSuite\Http\Resources\V1\Maintenances\MaintenanceResource;
use XetaSuite\Http\Resources\V1\Materials\MaterialDetailResource;
use XetaSuite\Http\Resources\V1\Materials\MaterialResource;
use XetaSuite\Models\Material;
use XetaSuite\Services\MaterialService;
use XetaSuite\Services\QrCodeService;

class MaterialController extends Controller
{
    public function __construct(
        private readonly MaterialService $materialService,
        private readonly QrCodeService $qrCodeService
    ) {
    }

    /**
     * Display a listing of materials.
     * Only shows materials for zones on the user's current site.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Material::class);

        $materials = $this->materialService->getPaginatedMaterials([
            'zone_id' => request('zone_id'),
            'search' => request('search'),
            'sort_by' => request('sort_by'),
            'sort_direction' => request('sort_direction'),
        ]);

        return MaterialResource::collection($materials);
    }

    /**
     * Store a newly created material.
     *
     * @param  StoreMaterialRequest  $request  The incoming request.
     * @param  CreateMaterial  $action  The action to create the material.
     */
    public function store(StoreMaterialRequest $request, CreateMaterial $action): MaterialDetailResource
    {
        $material = $action->handle($request->user(), $request->validated());

        return new MaterialDetailResource(
            $material->load(['zone', 'creator', 'recipients'])
        );
    }

    /**
     * Display the specified material.
     *
     * @param  Material  $material  The material to display.
     */
    public function show(Material $material): MaterialDetailResource
    {
        $this->authorize('view', $material);

        $material->load(['zone', 'creator', 'recipients']);

        return new MaterialDetailResource($material);
    }

    /**
     * Update the specified material.
     *
     * @param  UpdateMaterialRequest  $request  The incoming request.
     * @param  Material  $material  The material to update.
     * @param  UpdateMaterial  $action  The action to update the material.
     */
    public function update(UpdateMaterialRequest $request, Material $material, UpdateMaterial $action): MaterialDetailResource
    {
        $material = $action->handle($material, $request->validated());

        return new MaterialDetailResource(
            $material->load(['zone', 'creator', 'recipients'])
        );
    }

    /**
     * Delete the specified material.
     *
     * @param  Material  $material  The material to delete.
     * @param  DeleteMaterial  $action  The action to delete the material.
     */
    public function destroy(Material $material, DeleteMaterial $action): JsonResponse
    {
        $this->authorize('delete', $material);

        $action->handle($material);

        return response()->json(null, 204);
    }

    /**
     * Get available zones for material creation/update.
     * Only zones that allow materials on user's current site.
     */
    public function availableZones(): JsonResponse
    {
        $this->authorize('viewAny', Material::class);

        $zones = $this->materialService->getAvailableZones();

        return response()->json([
            'data' => $zones->map(fn ($zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
            ]),
        ]);
    }

    /**
     * Get available recipients for cleaning alerts.
     */
    public function availableRecipients(): JsonResponse
    {
        $this->authorize('viewAny', Material::class);

        $recipients = $this->materialService->getAvailableRecipients();

        return response()->json([
            'data' => $recipients->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
            ]),
        ]);
    }

    /**
     * Get monthly statistics for a material over the last 12 months.
     * Returns counts for incidents, maintenances, cleanings, and item movements (exit).
     *
     * @param  Material  $material  The material to get statistics for.
     */
    public function stats(Material $material): JsonResponse
    {
        $this->authorize('view', $material);

        $stats = $this->materialService->getMonthlyStats($material);

        return response()->json(['data' => $stats]);
    }

    /**
     * Generate and return a QR code for the material.
     *
     * @param  Material  $material  The material to generate the QR code for.
     */
    public function qrCode(Material $material): JsonResponse
    {
        $this->authorize('generateQrCode', $material);

        $size = (int) request('size', 200);
        $url = config('app.spa_url').'?source=qr&material='.$material->id;

        return response()->json([
            'data' => $this->qrCodeService->generateSvg($url, $size),
        ]);
    }

    /**
     * Get incidents for a specific material.
     *
     * @param  Material  $material  The material to get incidents for.
     */
    public function incidents(Material $material): AnonymousResourceCollection
    {
        $this->authorize('view', $material);

        $incidents = $this->materialService->getMaterialIncidents($material, [
            'per_page' => request('per_page', 10),
            'search' => request('search'),
        ]);

        return IncidentResource::collection($incidents);
    }

    /**
     * Get maintenances for a specific material.
     *
     * @param  Material  $material  The material to get maintenances for.
     */
    public function maintenances(Material $material): AnonymousResourceCollection
    {
        $this->authorize('view', $material);

        $maintenances = $this->materialService->getMaterialMaintenances($material, [
            'per_page' => request('per_page', 10),
            'search' => request('search'),
        ]);

        return MaintenanceResource::collection($maintenances);
    }

    /**
     * Get cleanings for a specific material.
     *
     * @param  Material  $material  The material to get cleanings for.
     */
    public function cleanings(Material $material): AnonymousResourceCollection
    {
        $this->authorize('view', $material);

        $cleanings = $this->materialService->getMaterialCleanings($material, [
            'per_page' => request('per_page', 10),
            'search' => request('search'),
        ]);

        return CleaningResource::collection($cleanings);
    }

    /**
     * Get items for a specific material.
     *
     * @param  Material  $material  The material to get items for.
     */
    public function items(Material $material): AnonymousResourceCollection
    {
        $this->authorize('view', $material);

        $items = $this->materialService->getMaterialItems($material, [
            'per_page' => request('per_page', 10),
            'search' => request('search'),
        ]);

        return ItemResource::collection($items);
    }
}
