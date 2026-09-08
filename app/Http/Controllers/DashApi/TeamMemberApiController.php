<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Controllers\DashApi\Concerns\ReordersRows;
use App\Http\Requests\TeamMember\StoreTeamMemberRequest;
use App\Http\Requests\TeamMember\UpdateTeamMemberRequest;
use App\Http\Resources\Dash\TeamMemberResource;
use App\Models\TeamMember;
use App\Services\TeamMemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamMemberApiController extends Controller
{
    use PaginatesJson, ReordersRows;

    private const SORTABLE = [
        'name' => 'name',
        'group' => 'group',
        'sort_order' => 'sort_order',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly TeamMemberService $service)
    {
        $this->authorizeResource(TeamMember::class, 'team');
    }

    /**
     * Unpaginated: resources/js/alpine/teamBoard.js (built on sortableList.js)
     * never sends a page param and expects `data` to be every member, so it
     * can group and drag-reorder them client-side.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TeamMember::query()
            ->search($request->query('search'))
            ->group($request->query('group'))
            ->active($request->query('is_active'));

        $query = $this->applySort($query, $request, self::SORTABLE, 'sort_order', 'asc');

        return $this->full($query->get(), TeamMemberResource::class);
    }

    public function show(TeamMember $team): JsonResponse
    {
        return response()->json(['data' => (new TeamMemberResource($team))->resolve()]);
    }

    public function store(StoreTeamMemberRequest $request): JsonResponse
    {
        $member = $this->service->create($request->validated(), $request->file('photo'));

        return response()->json(['data' => (new TeamMemberResource($member))->resolve()], 201);
    }

    public function update(UpdateTeamMemberRequest $request, TeamMember $team): JsonResponse
    {
        $member = $this->service->update(
            $team,
            $request->validated(),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        return response()->json(['data' => (new TeamMemberResource($member))->resolve()]);
    }

    public function destroy(TeamMember $team): JsonResponse
    {
        $this->service->delete($team);

        return response()->json(null, 204);
    }

    public function reorder(Request $request): JsonResponse
    {
        return $this->reorderRows($request, TeamMember::class, 'reorder', 'team');
    }
}
