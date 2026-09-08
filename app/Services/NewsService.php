<?php

namespace App\Services;

use App\Models\News;
use App\Support\SlugMaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Multi-step write logic for news: slug generation, cover upload and publish
 * timing. Controllers stay thin (context.md §4.8).
 */
class NewsService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, ?UploadedFile $cover = null): News
    {
        return DB::connection('tenant')->transaction(function () use ($data, $cover) {
            $news = new News;

            $this->fill($news, $data);

            if ($cover) {
                $news->cover_path = $this->media->storeImage($cover, 'news');
            }

            $news->save();
            $this->publicCache->forget('news');

            return $news;
        });
    }

    public function update(News $news, array $data, ?UploadedFile $cover = null, bool $removeCover = false): News
    {
        return DB::connection('tenant')->transaction(function () use ($news, $data, $cover, $removeCover) {
            $previousCover = $news->cover_path;

            $this->fill($news, $data);

            if ($cover) {
                $news->cover_path = $this->media->storeImage($cover, 'news');
            } elseif ($removeCover) {
                $news->cover_path = null;
            }

            $news->save();

            // Only drop the old file once the row pointing at it is safely
            // saved — a failed save must not leave the article imageless.
            if ($previousCover && $news->cover_path !== $previousCover) {
                $this->media->delete($previousCover);
            }

            $this->publicCache->forget('news');

            return $news;
        });
    }

    public function delete(News $news): void
    {
        // Soft delete: the cover stays, because a restore must bring the whole
        // article back (plan.md §5.4).
        $news->delete();
        $this->publicCache->forget('news');
    }

    public function forceDelete(News $news): void
    {
        DB::connection('tenant')->transaction(function () use ($news) {
            $this->media->delete($news->cover_path);
            $news->forceDelete();
        });

        $this->publicCache->forget('news');
    }

    /** Bulk publish/draft/delete from the index page's selection. */
    public function bulk(string $action, array $ids): int
    {
        $query = News::whereIn('id', $ids);

        $affected = match ($action) {
            'publish' => $query->get()->each(fn (News $n) => $this->publish($n))->count(),
            'draft' => $query->update(['status' => 'draft']),
            'delete' => $query->get()->each(fn (News $n) => $this->delete($n))->count(),
            default => 0,
        };

        $this->publicCache->forget('news');

        return $affected;
    }

    private function publish(News $news): void
    {
        $news->update([
            'status' => 'published',
            'published_at' => $news->published_at ?? now(),
        ]);
    }

    private function fill(News $news, array $data): void
    {
        $title = News::normaliseTranslatable($data['title'] ?? []);

        $news->fill([
            'category_id' => $data['category_id'] ?? null,
            'title' => $title,
            'excerpt' => News::normaliseTranslatable($data['excerpt'] ?? []),
            'body' => News::normaliseTranslatable($data['body'] ?? []),
            'meta_title' => News::normaliseTranslatable($data['meta_title'] ?? []),
            'meta_description' => News::normaliseTranslatable($data['meta_description'] ?? []),
            // Slugs only, never labels (context.md §5.12).
            'related_programs' => array_values($data['related_programs'] ?? []),
            'status' => $data['status'],
            'author_name' => $data['author_name'] ?? null,
        ]);

        $news->slug = SlugMaker::forTranslatable(
            News::class,
            News::normaliseTranslatable($data['slug'] ?? []),
            $title,
            $news->exists ? $news->id : null,
        );

        $news->published_at = $this->resolvePublishedAt($news, $data);
    }

    /**
     * Publishing without a date means "now"; going back to draft keeps the date
     * so republishing does not silently change the article's age.
     */
    private function resolvePublishedAt(News $news, array $data): ?string
    {
        $given = $data['published_at'] ?? null;

        if ($data['status'] === 'draft') {
            return $given ?: $news->published_at;
        }

        return $given ?: ($news->published_at ?? now());
    }
}
