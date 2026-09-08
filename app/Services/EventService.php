<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRundown;
use App\Support\SlugMaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly TenantManager $tenants,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, ?UploadedFile $cover = null): Event
    {
        return DB::connection('tenant')->transaction(function () use ($data, $cover) {
            $event = new Event;

            $this->fill($event, $data);

            if ($cover) {
                $event->cover_path = $this->media->storeImage($cover, 'events');
            }

            $event->save();

            $this->syncRundowns($event, $data['rundowns'] ?? null);
            $this->publicCache->forget('events');

            return $event;
        });
    }

    public function update(Event $event, array $data, ?UploadedFile $cover = null, bool $removeCover = false): Event
    {
        return DB::connection('tenant')->transaction(function () use ($event, $data, $cover, $removeCover) {
            $previousCover = $event->cover_path;

            $this->fill($event, $data);

            if ($cover) {
                $event->cover_path = $this->media->storeImage($cover, 'events');
            } elseif ($removeCover) {
                $event->cover_path = null;
            }

            $event->save();

            $this->syncRundowns($event, $data['rundowns'] ?? null);

            if ($previousCover && $event->cover_path !== $previousCover) {
                $this->media->delete($previousCover);
            }

            $this->publicCache->forget('events');

            return $event;
        });
    }

    public function delete(Event $event): void
    {
        $event->delete();
        $this->publicCache->forget('events');
    }

    public function forceDelete(Event $event): void
    {
        DB::connection('tenant')->transaction(function () use ($event) {
            $this->media->delete($event->cover_path);
            $event->forceDelete();
        });

        $this->publicCache->forget('events');
    }

    /**
     * Creates, updates and deletes rundown rows in one pass, inside the parent's
     * transaction (context.md §4.11).
     *
     * A null payload means "the form did not include rundowns" — for a tenant
     * without the feature, or a partial update — and must leave existing rows
     * alone. An empty array means "the editor removed every row".
     */
    private function syncRundowns(Event $event, ?array $rows): void
    {
        if ($rows === null || ! $this->tenants->hasFeature('event_rundown')) {
            return;
        }

        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'time' => $row['time'] ?: null,
                'title' => EventRundown::normaliseTranslatable($row['title'] ?? []),
                'description' => EventRundown::normaliseTranslatable($row['description'] ?? []),
                // Order comes from the array position, which is what the editor
                // dragged the rows into.
                'sort_order' => $index,
            ];

            $existing = isset($row['id'])
                ? $event->rundowns()->whereKey($row['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keptIds[] = $existing->id;

                continue;
            }

            $keptIds[] = $event->rundowns()->create($attributes)->id;
        }

        // Whatever the form no longer holds has been removed by the editor.
        $event->rundowns()->whereKeyNot($keptIds)->delete();
    }

    private function fill(Event $event, array $data): void
    {
        $title = Event::normaliseTranslatable($data['title'] ?? []);

        $event->fill([
            'title' => $title,
            'description' => Event::normaliseTranslatable($data['description'] ?? []),
            'location' => Event::normaliseTranslatable($data['location'] ?? []),
            'fee_note' => Event::normaliseTranslatable($data['fee_note'] ?? []),
            'meta_title' => Event::normaliseTranslatable($data['meta_title'] ?? []),
            'meta_description' => Event::normaliseTranslatable($data['meta_description'] ?? []),
            'category' => $data['category'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'] ?? null,
            'is_all_day' => (bool) ($data['is_all_day'] ?? false),
            'fee' => $data['fee'] ?? null,
            'quota' => $data['quota'] ?? null,
            'registration_url' => $data['registration_url'] ?? null,
            'status' => $data['status'],
        ]);

        $event->slug = SlugMaker::forTranslatable(
            Event::class,
            Event::normaliseTranslatable($data['slug'] ?? []),
            $title,
            $event->exists ? $event->id : null,
        );
    }
}
