<?php

namespace App\View\Components;

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
    public function __construct(
        public ?string $title = null,
        /** @var array<int, array{label: string, url?: string}> */
        public array $breadcrumbs = [],
        public int $unreadMessages = 0,
        /** Sidebar groups to open on first visit, before localStorage takes over. */
        public array $openGroups = ['konten', 'interaksi', 'sistem'],
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
