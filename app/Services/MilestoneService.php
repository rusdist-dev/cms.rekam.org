<?php

namespace App\Services;

use App\Models\Milestone;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Multi-step write logic for milestones: cover upload and append-to-end
 * ordering. Controllers stay thin (context.md §4.8).
 */
class MilestoneService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, ?UploadedFile $cover = null): Milestone
    {
        return DB::connection('tenant')->transaction(function () use ($data, $cover) {
            $milestone = new Milestone;

            $this->fill($milestone, $data);
            $milestone->sort_order = (int) Milestone::max('sort_order') + 1;

            if ($cover) {
                $milestone->cover_path = $this->media->storeImage($cover, 'milestones');
            }

            $milestone->save();
            $this->publicCache->forget('milestones');

            return $milestone;
        });
    }

    public function update(Milestone $milestone, array $data, ?UploadedFile $cover = null, bool $removeCover = false): Milestone
    {
        return DB::connection('tenant')->transaction(function () use ($milestone, $data, $cover, $removeCover) {
            $previousCover = $milestone->cover_path;

            $this->fill($milestone, $data);

            if ($cover) {
                $milestone->cover_path = $this->media->storeImage($cover, 'milestones');
            } elseif ($removeCover) {
                $milestone->cover_path = null;
            }

            $milestone->save();

            if ($previousCover && $milestone->cover_path !== $previousCover) {
                $this->media->delete($previousCover);
            }

            $this->publicCache->forget('milestones');

            return $milestone;
        });
    }

    public function delete(Milestone $milestone): void
    {
        DB::connection('tenant')->transaction(function () use ($milestone) {
            $this->media->delete($milestone->cover_path);
            $milestone->delete();
        });

        $this->publicCache->forget('milestones');
    }

    private function fill(Milestone $milestone, array $data): void
    {
        $milestone->fill([
            'title' => Milestone::normaliseTranslatable($data['title'] ?? []),
            'body' => Milestone::normaliseTranslatable($data['body'] ?? []),
            'year' => $data['year'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
