<?php

namespace Tests\Feature\PublicApi;

use Tests\TenantTestCase;

class TenantResolutionTest extends TenantTestCase
{
    public function test_a_missing_key_is_rejected(): void
    {
        $this->getJson('/api/v1/news')->assertUnauthorized();
    }

    public function test_an_invalid_key_is_rejected(): void
    {
        $this->withHeaders(['X-Api-Key' => 'not-a-real-key'])
            ->getJson('/api/v1/news')
            ->assertUnauthorized();
    }

    public function test_a_key_with_no_underscore_is_rejected(): void
    {
        $this->withHeaders(['X-Api-Key' => 'malformed'])
            ->getJson('/api/v1/news')
            ->assertUnauthorized();
    }

    public function test_a_valid_key_resolves_the_correct_tenant(): void
    {
        $rekamKey = $this->rekam->rotateApiKey();
        $perikananKey = $this->perikanan->rotateApiKey();

        $this->tenants->setCurrent($this->rekam);
        \App\Models\News::factory()->published()->create(['title' => ['id' => 'Berita Rekam', 'en' => null]]);

        $this->tenants->setCurrent($this->perikanan);
        \App\Models\News::factory()->published()->create(['title' => ['id' => 'Berita Perikanan', 'en' => null]]);

        $this->withHeaders(['X-Api-Key' => $rekamKey])
            ->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Berita Rekam')
            ->assertJsonCount(1, 'data');

        $this->withHeaders(['X-Api-Key' => $perikananKey])
            ->getJson('/api/v1/news')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Berita Perikanan')
            ->assertJsonCount(1, 'data');
    }

    public function test_rotating_invalidates_the_previous_key(): void
    {
        $first = $this->rekam->rotateApiKey();
        $this->rekam->rotateApiKey();

        $this->withHeaders(['X-Api-Key' => $first])
            ->getJson('/api/v1/news')
            ->assertUnauthorized();
    }

    public function test_an_inactive_tenants_key_is_rejected(): void
    {
        $key = $this->rekam->rotateApiKey();
        $this->rekam->update(['is_active' => false]);

        $this->withHeaders(['X-Api-Key' => $key])
            ->getJson('/api/v1/news')
            ->assertUnauthorized();
    }
}
