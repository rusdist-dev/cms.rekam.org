<?php

namespace App\Http\Resources\Dash;

use App\Models\Event;
use App\Models\Milestone;
use App\Models\News;
use App\Models\Partner;
use App\Models\Publication;
use App\Models\TeamMember;
use App\Models\Unit;
use App\Support\Labels;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'causer' => $this->causer?->name,
            'subject_type' => Labels::module(self::SUBJECT_MODULES[$this->subject_type] ?? $this->subject_type ?? ''),
            'event' => $this->event,
            'properties' => $this->properties,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
