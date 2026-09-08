<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\NewsResource;
use App\Models\News;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    use PaginatesPublicJson;

    public function index(Request $request): JsonResponse
    {
        $query = News::query()
            ->published()
            ->with('category:id,name,slug')
            ->program($request->query('program'))
            ->when($request->query('category'), fn (Builder $q, string $slug) => $this->whereCategorySlug($q, $slug))
            ->orderByDesc('published_at');

        return $this->listResponse($request, $query, NewsResource::class, 'news');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return $this->dataResponse($request, "news:{$slug}", function () use ($slug) {
            $news = News::query()
                ->published()
                ->with('category:id,name,slug')
                ->where('slug->id', $slug)
                ->orWhere('slug->en', $slug)
                ->firstOrFail();

            return (new NewsResource($news))->resolve();
        });
    }

    /**
     * Matches either locale's category slug — the caller may be linking from
     * a page built around either language (context.md §6.4).
     */
    private function whereCategorySlug(Builder $query, string $slug): Builder
    {
        return $query->whereHas('category', function (Builder $c) use ($slug) {
            $c->where('slug->id', $slug)->orWhere('slug->en', $slug);
        });
    }
}
