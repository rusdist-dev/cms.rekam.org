<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\NewsCategory\SaveNewsCategoryRequest;
use App\Http\Resources\Dash\NewsCategoryResource;
use App\Models\News;
use App\Models\NewsCategory;
use App\Support\SlugMaker;
use Illuminate\Http\JsonResponse;

/**
 * Categories are a small, ordered list edited from the news screen rather than
 * a module of their own, so this is a compact CRUD without pagination.
 */
class NewsCategoryApiController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', News::class);

        $categories = NewsCategory::withCount('news')->ordered()->get();

        return response()->json([
            'data' => NewsCategoryResource::collection($categories)->resolve(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => max($categories->count(), 1),
                'total' => $categories->count(),
            ],
        ]);
    }

    public function store(SaveNewsCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        $category = new NewsCategory(['sort_order' => $data['sort_order'] ?? 0]);
        $category->name = NewsCategory::normaliseTranslatable($data['name']);
        $category->slug = SlugMaker::forTranslatable(NewsCategory::class, $data['slug'] ?? [], $category->name);
        $category->save();

        return response()->json(['data' => (new NewsCategoryResource($category))->resolve()], 201);
    }

    public function update(SaveNewsCategoryRequest $request, NewsCategory $category): JsonResponse
    {
        $data = $request->validated();

        $category->name = NewsCategory::normaliseTranslatable($data['name']);
        $category->slug = SlugMaker::forTranslatable(
            NewsCategory::class,
            $data['slug'] ?? [],
            $category->name,
            $category->id,
        );
        $category->sort_order = $data['sort_order'] ?? $category->sort_order;
        $category->save();

        return response()->json(['data' => (new NewsCategoryResource($category))->resolve()]);
    }

    public function destroy(NewsCategory $category): JsonResponse
    {
        // deleteAny, not delete: a class-string argument is dropped before the
        // policy call, so the ability must not declare a model parameter.
        $this->authorize('deleteAny', News::class);

        // The foreign key nulls the articles' category rather than deleting
        // them, but silently un-categorising content is not what someone
        // clicking "hapus kategori" expects.
        if ($category->news()->exists()) {
            return response()->json([
                'message' => 'Kategori masih dipakai oleh berita.',
                'errors' => ['category' => ['Pindahkan atau hapus beritanya terlebih dahulu.']],
            ], 422);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
