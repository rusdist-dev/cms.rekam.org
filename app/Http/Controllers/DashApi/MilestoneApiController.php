<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Controllers\DashApi\Concerns\ReordersRows;
use App\Http\Requests\Milestone\StoreMilestoneRequest;
use App\Http\Requests\Milestone\UpdateMilestoneRequest;
use App\Http\Resources\Dash\MilestoneResource;
use App\Models\Milestone;
use App\Services\MilestoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MilestoneApiController extends Controller
{
    use PaginatesJson, ReordersRows;

    public function __construct(private readonly MilestoneService $service)
    {
        $this->authorizeResource(Milestone::class, 'milestone');
    }

    /**
     * Unpaginated (see PaginatesJson::full()) and always ordered
     * chronologically — sortableList.js never sends a `?sort=`/page param, so
     * there is nothing to whitelist and no page to slice. Matches the
     * migration's own composite index and plan.md's "urut year, sort_order".
     */
    public function index(Request $request): JsonResponse
    {
        $milestones = Milestone::query()
            ->search($request->query('search'))
            ->year($request->query('year'))
            ->active($request->query('is_active'))
            ->orderBy('year')
            ->orderBy('sort_order')
            ->get();

        return $this->full($milestones, MilestoneResource::class);
    }

    public function show(Milestone $milestone): JsonResponse
    {
        return response()->json(['data' => (new MilestoneResource($milestone))->resolve()]);
    }

    public function store(StoreMilestoneRequest $request): JsonResponse
    {
        $milestone = $this->service->create($request->validated(), $request->file('cover'));

        return response()->json(['data' => (new MilestoneResource($milestone))->resolve()], 201);
    }

    public function update(UpdateMilestoneRequest $request, Milestone $milestone): JsonResponse
    {
        $milestone = $this->service->update(
            $milestone,
            $request->validated(),
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return response()->json(['data' => (new MilestoneResource($milestone))->resolve()]);
    }

    public function destroy(Milestone $milestone): JsonResponse
    {
        $this->service->delete($milestone);

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        return $this->reorderRows($request, Milestone::class, 'reorder', 'milestones');
    }
}
