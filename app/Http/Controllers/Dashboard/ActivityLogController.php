<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Renders the Riwayat Aktivitas shell only. Rows are fetched by Alpine from
 * /dash-api/v1/activity — passing content from here would violate context.md §4.2.
 */
class ActivityLogController extends Controller
{
    public function index(): View
    {
        return view('activity.index', [
            'title' => 'Riwayat Aktivitas',
            'breadcrumbs' => [['label' => 'Riwayat Aktivitas']],
        ]);
    }
}
