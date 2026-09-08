<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\TeamMemberResource;
use App\Models\TeamMember;
use App\Services\TaxonomyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    use PaginatesPublicJson;

    public function __construct(private readonly TaxonomyService $taxonomy) {}

    /**
     * Grouped per level (plan.md Fase 6) — the compro site has no access to
     * the internal taxonomy system, so the grouping and level labels are
     * built here rather than left to the client, same order the dashboard's
     * team board already groups in.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'team', function () {
            // Not `->active()` — that scope takes an optional query-string
            // value (null/'' means "no filter", for the admin index); the
            // public API always hard-filters, no override possible.
            $members = TeamMember::query()->where('is_active', true)->orderBy('sort_order')->get();

            return collect($this->taxonomy->options('team_levels'))
                ->map(fn (array $level) => [
                    'level' => $level,
                    'members' => TeamMemberResource::collection(
                        $members->where('group', $level['value'])->values(),
                    )->resolve(),
                ])
                // A level with no active members yet clutters the page rather
                // than helping it — orphaned members (level deleted from
                // taxonomy) are likewise dropped, same as they'd have no
                // sensible label to show here.
                ->filter(fn (array $group) => count($group['members']) > 0)
                ->values()
                ->all();
        });
    }
}
