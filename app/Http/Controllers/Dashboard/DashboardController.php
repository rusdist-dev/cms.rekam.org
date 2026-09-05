<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Stat cards, charts and the activity table all fetch their own data from
 * /dash-api/v1/stats/* — this only renders the frame (context.md §4.1).
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', [
            'title' => 'Dasbor',
        ]);
    }
}
