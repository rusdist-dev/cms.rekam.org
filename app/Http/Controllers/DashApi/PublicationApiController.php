<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Requests\Publication\StorePublicationRequest;
use App\Http\Requests\Publication\UpdatePublicationRequest;
use App\Http\Resources\Dash\PublicationResource;
use App\Models\Publication;
use App\Services\PublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationApiController extends Controller
{
    use PaginatesJson;

    private const SORTABLE = [
        'title.id' => 'title->id',
        'sort_order' => 'sort_order',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly PublicationService $service)
    {
        $this->authorizeResource(Publication::class, 'publication');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Publication::query()
            ->search($request->query('search'))
            ->category($request->query('category'))
            ->featured($request->query('is_featured'));

        $query = $this->applySort($query, $request, self::SORTABLE, 'sort_order', 'asc');

        return $this->paginate($query, $request, PublicationResource::class);
    }

    public function show(Publication $publication): JsonResponse
    {
        return response()->json(['data' => (new PublicationResource($publication))->resolve()]);
    }

    public function store(StorePublicationRequest $request): JsonResponse
    {
        $publication = $this->service->create(
            $request->validated(),
            $request->file('file'),
            $request->file('cover'),
        );

        return response()->json(['data' => (new PublicationResource($publication))->resolve()], 201);
    }

    public function update(UpdatePublicationRequest $request, Publication $publication): JsonResponse
    {
        $publication = $this->service->update(
            $publication,
            $request->validated(),
            $request->file('file'),
            $request->boolean('remove_file'),
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return response()->json(['data' => (new PublicationResource($publication))->resolve()]);
    }

    public function destroy(Publication $publication): JsonResponse
    {
        $this->service->delete($publication);

        return response()->json(null, 204);
    }
}
