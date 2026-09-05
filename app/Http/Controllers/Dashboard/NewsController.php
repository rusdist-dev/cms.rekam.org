<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Berita shell only. Rows and records are fetched by Alpine from
 * /dash-api/v1/news — passing content from here would violate context.md §4.2.
 */
class NewsController extends Controller
{
    public function index(): View
    {
        return view('news.index', [
            'title' => 'Berita',
            'breadcrumbs' => [['label' => 'Berita']],
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
        return view('news.form', [
            'title' => $id ? 'Ubah Berita' : 'Tambah Berita',
            'recordId' => $id,
            'breadcrumbs' => [
                ['label' => 'Berita', 'url' => route('news.index')],
                ['label' => $id ? 'Ubah' : 'Tambah'],
            ],
        ]);
    }
}
