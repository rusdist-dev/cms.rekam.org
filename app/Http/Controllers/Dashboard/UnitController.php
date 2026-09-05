<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Unit shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/units — passing content from here would violate context.md §4.2.
 */
class UnitController extends Controller
{
    public function index(): View
    {
        return view('units.index', [
            'title' => 'Unit',
            'breadcrumbs' => [['label' => 'Unit']],
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
        return view('units.form', [
            'title' => $id ? 'Ubah Unit' : 'Tambah Unit',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Unit', 'url' => route('units.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
