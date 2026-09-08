<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Controllers\DashApi\Concerns\ReordersRows;
use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;
use App\Http\Resources\Dash\PartnerResource;
use App\Models\Partner;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerApiController extends Controller
{
    use PaginatesJson, ReordersRows;

    private const SORTABLE = [
        'name' => 'name',
        'sort_order' => 'sort_order',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly PartnerService $service)
    {
        $this->authorizeResource(Partner::class, 'partner');
    }

    /**
     * Unpaginated: resources/js/alpine/sortableList.js never sends a page
     * param and expects `data` to be every partner, so it can drag-reorder
     * the whole set client-side.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()
            ->search($request->query('search'))
            ->active($request->query('is_active'));

        $query = $this->applySort($query, $request, self::SORTABLE, 'sort_order', 'asc');

        return $this->full($query->get(), PartnerResource::class);
    }

    public function show(Partner $partner): JsonResponse
    {
        return response()->json(['data' => (new PartnerResource($partner))->resolve()]);
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = $this->service->create($request->validated(), $request->file('logo'));

        return response()->json(['data' => (new PartnerResource($partner))->resolve()], 201);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
    {
        $partner = $this->service->update(
            $partner,
            $request->validated(),
            $request->file('logo'),
            $request->boolean('remove_logo'),
        );

        return response()->json(['data' => (new PartnerResource($partner))->resolve()]);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $this->service->delete($partner);

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        return $this->reorderRows($request, Partner::class, 'reorder', 'partners');
    }
}
