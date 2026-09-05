<?php

namespace App\Http\Controllers\DashApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\UpdateTenantRequest;
use App\Http\Resources\Dash\TenantResource;
use App\Models\Tenant;
use App\Services\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tenant::class);

        $tenants = Tenant::orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'data' => TenantResource::collection($tenants)->resolve(),
            'meta' => [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $tenants->count(),
                'total' => $tenants->count(),
            ],
        ]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        return response()->json(['data' => (new TenantResource($tenant))->resolve()]);
    }

    public function update(UpdateTenantRequest $request, TenantManager $tenants, Tenant $tenant): JsonResponse
    {
        $data = $request->validated();

        $tenant->update([
            'name' => $data['name'],
            'domain' => $data['domain'] ?? null,
            'is_active' => $data['is_active'] ?? $tenant->is_active,
        ]);

        // Goes through syncFeatures so unknown keys are dropped and core
        // modules stay on regardless of what was posted.
        $tenant->syncFeatures($data['features'] ?? []);

        // Editing the company you are currently in must not leave the rest of
        // the request looking at the flags it started with.
        if ($tenants->currentId() === $tenant->id) {
            $tenants->refresh();
        }

        return response()->json(['data' => (new TenantResource($tenant->fresh()))->resolve()]);
    }

    /**
     * Issues a new key and returns the plaintext exactly once — only the hash
     * is stored, so it can never be shown again.
     */
    public function rotateApiKey(Tenant $tenant): JsonResponse
    {
        $this->authorize('rotateApiKey', $tenant);

        $plain = $tenant->rotateApiKey();

        return response()->json([
            'data' => [
                'api_key' => $plain,
                'generated_at' => $tenant->api_key_generated_at?->format('Y-m-d H:i'),
                'notice' => 'Kunci ini hanya ditampilkan sekali. Simpan sekarang.',
            ],
        ]);
    }
}
