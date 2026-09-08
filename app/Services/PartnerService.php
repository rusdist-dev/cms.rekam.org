<?php

namespace App\Services;

use App\Models\Partner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Multi-step write logic for partners: logo upload and append-to-end
 * ordering. Controllers stay thin (context.md §4.8).
 */
class PartnerService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, ?UploadedFile $logo = null): Partner
    {
        return DB::connection('tenant')->transaction(function () use ($data, $logo) {
            $partner = new Partner;

            $this->fill($partner, $data);
            $partner->sort_order = (int) Partner::max('sort_order') + 1;

            if ($logo) {
                $partner->logo_path = $this->media->storeImage($logo, 'partners');
            }

            $partner->save();
            $this->publicCache->forget('partners');

            return $partner;
        });
    }

    public function update(Partner $partner, array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Partner
    {
        return DB::connection('tenant')->transaction(function () use ($partner, $data, $logo, $removeLogo) {
            $previousLogo = $partner->logo_path;

            $this->fill($partner, $data);

            if ($logo) {
                $partner->logo_path = $this->media->storeImage($logo, 'partners');
            } elseif ($removeLogo) {
                $partner->logo_path = null;
            }

            $partner->save();

            if ($previousLogo && $partner->logo_path !== $previousLogo) {
                $this->media->delete($previousLogo);
            }

            $this->publicCache->forget('partners');

            return $partner;
        });
    }

    public function delete(Partner $partner): void
    {
        DB::connection('tenant')->transaction(function () use ($partner) {
            $this->media->delete($partner->logo_path);
            $partner->delete();
        });

        $this->publicCache->forget('partners');
    }

    private function fill(Partner $partner, array $data): void
    {
        $partner->fill([
            'name' => $data['name'],
            'title' => Partner::normaliseTranslatable($data['title'] ?? []),
            'url' => $data['url'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
