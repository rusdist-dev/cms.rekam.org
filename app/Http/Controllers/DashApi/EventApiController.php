<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\Dash\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventApiController extends Controller
{
    use PaginatesJson;

    private const SORTABLE = [
        'title.id' => 'title->id',
        'status' => 'status',
        'start_at' => 'start_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(private readonly EventService $service)
    {
        $this->authorizeResource(Event::class, 'event');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            // Counted rather than loaded: the index only shows how many
            // sessions there are (context.md §4.9).
            ->withCount('rundowns')
            ->search($request->query('search'))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('category'), fn ($q, $cat) => $q->where('category', $cat))
            ->when($request->boolean('upcoming'), fn ($q) => $q->upcoming())
            ->when($request->query('from'), fn ($q, $date) => $q->where('start_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->where('start_at', '<=', $date))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed());

        $query = $this->applySort($query, $request, self::SORTABLE, 'start_at');

        return $this->paginate($query, $request, EventResource::class);
    }

    public function show(Event $event): JsonResponse
    {
        // The form edits rundown rows inline, so they travel with the event
        // (context.md §4.11).
        $event->load('rundowns');

        return response()->json(['data' => (new EventResource($event))->resolve()]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $this->authorizePublishing($request);

        $event = $this->service->create($request->validated(), $request->file('cover'));

        return response()->json(
            ['data' => (new EventResource($event->load('rundowns')))->resolve()],
            201
        );
    }

    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        $this->authorizePublishing($request);

        $event = $this->service->update(
            $event,
            $request->validated(),
            $request->file('cover'),
            $request->boolean('remove_cover'),
        );

        return response()->json(['data' => (new EventResource($event->load('rundowns')))->resolve()]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $this->service->delete($event);

        return response()->json(null, 204);
    }

    public function restore(int $event): JsonResponse
    {
        $model = Event::onlyTrashed()->findOrFail($event);

        $this->authorize('restore', $model);
        $model->restore();

        return response()->json(['data' => (new EventResource($model))->resolve()]);
    }

    public function forceDestroy(int $event): JsonResponse
    {
        $model = Event::onlyTrashed()->findOrFail($event);

        $this->authorize('forceDelete', $model);
        $this->service->forceDelete($model);

        return response()->json(null, 204);
    }

    private function authorizePublishing(Request $request): void
    {
        if (in_array($request->input('status'), ['published', 'scheduled'], true)) {
            $this->authorize('publish', Event::class);
        }
    }
}
