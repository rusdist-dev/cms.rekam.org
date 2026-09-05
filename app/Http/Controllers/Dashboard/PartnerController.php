<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Partner shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/partners — passing content from here would violate context.md §4.2.
 */
class PartnerController extends Controller
{
    public function index(): View
    {
        return view('partners.index', [
            'title' => 'Partner',
            'breadcrumbs' => [['label' => 'Partner']],
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
        return view('partners.form', [
            'title' => $id ? 'Ubah Partner' : 'Tambah Partner',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Partner', 'url' => route('partners.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
