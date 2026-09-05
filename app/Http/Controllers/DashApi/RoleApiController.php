<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\SaveRoleRequest;
use App\Http\Resources\Dash\RoleResource;
use App\Support\Labels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleApiController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Role::class, 'role');
    }

    public function index(Request $request): JsonResponse
    {
        $roles = Role::query()
            // withCount avoids an N+1 across every row (context.md §4.9).
            ->withCount(['users', 'permissions'])
            ->when($request->query('search'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => RoleResource::collection($roles)->resolve(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $roles->count(),
                'total' => $roles->count(),
            ],
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        $role->loadCount(['users', 'permissions'])->load('permissions:id,name');

        return response()->json(['data' => (new RoleResource($role))->resolve()]);
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->syncPermissions($data['permissions'] ?? []);

        $this->resetPermissionCache();

        return response()->json(['data' => (new RoleResource($role))->resolve()], 201);
    }

    public function update(SaveRoleRequest $request, Role $role): JsonResponse
    {
        $data = $request->validated();

        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        $this->resetPermissionCache();

        return response()->json(['data' => (new RoleResource($role))->resolve()]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        $this->resetPermissionCache();

        return response()->json(null, 204);
    }

    /** The permission matrix rendered on the role form. */
    public function permissions(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $grouped = Permission::orderBy('name')->pluck('name')
            ->groupBy(fn (string $name) => explode('.', $name)[0])
            ->map(fn ($names, string $module) => [
                'module' => $module,
                'label' => Labels::module($module),
                'actions' => $names
                    ->map(fn (string $name) => [
                        'name' => $name,
                        'action' => explode('.', $name)[1] ?? '',
                        'label' => Labels::action(explode('.', $name)[1] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();

        return response()->json(['data' => $grouped]);
    }

    /**
     * Spatie caches the permission map; without this a permission granted a
     * second ago appears not to work until the cache expires.
     */
    private function resetPermissionCache(): void
    {
        Artisan::call('permission:cache-reset');
    }
}
