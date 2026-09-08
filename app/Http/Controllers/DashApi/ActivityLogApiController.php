<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DashApi\Concerns\PaginatesJson;
use App\Http\Resources\Dash\ActivityResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogApiController extends Controller
{
    use PaginatesJson;

    public function index(Request $request): JsonResponse
    {
        $query = Activity::query()
            ->forCurrentTenant()
            ->with('causer')
            ->when($request->query('search'), function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('description', 'like', "%{$term}%")
                        ->orWhereHas('causer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
                });
            })
            ->when($request->query('from'), fn ($q, $date) => $q->where('created_at', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->where('created_at', '<=', $date))
            ->latest();

        return $this->paginate($query, $request, ActivityResource::class);
    }
}
