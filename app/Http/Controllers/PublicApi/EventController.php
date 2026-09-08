<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicApi\Concerns\PaginatesPublicJson;
use App\Http\Resources\Public\EventResource;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use PaginatesPublicJson;

    public function index(Request $request): JsonResponse
    {
        $query = Event::query()
            ->published()
            ->when($request->boolean('upcoming'), fn ($q) => $q->upcoming())
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('start_at');

        return $this->listResponse($request, $query, EventResource::class, 'events');
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        return $this->dataResponse($request, "events:{$slug}", function () use ($slug) {
            $event = Event::query()
                ->published()
                ->with('rundowns')
                ->where('slug->id', $slug)
                ->orWhere('slug->en', $slug)
                ->firstOrFail();

            return (new EventResource($event))->resolve();
        });
    }
}
