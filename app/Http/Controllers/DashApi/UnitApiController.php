<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Controllers\DashApi\Concerns\ReordersRows;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Resources\Dash\UnitResource;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitApiController extends Controller
{
    use PaginatesJson, ReordersRows;

    public function __construct(private readonly UnitService $service)
    {
        $this->authorizeResource(Unit::class, 'unit');
    }

    /**
     * Unpaginated (see PaginatesJson::full()) — sortableList.js never sends a
     * page param and expects `data` to be every unit, so it can drag-reorder
     * the whole set client-side.
     */
    public function index(Request $request): JsonResponse
    {
        $units = Unit::query()
            ->search($request->query('search'))
            ->active($request->query('is_active'))
            ->orderBy('sort_order')
            ->get();

        return $this->full($units, UnitResource::class);
    }

    public function show(Unit $unit): JsonResponse
    {
        return response()->json(['data' => (new UnitResource($unit))->resolve()]);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->service->create($request->validated(), $request->file('logo'));

        return response()->json(['data' => (new UnitResource($unit))->resolve()], 201);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit = $this->service->update(
            $unit,
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return response()->json(['data' => (new UnitResource($unit))->resolve()]);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $this->service->delete($unit);

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        return $this->reorderRows($request, Unit::class, 'reorder', 'units');
    }
}
