<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Tim shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/team — passing content from here would violate context.md §4.2.
 */
class TeamMemberController extends Controller
{
    public function index(): View
    {
        return view('team.index', [
            'title' => 'Tim',
            'breadcrumbs' => [['label' => 'Tim']],
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
        return view('team.form', [
            'title' => $id ? 'Ubah Anggota Tim' : 'Tambah Anggota Tim',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Tim', 'url' => route('team.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
