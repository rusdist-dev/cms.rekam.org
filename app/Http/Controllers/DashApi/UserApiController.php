<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\Dash\UserResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserApiController extends Controller
{
    public function __construct()
    {
        // Route middleware gates the permission; the policy adds the rules a
        // permission cannot express (context.md §4.7).
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->query('per_page', config('cms.per_page')), 1),
            config('cms.max_per_page')
        );

        $users = User::query()
            // Eager loaded: the resource reads roles and tenants for every row
            // (context.md §4.9).
            ->with(['roles:id,name', 'tenants:id,slug'])
            ->when($request->query('search'), function ($query, string $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when($request->query('role'), fn ($q, $role) => $q->role($role))
            ->when(
                $request->query('is_active') !== null && $request->query('is_active') !== '',
                fn ($q) => $q->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN))
            )
            ->when(
                in_array($request->query('sort'), ['name', 'email', 'last_login_at', 'created_at'], true),
                fn ($q) => $q->orderBy($request->query('sort'), $request->query('direction') === 'desc' ? 'desc' : 'asc'),
                fn ($q) => $q->orderBy('name')
            )
            ->paginate($perPage);

        return response()->json([
            'data' => UserResource::collection($users->items())->resolve(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['roles:id,name', 'tenants:id,slug']);

        return response()->json(['data' => (new UserResource($user))->resolve()]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->syncRoles([$data['role']]);
            $user->tenants()->sync($this->tenantIds($data['tenants'] ?? []));

            return $user;
        });

        $user->load(['roles:id,name', 'tenants:id,slug']);

        return response()->json(['data' => (new UserResource($user))->resolve()], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        $this->guardLastSuperAdmin($user, $data);

        DB::transaction(function () use ($user, $data) {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'is_active' => $data['is_active'] ?? $user->is_active,
            ]);

            // An empty password field means "leave it alone", not "blank it".
            if (! empty($data['password'])) {
                $user->update(['password' => Hash::make($data['password'])]);
            }

            $user->syncRoles([$data['role']]);
            $user->tenants()->sync($this->tenantIds($data['tenants'] ?? []));
        });

        $user->load(['roles:id,name', 'tenants:id,slug']);

        return response()->json(['data' => (new UserResource($user))->resolve()]);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(null, 204);
    }

    /** @param array<int, string> $slugs */
    private function tenantIds(array $slugs): array
    {
        return $slugs ? Tenant::whereIn('slug', $slugs)->pluck('id')->all() : [];
    }

    /**
     * Demoting or deactivating the last super-admin would leave nobody able to
     * manage roles or tenants — a state with no way out through the UI.
     */
    private function guardLastSuperAdmin(User $user, array $data): void
    {
        if (! $user->isSuperAdmin()) {
            return;
        }

        $losingRole = $data['role'] !== User::SUPER_ADMIN;
        $beingDisabled = array_key_exists('is_active', $data) && ! $data['is_active'];

        if (! $losingRole && ! $beingDisabled) {
            return;
        }

        $remaining = User::role(User::SUPER_ADMIN)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($remaining > 0) {
            return;
        }

        // Reported as a field error so the message lands next to the control the
        // user just changed, rather than as a bare banner (context.md §4.5).
        throw ValidationException::withMessages([
            $losingRole ? 'role' : 'is_active' => 'Super admin terakhir tidak dapat diturunkan atau dinonaktifkan.',
        ]);
    }
}
