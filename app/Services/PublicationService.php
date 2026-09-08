<?php

namespace App\Services;

use App\Models\Publication;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Multi-step write logic for publications: document + cover upload, and
 * append-to-end ordering. Controllers stay thin (context.md §4.8).
 */
class PublicationService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, UploadedFile $file, ?UploadedFile $cover = null): Publication
    {
        return DB::connection('tenant')->transaction(function () use ($data, $file, $cover) {
            $publication = new Publication;

            $this->fill($publication, $data);
            $publication->sort_order = (int) Publication::max('sort_order') + 1;
            $this->attachFile($publication, $file);

            if ($cover) {
                $publication->cover_path = $this->media->storeImage($cover, 'publications');
            }

            $publication->save();
            $this->publicCache->forget('publications');

            return $publication;
        });
    }

    public function update(
        Publication $publication,
        array $data,
        ?UploadedFile $file = null,
        bool $removeFile = false,
        ?UploadedFile $cover = null,
        bool $removeCover = false,
    ): Publication {
        return DB::connection('tenant')->transaction(function () use ($publication, $data, $file, $removeFile, $cover, $removeCover) {
            $previousFile = $publication->file_path;
            $previousCover = $publication->cover_path;

            $this->fill($publication, $data);

            if ($file) {
                $this->attachFile($publication, $file);
            } elseif ($removeFile) {
                $publication->file_path = null;
                $publication->file_name = null;
                $publication->file_size = null;
            }

            if ($cover) {
                $publication->cover_path = $this->media->storeImage($cover, 'publications');
            } elseif ($removeCover) {
                $publication->cover_path = null;
            }

            $publication->save();

            if ($previousFile && $publication->file_path !== $previousFile) {
                $this->media->delete($previousFile);
            }

            if ($previousCover && $publication->cover_path !== $previousCover) {
                $this->media->delete($previousCover);
            }

            $this->publicCache->forget('publications');

            return $publication;
        });
    }

    public function delete(Publication $publication): void
    {
        DB::connection('tenant')->transaction(function () use ($publication) {
            $this->media->delete($publication->file_path);
            $this->media->delete($publication->cover_path);
            $publication->delete();
        });

        $this->publicCache->forget('publications');
    }

    private function attachFile(Publication $publication, UploadedFile $file): void
    {
        $publication->file_path = $this->media->storeDocument($file, 'publications');
        $publication->file_name = $file->getClientOriginalName();
        $publication->file_size = $file->getSize();
    }

    private function fill(Publication $publication, array $data): void
    {
        $publication->fill([
            'title' => Publication::normaliseTranslatable($data['title'] ?? []),
            'description' => Publication::normaliseTranslatable($data['description'] ?? []),
            'category' => $data['category'],
            'is_featured' => $data['is_featured'] ?? false,
        ]);
    }
}
