<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\PartnerResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use PaginatesPublicJson;

    public function index(Request $request): JsonResponse
    {
        if ($request->boolean('grouped')) {
            return $this->groupedResponse($request);
        }

        // Not `->active()` — that scope takes an optional query-string value
        // for the admin index; the public API always hard-filters.
        $query = Partner::query()
            ->where('is_active', true)
            ->category($request->query('category'))
            ->orderBy('sort_order');

        return $this->listResponse($request, $query, PartnerResource::class, 'partners');
    }

    /**
     * `?grouped=1` buckets partners by `cms.partner_categories`, in that
     * config's order, so a consumer can render one section per category
     * without sorting them itself. Categories with no partners are omitted;
     * an "Lainnya" bucket catches partners left without one.
     */
    private function groupedResponse(Request $request): JsonResponse
    {
        return $this->dataResponse($request, 'partners:grouped', function () {
            $partners = Partner::query()->where('is_active', true)->orderBy('sort_order')->get();

            $groups = collect(config('cms.partner_categories'))
                ->map(fn (string $label, string $slug) => [
                    'category' => $slug,
                    'label' => $label,
                    'partners' => PartnerResource::collection(
                        $partners->where('category', $slug)->values()
                    )->resolve(),
                ]);

            $uncategorised = $partners->whereNull('category')->values();

            if ($uncategorised->isNotEmpty()) {
                $groups->push([
                    'category' => null,
                    'label' => 'Lainnya',
                    'partners' => PartnerResource::collection($uncategorised)->resolve(),
                ]);
            }

            return $groups->filter(fn (array $group) => count($group['partners']) > 0)->values()->all();
        });
    }
}
