<?php

namespace App\Services;

use App\Models\TeamMember;
use App\Support\SlugMaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Multi-step write logic for team members: slug generation, photo upload,
 * and append-to-end ordering. Controllers stay thin (context.md §4.8).
 */
class TeamMemberService
{
    public function __construct(
        private readonly MediaService $media,
        private readonly PublicCacheService $publicCache,
    ) {}

    public function create(array $data, ?UploadedFile $photo = null): TeamMember
    {
        return DB::connection('tenant')->transaction(function () use ($data, $photo) {
            $member = new TeamMember;

            $this->fill($member, $data);
            $member->slug = SlugMaker::uniquePlain(TeamMember::class, $data['name']);
            $member->sort_order = (int) TeamMember::max('sort_order') + 1;

            if ($photo) {
                $member->photo_path = $this->media->storeImage($photo, 'team');
            }

            $member->save();
            $this->publicCache->forget('team');

            return $member;
        });
    }

    public function update(TeamMember $member, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): TeamMember
    {
        return DB::connection('tenant')->transaction(function () use ($member, $data, $photo, $removePhoto) {
            $previousPhoto = $member->photo_path;

            $this->fill($member, $data);

            if ($data['name'] !== $member->getOriginal('name')) {
                $member->slug = SlugMaker::uniquePlain(TeamMember::class, $data['name'], $member->id);
            }

            if ($photo) {
                $member->photo_path = $this->media->storeImage($photo, 'team');
            } elseif ($removePhoto) {
                $member->photo_path = null;
            }

            $member->save();

            // Only drop the old file once the row pointing at it is safely
            // saved — a failed save must not leave the member photo-less.
            if ($previousPhoto && $member->photo_path !== $previousPhoto) {
                $this->media->delete($previousPhoto);
            }

            $this->publicCache->forget('team');

            return $member;
        });
    }

    public function delete(TeamMember $member): void
    {
        DB::connection('tenant')->transaction(function () use ($member) {
            $this->media->delete($member->photo_path);
            $member->delete();
        });

        $this->publicCache->forget('team');
    }

    private function fill(TeamMember $member, array $data): void
    {
        $member->fill([
            'name' => $data['name'],
            'position' => TeamMember::normaliseTranslatable($data['position'] ?? []),
            'bio' => TeamMember::normaliseTranslatable($data['bio'] ?? []),
            'group' => $data['group'],
            'email' => $data['email'] ?? null,
            'socials' => [
                'linkedin' => $data['socials']['linkedin'] ?? '',
                'instagram' => $data['socials']['instagram'] ?? '',
            ],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
