<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

/**
 * Uploads, resizes and deletes tenant files.
 *
 * Every path is prefixed with the tenant slug (context.md §5.9), so one
 * company's files can never be served from another's URL space — and a stray
 * `storage/app/public/media/rekam` is immediately identifiable as rekam's.
 *
 * Fase 5 adds the media library UI on top; the storage contract here does not
 * change.
 */
class MediaService
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * Stores an image, downscaling anything larger than the configured cap.
     *
     * @return string The stored path, relative to the public disk.
     */
    public function storeImage(UploadedFile $file, string $folder = 'covers'): string
    {
        $path = $this->path($folder, $file);
        $config = config('cms.media.image');

        $image = Image::make($file->getRealPath());

        // Editors upload straight from a camera or phone; a 6000px cover would
        // be served to every compro visitor untouched.
        if ($image->width() > $config['max_width']) {
            $image->resize($config['max_width'], null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        Storage::disk($this->disk())->put($path, (string) $image->encode(null, 85));

        $this->record($path, $file, $image->width(), $image->height());

        return $path;
    }

    /** Stores a document (PDF) as uploaded, without transformation. */
    public function storeDocument(UploadedFile $file, string $folder = 'documents'): string
    {
        $path = $this->path($folder, $file);

        Storage::disk($this->disk())->putFileAs(dirname($path), $file, basename($path));

        $this->record($path, $file);

        return $path;
    }

    /**
     * Deletes a stored file. Silently ignores a path that is already gone —
     * a missing file must not block deleting the record that pointed at it.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        // Only ever delete inside this tenant's own folder, whatever the caller
        // passed in.
        if (! str_starts_with($path, $this->prefix())) {
            return;
        }

        Storage::disk($this->disk())->delete($path);

        Media::where('path', $path)->delete();
    }

    /** Public URL for a stored path, or null when there is no file. */
    public function url(?string $path): ?string
    {
        return $path ? Storage::disk($this->disk())->url($path) : null;
    }

    private function path(string $folder, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');

        // Date folders keep directory listings manageable once a company has
        // years of content.
        return sprintf(
            '%s/%s/%s/%s.%s',
            $this->prefix(),
            $folder,
            now()->format('Y/m'),
            Str::uuid(),
            $extension
        );
    }

    private function record(string $path, UploadedFile $file, ?int $width = null, ?int $height = null): void
    {
        Media::create([
            'disk' => $this->disk(),
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
            'uploaded_by' => auth()->id(),
        ]);
    }

    private function prefix(): string
    {
        return config('cms.media.path_prefix').'/'.$this->tenants->currentOrFail()->slug;
    }

    private function disk(): string
    {
        return config('cms.media.disk');
    }
}
