<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\TenantModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single benefit line for an event. Never edited on its own: rows arrive
 * with their parent event in a single request (context.md §4.11).
 */
class EventBenefit extends TenantModel
{
    use HasFactory, HasTranslations;

    protected $fillable = ['event_id', 'title', 'sort_order'];

    protected $casts = [
        'title' => 'array',
        'sort_order' => 'integer',
    ];

    protected array $translatable = ['title'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
