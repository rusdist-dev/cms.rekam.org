<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Events shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/events — passing content from here would violate context.md §4.2.
 */
class EventController extends Controller
{
    public function index(): View
    {
        return view('events.index', [
            'title' => 'Events',
            'breadcrumbs' => [['label' => 'Events']],
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
        return view('events.form', [
            'title' => $id ? 'Ubah Event' : 'Tambah Event',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Events', 'url' => route('events.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
