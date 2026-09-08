<?php

namespace Tests\Feature\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

class SiteSettingsTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);

        Storage::fake('public');
    }

    public function test_identity_round_trip_with_a_logo_upload(): void
    {
        $this->getJson(route('dash-api.settings.identity.show'))
            ->assertOk()
            ->assertJsonPath('data.name', '');

        $response = $this->postJson(route('dash-api.settings.identity.update'), [
            'name' => 'Rekam',
            'tagline' => ['id' => 'Restorasi ekosistem', 'en' => null],
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertOk()->assertJsonPath('data.name', 'Rekam');
        $this->assertNotNull($response->json('data.logo.path'));

        $this->getJson(route('dash-api.settings.identity.show'))
            ->assertJsonPath('data.name', 'Rekam')
            ->assertJsonPath('data.tagline.id', 'Restorasi ekosistem');
    }

    public function test_identity_requires_a_name(): void
    {
        $this->postJson(route('dash-api.settings.identity.update'), ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_replacing_the_logo_deletes_the_previous_file(): void
    {
        $first = $this->postJson(route('dash-api.settings.identity.update'), [
            'name' => 'Rekam',
            'logo' => UploadedFile::fake()->image('first.png'),
        ])->json('data.logo.path');

        $second = $this->postJson(route('dash-api.settings.identity.update'), [
            'name' => 'Rekam',
            'logo' => UploadedFile::fake()->image('second.png'),
        ])->json('data.logo.path');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_seo_round_trip_with_keywords_and_og_image(): void
    {
        $this->postJson(route('dash-api.settings.seo.update'), [
            'meta_title' => ['id' => 'Judul Default', 'en' => null],
            'meta_description' => ['id' => 'Deskripsi default.', 'en' => null],
            'keywords' => ['konservasi', 'laut'],
            'og_image' => UploadedFile::fake()->image('og.png'),
        ])->assertOk();

        $this->getJson(route('dash-api.settings.seo.show'))
            ->assertJsonPath('data.meta_title.id', 'Judul Default')
            ->assertJsonPath('data.keywords', ['konservasi', 'laut'])
            ->assertJsonPath('data.og_image.name', null);
    }

    public function test_socials_round_trip(): void
    {
        // settingsForm.js always submits the whole form object — every field
        // present, even the ones the editor left untouched — so the request
        // here matches that rather than a partial payload.
        $this->putJson(route('dash-api.settings.show', 'socials'), [
            'instagram' => 'https://instagram.com/rekam',
            'linkedin' => '', 'youtube' => '', 'facebook' => '', 'x' => '', 'tiktok' => '',
        ])->assertOk();

        // The global ConvertEmptyStringsToNull middleware turns the untouched
        // '' fields into null before validation ever sees them — same as
        // every other optional field in the app (e.g. News's excerpt.en).
        $this->getJson(route('dash-api.settings.show', 'socials'))
            ->assertJsonPath('data.instagram', 'https://instagram.com/rekam')
            ->assertJsonPath('data.linkedin', null);
    }

    public function test_socials_validates_urls(): void
    {
        $this->putJson(route('dash-api.settings.show', 'socials'), ['instagram' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('instagram');
    }

    public function test_map_round_trip(): void
    {
        $this->putJson(route('dash-api.settings.show', 'map'), ['embed' => '<iframe></iframe>'])->assertOk();

        $this->getJson(route('dash-api.settings.show', 'map'))
            ->assertJsonPath('data.embed', '<iframe></iframe>');
    }

    public function test_an_unknown_settings_group_is_rejected(): void
    {
        $this->getJson('/dash-api/v1/settings/not-a-real-group')->assertNotFound();
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        $this->postJson(route('dash-api.settings.identity.update'), ['name' => 'Rekam'])->assertOk();

        $this->useTenant($this->perikanan);

        $this->getJson(route('dash-api.settings.identity.show'))->assertJsonPath('data.name', '');
    }

    public function test_a_viewer_can_read_but_not_update(): void
    {
        $this->actingAsUserWith('viewer');
        $this->useTenant($this->rekam);

        $this->getJson(route('dash-api.settings.identity.show'))->assertOk();
        $this->postJson(route('dash-api.settings.identity.update'), ['name' => 'Rekam'])->assertForbidden();
    }

    public function test_an_editor_can_read_but_not_update(): void
    {
        // Editors get settings.view only — writing site-wide settings sits
        // with admin/super-admin (RolePermissionSeeder::editorPermissions()).
        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->getJson(route('dash-api.settings.show', 'socials'))->assertOk();
        $this->putJson(route('dash-api.settings.show', 'socials'), [])->assertForbidden();
    }
}
