<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\TenantManager;
use Illuminate\Http\RedirectResponse;

/**
 * Switching company changes which database every subsequent query reads, so it
 * is a state change — POST/PUT, never a GET link that a prefetcher could fire.
 */
class TenantSwitchController extends Controller
{
    public function __invoke(TenantManager $tenants, int $tenant): RedirectResponse
    {
        if (! $tenants->switchTo($tenant)) {
            return back()->with('error', 'Company tersebut tidak tersedia untuk akun Anda.');
        }

        // The previous page may belong to a module this tenant does not have,
        // so land on the dashboard rather than bouncing into a 404.
        return redirect()
            ->route('dashboard')
            ->with('success', 'Company aktif diubah ke '.$tenants->current()['name'].'.');
    }
}
