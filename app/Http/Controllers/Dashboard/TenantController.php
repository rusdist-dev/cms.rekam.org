<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        return view('tenants.index', [
            'title' => 'Company',
            'breadcrumbs' => [['label' => 'Company']],
        ]);
    }

    public function edit(string $tenant): View
    {
        return view('tenants.form', [
            'title' => 'Ubah Company',
            'recordId' => $tenant,
            'breadcrumbs' => [
                ['label' => 'Company', 'url' => route('tenants.index')],
                ['label' => 'Ubah'],
            ],
        ]);
    }
}
