<?php

declare(strict_types=1);

namespace XetaSuite\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use XetaSuite\Actions\Items\CreateItem;
use XetaSuite\Actions\Items\DeleteItem;
use XetaSuite\Actions\Items\UpdateItem;
use XetaSuite\Http\Requests\V1\Items\StoreItemRequest;
use XetaSuite\Http\Requests\V1\Items\UpdateItemRequest;
use XetaSuite\Http\Resources\V1\ItemMovements\ItemMovementResource;
use XetaSuite\Http\Resources\V1\Items\ItemDetailResource;
use XetaSuite\Http\Resources\V1\Items\ItemResource;
use XetaSuite\Http\Resources\V1\Materials\MaterialResource;
use XetaSuite\Models\Item;
use XetaSuite\Services\ItemMovementService;
use XetaSuite\Services\ItemPriceService;
use XetaSuite\Services\ItemService;
use XetaSuite\Services\QrCodeService;

class ItemController extends Controller
{
    public function __construct(
        private readonly ItemService $itemService,
        private readonly ItemMovementService $movementService,
        private readonly ItemPriceService $priceService,
        private readonly QrCodeService $qrCodeService
    ) {
    }

    /**
     * Display a listing of items.
     * Only shows items on the user's current site.
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Item::class);

        $items = $this->itemService->getPaginatedItems([
            'search' => request('search'),
            'company_id' => request('company_id'),
            'stock_status' => request('stock_status'),
            'sort_by' => request('sort_by'),
            'sort_direction' => request('sort_direction'),
        ]);

        return ItemResource::collection($items);
    }

    /**
     * Store a newly created item.
     *
     * @param  StoreItemRequest  $request  The incoming request.
     * @param  CreateItem  $action  The action to create the item.
     */
    public function store(StoreItemRequest $request, CreateItem $action): ItemDetailResource
    {
        $item = $action->handle($request->user(), $request->validated());

        return new ItemDetailResource(
            $item->load(['company', 'creator', 'materials', 'recipients'])
        );
    }

    /**
     * Display the specified item.
     *
     * @param  Item  $item  The item to display.
     */
    public function show(Item $item): ItemDetailResource
    {
        $this->authorize('view', $item);

        $item->load(['company', 'creator', 'editor', 'materials', 'recipients']);

        return new ItemDetailResource($item);
    }

    /**
     * Update the specified item.
     *
     * @param  UpdateItemRequest  $request  The incoming request.
     * @param  Item  $item  The item to update.
     * @param  UpdateItem  $action  The action to update the item.
     */
    public function update(UpdateItemRequest $request, Item $item, UpdateItem $action): ItemDetailResource
    {
        $item = $action->handle($item, $request->user(), $request->validated());

        return new ItemDetailResource(
            $item->load(['company', 'creator', 'editor', 'materials', 'recipients'])
        );
    }

    /**
     * Delete the specified item.
     *
     * @param  Item  $item  The item to delete.
     * @param  DeleteItem  $action  The action to delete the item.
     */
    public function destroy(Item $item, DeleteItem $action): JsonResponse
    {
        $this->authorize('delete', $item);

        $result = $action->handle($item);

        if (! $result['success']) {
            return response()->json([
                'message' => $result['message'],
            ], 422);
        }

        return response()->json(null, 204);
    }

    /**
     * Get monthly statistics for an item.
     *
     * @param  Item  $item  The item to get statistics for.
     */
    public function stats(Item $item): JsonResponse
    {
        $this->authorize('view', $item);

        $stats = $this->itemService->getMonthlyStats($item);

        return response()->json([
            'stats' => $stats,
        ]);
    }

    /**
     * Get paginated materials for an item.
     *
     * @param  Item  $item  The item to get materials for.
     */
    public function materials(Item $item): AnonymousResourceCollection
    {
        $this->authorize('view', $item);

        $materials = $item->materials()
            ->with('zone')
            ->orderBy('name')
            ->paginate((int) request('per_page', 5));

        return MaterialResource::collection($materials);
    }

    /**
     * Get paginated movements for an item.
     *
     * @param  Item  $item  The item to get movements for.
     */
    public function movements(Item $item): AnonymousResourceCollection
    {
        $this->authorize('view', $item);

        $movements = $this->movementService->getPaginatedMovements($item, [
            'type' => request('type'),
            'sort_by' => request('sort_by'),
            'sort_direction' => request('sort_direction'),
        ]);

        return ItemMovementResource::collection($movements);
    }

    /**
     * Get price history with statistics for an item.
     *
     * @param  Item  $item  The item to get price history for.
     */
    public function priceHistory(Item $item): JsonResponse
    {
        $this->authorize('view', $item);

        $limit = (int) request('limit', 20);
        $limit = max(5, min(100, $limit));

        $data = $this->priceService->getPriceHistoryWithStats($item, $limit);

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Generate and return a QR code for the item.
     */
    public function qrCode(Item $item): JsonResponse
    {
        $this->authorize('generateQrCode', $item);

        $size = (int) request('size', 200);
        $url = config('app.spa_url').'?source=qr&item='.$item->id;

        return response()->json([
            'data' => $this->qrCodeService->generateSvg($url, $size),
        ]);
    }

    /**
     * Get available companies (item providers) for item assignment.
     *
     * @param  Request  $request  The incoming request.
     */
    public function availableCompanies(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        $companies = $this->itemService->getAvailableCompanies(
            $request->query('search'),
            $request->query('include_id') ? (int) $request->query('include_id') : null
        );

        return response()->json([
            'companies' => $companies,
        ]);
    }

    /**
     * Get available materials for item assignment.
     *
     * @param  Request  $request  The incoming request.
     */
    public function availableMaterials(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        $materials = $this->itemService->getAvailableMaterials($request->query('search'));

        return response()->json([
            'materials' => $materials,
        ]);
    }

    /**
     * Get available recipients for item assignment.
     *
     * @param  Request  $request  The incoming request.
     */
    public function availableRecipients(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Item::class);

        $recipients = $this->itemService->getAvailableRecipients($request->query('search'));

        return response()->json([
            'recipients' => $recipients,
        ]);
    }
}
