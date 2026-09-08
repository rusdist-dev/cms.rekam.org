<?php

namespace App\View\Components;

use App\Models\ContactMessage;
use App\Services\TenantManager;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Dashboard shell: sidebar, topbar, flash area (context.md §7.4-§7.7).
 *
 * A class component rather than an anonymous one because the props are typed
 * and every page passes them.
 */
class AppLayout extends Component
{
    public int $unreadMessages;

    public function __construct(
        TenantManager $tenants,
        public ?string $title = null,
        /** @var array<int, array{label: string, url?: string}> */
        public array $breadcrumbs = [],
        /** Sidebar groups to open on first visit, before localStorage takes over. */
        public array $openGroups = ['konten', 'interaksi', 'sistem'],
    ) {
        // Computed here rather than passed by every controller, since nothing
        // ever did (the "Kotak Masuk" badge always read 0 before Fase 7).
        $this->unreadMessages = $tenants->hasFeature('contacts')
            ? ContactMessage::status('unread')->count()
            : 0;
    }

    public function render(): View
    {
        return view('layouts.app');
    }
}
