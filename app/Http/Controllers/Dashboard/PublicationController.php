<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Publikasi shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/publications — passing content from here would violate context.md §4.2.
 */
class PublicationController extends Controller
{
    public function index(): View
    {
        return view('publications.index', [
            'title' => 'Publikasi',
            'breadcrumbs' => [['label' => 'Publikasi']],
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
        return view('publications.form', [
            'title' => $id ? 'Ubah Publikasi' : 'Tambah Publikasi',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Publikasi', 'url' => route('publications.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
