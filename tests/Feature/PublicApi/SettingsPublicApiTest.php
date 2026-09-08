<?php

namespace Tests\Feature\PublicApi;

use App\Models\SiteSetting;
use Tests\TenantTestCase;

class SettingsPublicApiTest extends TenantTestCase
{
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();
    }

    private function getPublic(string $uri)
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])->getJson($uri);
    }

    public function test_it_merges_every_settings_group(): void
    {
        SiteSetting::put('site', 'identity', ['name' => 'Rekam', 'tagline' => ['id' => 'Restorasi ekosistem', 'en' => null]]);
        SiteSetting::put('site', 'socials', ['instagram' => 'https://instagram.com/rekam']);
        SiteSetting::put('site', 'seo', ['meta_title' => ['id' => 'Rekam - Beranda', 'en' => null], 'keywords' => ['konservasi']]);
        SiteSetting::put('site', 'map', ['embed' => '<iframe></iframe>']);
        SiteSetting::put('contact', 'info', ['email' => 'info@rekam.org', 'address' => ['id' => 'Jakarta', 'en' => null]]);

        $response = $this->getPublic('/api/v1/settings');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Rekam')
            ->assertJsonPath('data.tagline', 'Restorasi ekosistem')
            ->assertJsonPath('data.socials.instagram', 'https://instagram.com/rekam')
            ->assertJsonPath('data.meta_title', 'Rekam - Beranda')
            ->assertJsonPath('data.keywords', ['konservasi'])
            ->assertJsonPath('data.map_embed', '<iframe></iframe>')
            ->assertJsonPath('data.contact.email', 'info@rekam.org')
            ->assertJsonPath('data.contact.address', 'Jakarta');
    }

    public function test_it_defaults_gracefully_when_nothing_has_been_saved(): void
    {
        $this->getPublic('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.name', '')
            ->assertJsonPath('data.tagline', null);
    }

    public function test_settings_are_isolated_per_tenant(): void
    {
        SiteSetting::put('site', 'identity', ['name' => 'Rekam']);

        $perikananKey = $this->perikanan->rotateApiKey();

        $this->withHeaders(['X-Api-Key' => $perikananKey])
            ->getJson('/api/v1/settings')
            ->assertJsonPath('data.name', '');
    }
}
