<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Resources\Dash\ContactMessageResource;
use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactMessageApiController extends Controller
{
    use PaginatesJson;

    private const SORTABLE = [
        'created_at' => 'created_at',
        'status' => 'status',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ContactMessage::class);

        $query = ContactMessage::query()
            ->search($request->query('search'))
            ->status($request->query('status'));

        $query = $this->applySort($query, $request, self::SORTABLE, 'created_at');

        return $this->paginate($query, $request, ContactMessageResource::class);
    }

    /** Opening a message is what "read" means — no separate endpoint for it. */
    public function show(ContactMessage $message): JsonResponse
    {
        $this->authorize('view', $message);

        if ($message->status === 'unread') {
            $message->update(['status' => 'read']);
        }

        return response()->json(['data' => (new ContactMessageResource($message))->resolve()]);
    }

    public function archive(ContactMessage $message): JsonResponse
    {
        $this->authorize('manage', $message);

        $message->update(['status' => 'archived']);

        return response()->json(['data' => (new ContactMessageResource($message))->resolve()]);
    }

    public function destroy(ContactMessage $message): JsonResponse
    {
        $this->authorize('delete', $message);

        $message->delete();

        return response()->json(null, 204);
    }
}
