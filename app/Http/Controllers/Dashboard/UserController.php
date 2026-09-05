<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;

/**
 * Renders the Pengguna shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/users — passing content from here would violate context.md §4.2.
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'title' => 'Pengguna',
            'breadcrumbs' => [['label' => 'Pengguna']],
        ]);
    }

    public function create(): View
    {
        return $this->form(null);
    }

    public function edit(string $id): View
    {
        return $this->form($id);
    }

    private function form(?string $id): View
    {
        return view('users.form', [
            'title' => $id ? 'Ubah Pengguna' : 'Tambah Pengguna',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Pengguna', 'url' => route('users.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],

            // Constant select options, not content — the one thing a Blade
            // controller may pass (context.md §4.2). Querying this in the view
            // would breach §4.9.
            'tenantOptions' => Tenant::active()
                ->orderBy('sort_order')
                ->get(['slug', 'name', 'domain'])
                ->map(fn (Tenant $t) => [
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'domain' => $t->domain,
                ])
                ->all(),
        ]);
    }
}
