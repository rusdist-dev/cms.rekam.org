<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Peran & Izin shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/roles — passing content from here would violate context.md §4.2.
 */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'title' => 'Peran & Izin',
            'breadcrumbs' => [['label' => 'Peran & Izin']],
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
        return view('roles.form', [
            'title' => $id ? 'Ubah Peran' : 'Tambah Peran',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Peran & Izin', 'url' => route('roles.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
