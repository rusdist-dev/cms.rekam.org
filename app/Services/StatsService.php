<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\Milestone;
use App\Models\News;
use App\Models\Partner;
use App\Models\Publication;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Support\Labels;
use Illuminate\Support\Carbon;

/**
 * Real numbers behind the dashboard's cards and charts (plan.md Fase 7),
 * reproducing the exact shape `resources/prototype/stats.json` established so
 * the Blade/Alpine layer built against it needs no changes.
 */
class StatsService
{
    /** Maps an activity's `subject_type` (a model class) to its module slug for Labels::module(). */
    private const SUBJECT_MODULES = [
        News::class => 'news',
        Event::class => 'events',
        TeamMember::class => 'team',
        Partner::class => 'partners',
        Publication::class => 'publications',
        Milestone::class => 'milestones',
        Unit::class => 'units',
    ];

    public function __construct(private readonly TenantManager $tenants) {}

    public function summary(): array
    {
        return [
            'news_total' => News::count(),
            'news_published' => News::status('published')->count(),
            'news_draft' => News::status('draft')->count(),
            'events_upcoming' => $this->tenants->hasFeature('events') ? Event::upcoming()->count() : 0,
            'team_active' => $this->tenants->hasFeature('team') ? TeamMember::where('is_active', true)->count() : 0,
            'messages_unread' => $this->tenants->hasFeature('contacts') ? ContactMessage::status('unread')->count() : 0,
        ];
    }

    /**
     * 12 rolling months (not the calendar year), so the chart is meaningful the
     * day after it ships rather than waiting for January.
     */
    public function publishingTrend(): array
    {
        $months = collect(range(11, 0))->map(fn (int $i) => now()->subMonths($i)->startOfMonth());
        $since = $months->first();

        $news = News::query()
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $since)
            ->get(['published_at'])
            ->groupBy(fn (News $n) => $n->published_at->format('Y-m'));

        $events = $this->tenants->hasFeature('events')
            ? Event::query()
                ->where('created_at', '>=', $since)
                ->get(['created_at'])
                ->groupBy(fn (Event $e) => $e->created_at->format('Y-m'))
            : collect();

        return [
            'labels' => $months->map(fn (Carbon $m) => $m->translatedFormat('M'))->values()->all(),
            'datasets' => [
                [
                    'label' => 'Berita',
                    'data' => $months->map(fn (Carbon $m) => $news->get($m->format('Y-m'), collect())->count())->values()->all(),
                ],
                [
                    'label' => 'Events',
                    'data' => $months->map(fn (Carbon $m) => $events->get($m->format('Y-m'), collect())->count())->values()->all(),
                ],
            ],
        ];
    }

    public function statusBreakdown(): array
    {
        return [
            'labels' => ['Terbit', 'Draf', 'Terjadwal'],
            'datasets' => [[
                'label' => 'Berita',
                'data' => [
                    News::status('published')->count(),
                    News::status('draft')->count(),
                    News::status('scheduled')->count(),
                ],
            ]],
        ];
    }

    public function recentActivity(): array
    {
        return Activity::query()
            ->forCurrentTenant()
            ->with('causer')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'causer' => $activity->causer?->name,
                'subject_type' => Labels::module(self::SUBJECT_MODULES[$activity->subject_type] ?? $activity->subject_type ?? ''),
                'created_at' => $activity->created_at?->format('Y-m-d H:i:s'),
            ])
            ->all();
    }

    public function popularNews(): array
    {
        return News::query()
            ->published()
            ->orderByDesc('views')
            ->limit(5)
            ->get(['id', 'title', 'views'])
            ->map(fn (News $news) => [
                'id' => $news->id,
                'title' => $news->title,
                'views' => $news->views,
            ])
            ->all();
    }
}
