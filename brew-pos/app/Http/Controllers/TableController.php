<?php

namespace App\Http\Controllers;

use App\Models\Table;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TableController extends Controller
{
    public function index(): JsonResponse
    {
        $tables = Table::with('activeOrder.items')
            ->orderBy('number')
            ->get()
            ->map(fn ($t) => $this->appendComputedStatus($t));

        return response()->json($tables);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50',
            'number'     => 'nullable|integer|unique:tables',
            'capacity'   => 'nullable|integer|min:1',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'width'      => 'nullable|integer|min:20|max:600',
            'height'     => 'nullable|integer|min:20|max:600',
            'shape'      => 'nullable|in:circle,square,rectangle',
            'rotation'   => 'nullable|numeric',
        ]);

        // Capacity optional in UI; default to 4 to satisfy NOT NULL column.
        $data['capacity'] = $data['capacity'] ?? 4;
        $data['status'] = 'available';
        $data['rotation'] = $data['rotation'] ?? 0;
        $table = Table::create($data);

        return response()->json($this->appendComputedStatus($table), 201);
    }

    public function show(Table $table): JsonResponse
    {
        $table->load('activeOrder.items.product');
        return response()->json($this->appendComputedStatus($table));
    }

    public function update(Request $request, Table $table): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:50',
            'number'     => 'sometimes|integer|unique:tables,number,' . $table->id,
            'capacity'   => 'sometimes|nullable|integer|min:1',
            'status'     => 'sometimes|in:available,occupied,reserved,inactive',
            'position_x' => 'nullable|integer',
            'position_y' => 'nullable|integer',
            'width'      => 'nullable|integer|min:20|max:600',
            'height'     => 'nullable|integer|min:20|max:600',
            'shape'      => 'nullable|in:circle,square,rectangle',
            'notes'      => 'nullable|string',
            'rotation'   => 'nullable|numeric',
        ]);

        // Avoid writing null into NOT NULL column; if capacity not provided or null, drop it.
        if (!array_key_exists('capacity', $data) || is_null($data['capacity'])) {
            unset($data['capacity']);
        }

        // If manually setting to available, cancel awareness of active orders
        if (isset($data['status']) && $data['status'] === 'available') {
            // Only allow if no active orders (or admin is forcing it)
            $hasActiveOrders = Order::where('table_id', $table->id)
                ->whereIn('status', ['pending', 'preparing', 'ready'])
                ->exists();

            if ($hasActiveOrders) {
                // Still allow — cashier is manually overriding
                // Just set the DB status; active_order will still show
            }
        }

        $table->update($data);
        $table->load('activeOrder.items');

        return response()->json($this->appendComputedStatus($table));
    }

    public function destroy(Table $table): JsonResponse
    {
        if ($table->activeOrder) {
            return response()->json(['message' => 'Cannot delete a table with an active order.'], 422);
        }

        $table->delete();

        return response()->json(['message' => 'Table deleted.']);
    }

    /**
     * Append computed_status so the frontend always gets a reliable status:
     *  - If there is an active (pending/preparing/ready) order → 'occupied'
     *  - Otherwise fall back to the DB status column
     */
    private function appendComputedStatus(Table $table): Table
    {
        $hasActiveOrder = $table->relationLoaded('activeOrder')
            ? (bool) $table->activeOrder
            : Order::where('table_id', $table->id)
                ->whereIn('status', ['pending', 'preparing', 'ready'])
                ->exists();

        $table->setAttribute('computed_status', $hasActiveOrder ? 'occupied' : $table->status);

        return $table;
    }
}
