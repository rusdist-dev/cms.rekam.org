<?php

namespace App\Services;

use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Multi-step write logic for units: logo upload and append-to-end ordering.
 * Controllers stay thin (context.md §4.8).
 */
class UnitService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, ?UploadedFile $logo = null): Unit
    {
        return DB::connection('tenant')->transaction(function () use ($data, $logo) {
            $unit = new Unit;

            $this->fill($unit, $data);
            $unit->sort_order = (int) Unit::max('sort_order') + 1;

            if ($logo) {
                $unit->logo_path = $this->media->storeImage($logo, 'units');
            }

            $unit->save();
            $this->publicCache->forget('units');

            return $unit;
        });
    }

    public function update(Unit $unit, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Unit
    {
        return DB::connection('tenant')->transaction(function () use ($unit, $data, $logo, $removeLogo) {
            $previousLogo = $unit->logo_path;

            $this->fill($unit, $data);

            if ($logo) {
                $unit->logo_path = $this->media->storeImage($logo, 'units');
            } elseif ($removeLogo) {
                $unit->logo_path = null;
            }

            $unit->save();

            if ($previousLogo && $unit->logo_path !== $previousLogo) {
                $this->media->delete($previousLogo);
            }

            $this->publicCache->forget('units');

            return $unit;
        });
    }

    public function delete(Unit $unit): void
    {
        DB::connection('tenant')->transaction(function () use ($unit) {
            $this->media->delete($unit->logo_path);
            $unit->delete();
        });

        $this->publicCache->forget('units');
    }

    private function fill(Unit $unit, array $data): void
    {
        $unit->fill([
            'name' => $data['name'],
            'description' => Unit::normaliseTranslatable($data['description'] ?? []),
            'url' => $data['url'],
            'domain' => $data['domain'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
