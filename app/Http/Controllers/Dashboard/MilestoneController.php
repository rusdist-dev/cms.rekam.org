<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Milestone shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/milestones — passing content from here would violate context.md §4.2.
 */
class MilestoneController extends Controller
{
    public function index(): View
    {
        return view('milestones.index', [
            'title' => 'Milestone',
            'breadcrumbs' => [['label' => 'Milestone']],
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
        return view('milestones.form', [
            'title' => $id ? 'Ubah Milestone' : 'Tambah Milestone',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Milestone', 'url' => route('milestones.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
