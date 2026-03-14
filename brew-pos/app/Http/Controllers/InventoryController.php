<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryLog;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InventoryController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request): JsonResponse
    {
        $inventory = Inventory::with('product.category')
            ->when($request->low_stock, fn($q) => $q->whereColumn('quantity', '<=', 'low_stock_threshold'))
            ->orderByDesc('updated_at')
            ->paginate(30);

        return response()->json($inventory);
    }

    public function adjust(Request $request, Inventory $inventory): JsonResponse
    {
        $data = $request->validate([
            'quantity_change' => 'required|integer',
            'type'            => 'required|in:restock,adjustment,waste',
            'notes'           => 'nullable|string',
        ]);

        $log = $this->inventoryService->adjust(
            $inventory,
            $data['quantity_change'],
            $data['type'],
            $data['notes'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'inventory' => $inventory->fresh()->load('product'),
            'log'       => $log,
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $logs = InventoryLog::with(['product', 'user'])
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($logs);
    }
}
