<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ContactMessageController extends Controller
{
    public function index(): View
    {
        return view('contacts.index', [
            'title' => 'Kotak Masuk',
            'breadcrumbs' => [['label' => 'Kotak Masuk']],
        ]);
    }

    public function show(string $message): View
    {
        return view('contacts.show', [
            'title' => 'Detail Pesan',
            'recordId' => $message,
            'breadcrumbs' => [
                ['label' => 'Kotak Masuk', 'url' => route('contacts.index')],
                ['label' => 'Detail'],
            ],
        ]);
    }

    /** Static contact details shown on the compro — stored in site_settings. */
    public function settings(): View
    {
        return view('contacts.settings', [
            'title' => 'Informasi Kontak',
            'breadcrumbs' => [
                ['label' => 'Kotak Masuk', 'url' => route('contacts.index')],
                ['label' => 'Informasi Kontak'],
            ],
        ]);
    }
}
