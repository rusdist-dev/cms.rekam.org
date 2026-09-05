<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Requests\News\BulkNewsRequest;
use App\Http\Requests\News\StoreNewsRequest;
use App\Http\Requests\News\UpdateNewsRequest;
use App\Http\Resources\Dash\NewsResource;
use App\Models\News;
use App\Services\NewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsApiController extends Controller
{
    use PaginatesJson;

    /** Sortable columns, mapped to the expression the database understands. */
    private const SORTABLE = [
        'title.id' => 'title->id',
        'status' => 'status',
        'published_at' => 'published_at',
        'views' => 'views',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly NewsService $service)
    {
        $this->authorizeResource(News::class, 'news');
    }

    public function index(Request $request): JsonResponse
    {
        $query = News::query()
            // Eager loaded: the resource reads the category of every row
            // (context.md §4.9).
            ->with('category:id,name')
            ->search($request->query('search'))
            ->status($request->query('status'))
            ->program($request->query('program'))
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->query('from'), fn ($q, $date) => $q->where('published_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->where('published_at', '<=', $date))
            // The index shows live content by default; ?trashed=1 is the
            // recycle bin (plan.md §5.4).
            ->when(
                $request->boolean('trashed'),
                fn ($q) => $q->onlyTrashed(),
            );

        $query = $this->applySort($query, $request, self::SORTABLE, 'published_at');

        return $this->paginate($query, $request, NewsResource::class);
    }

    public function show(News $news): JsonResponse
    {
        $news->load('category:id,name');

        return response()->json(['data' => (new NewsResource($news))->resolve()]);
    }

    public function store(StoreNewsRequest $request): JsonResponse
    {
        $this->authorizePublishing($request);

        $news = $this->service->create($request->validated(), $request->file('cover'));

        return response()->json(
            ['data' => (new NewsResource($news->load('category:id,name')))->resolve()],
            201
        );
    }

    public function update(UpdateNewsRequest $request, News $news): JsonResponse
    {
        $this->authorizePublishing($request);

        $news = $this->service->update(
            $news,
            $request->validated(),
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return response()->json(['data' => (new NewsResource($news->load('category:id,name')))->resolve()]);
    }

    public function destroy(News $news): JsonResponse
    {
        $this->service->delete($news);

        return response()->json(null, 204);
    }

    public function restore(int $news): JsonResponse
    {
        $model = News::onlyTrashed()->findOrFail($news);

        $this->authorize('restore', $model);
        $model->restore();

        return response()->json(['data' => (new NewsResource($model))->resolve()]);
    }

    public function forceDestroy(int $news): JsonResponse
    {
        $model = News::onlyTrashed()->findOrFail($news);

        $this->authorize('forceDelete', $model);
        $this->service->forceDelete($model);

        return response()->json(null, 204);
    }

    /** Publish, draft or delete the rows selected on the index. */
    public function bulk(BulkNewsRequest $request): JsonResponse
    {
        $data = $request->validated();

        $affected = $this->service->bulk($data['action'], $data['ids']);

        return response()->json([
            'data' => ['affected' => $affected],
            'message' => "{$affected} berita diperbarui.",
        ]);
    }

    /**
     * Publishing is a separate permission from writing, so an editor cannot
     * make content public by choosing a status.
     */
    private function authorizePublishing(Request $request): void
    {
        if (in_array($request->input('status'), ['published', 'scheduled'], true)) {
            $this->authorize('publish', News::class);
        }
    }
}
