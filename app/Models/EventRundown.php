<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A session inside an event's rundown. Never edited on its own: rows arrive
 * with their parent event in a single request (context.md §4.11).
 */
class EventRundown extends TenantModel
{
    use HasFactory, HasTranslations;

    protected $fillable = ['event_id', 'time', 'title', 'description', 'sort_order'];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'sort_order' => 'integer',
    ];

    protected array $translatable = ['title', 'description'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
