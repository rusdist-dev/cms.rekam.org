<?php

namespace App\Support;

/**
 * Empty shape of each form model.
 *
 * Alpine needs every key to exist before the first render — an undefined
 * `form.title.en` breaks two-way binding on the EN tab. Defining the shapes
 * here keeps them out of the Blade files and identical between create and edit.
 */
class FormDefaults
{
    /** Translatable field: one entry per configured content locale. */
    private static function translatable(?string $value = null): array
    {
        return array_fill_keys(config('cms.locales'), $value);
    }

    public static function news(): array
    {
        return [
            'title' => self::translatable(),
            'slug' => self::translatable(),
            'excerpt' => self::translatable(),
            'body' => self::translatable(),
            'meta_title' => self::translatable(),
            'meta_description' => self::translatable(),
            'category_id' => '',
            'related_programs' => [],
            'cover' => null,
            'status' => 'draft',
            'published_at' => null,
            'author_name' => '',
        ];
    }

    public static function event(): array
    {
        return [
            'title' => self::translatable(),
            'slug' => self::translatable(),
            'description' => self::translatable(),
            'location' => self::translatable(),
            'fee_note' => self::translatable(),
            'meta_title' => self::translatable(),
            'meta_description' => self::translatable(),
            'category' => '',
            'start_at' => null,
            'end_at' => null,
            'is_all_day' => false,
            'fee' => null,
            'quota' => null,
            'registration_url' => '',
            'cover' => null,
            'status' => 'draft',
            'rundowns' => [],
        ];
    }

    public static function teamMember(): array
    {
        return [
            'name' => '',
            'slug' => '',
            'position' => self::translatable(),
            'bio' => self::translatable(),
            'photo' => null,
            'group' => '',
            'email' => '',
            'socials' => ['linkedin' => '', 'instagram' => ''],
            'is_active' => true,
        ];
    }

    public static function publication(): array
    {
        return [
            'title' => self::translatable(),
            'description' => self::translatable(),
            'category' => '',
            'file' => null,
            'cover' => null,
            'is_featured' => false,
        ];
    }

    public static function partner(): array
    {
        return [
            'name' => '',
            'title' => self::translatable(),
            'logo' => null,
            'url' => '',
            'is_active' => true,
        ];
    }

    public static function milestone(): array
    {
        return [
            'title' => self::translatable(),
            'body' => self::translatable(),
            'cover' => null,
            'year' => null,
            'is_active' => true,
        ];
    }

    public static function unit(): array
    {
        return [
            'name' => '',
            'description' => self::translatable(),
            'url' => '',
            'domain' => '',
            'logo' => null,
            'is_active' => true,
        ];
    }

    public static function user(): array
    {
        return [
            'name' => '',
            'email' => '',
            'password' => '',
            'password_confirmation' => '',
            'role' => '',
            'tenants' => [],
            'is_active' => true,
        ];
    }

    public static function role(): array
    {
        return [
            'name' => '',
            'label' => '',
            'permissions' => [],
        ];
    }

    /** Blank rundown row used by the repeater when a new line is added. */
    public static function rundownRow(): array
    {
        return [
            'time' => '',
            'title' => self::translatable(''),
            'description' => self::translatable(''),
        ];
    }
}
