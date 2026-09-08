<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\TenantManager;
use Illuminate\Contracts\View\View;

class SiteSettingController extends Controller
{
    public function __invoke(TenantManager $tenants): View
    {
        return view('settings.index', [
            'title' => 'Pengaturan Situs',
            'breadcrumbs' => [['label' => 'Pengaturan']],
            'currentTenant' => $tenants->currentOrFail(),

            // Tab labels are constants, not content — safe to pass (context.md §4.2).
            'tabs' => [
                'identitas' => 'Identitas',
                'sosial' => 'Sosial Media',
                'seo' => 'SEO',
                'peta' => 'Peta',
                'taksonomi' => 'Taksonomi',
                'api' => 'API Key',
            ],
        ]);
    }
}
