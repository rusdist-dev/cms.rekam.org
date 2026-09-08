<?php

namespace Tests\Feature\PublicApi;

use App\Models\ContactMessage;
use Tests\TenantTestCase;

class ContactPublicApiTest extends TenantTestCase
{
    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenants->setCurrent($this->rekam);
        $this->key = $this->rekam->rotateApiKey();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rina Hartati',
            'email' => 'rina@example.com',
            'phone' => '081234567890',
            'subject' => 'Pertanyaan',
            'message' => 'Halo, saya ingin bertanya.',
            'website' => '', // honeypot, must stay empty
        ], $overrides);
    }

    private function postPublic(array $payload)
    {
        return $this->withHeaders(['X-Api-Key' => $this->key])->postJson('/api/v1/contact', $payload);
    }

    public function test_it_creates_a_message(): void
    {
        $this->postPublic($this->payload())->assertCreated();

        $this->assertSame(1, ContactMessage::count());
        $message = ContactMessage::first();
        $this->assertSame('unread', $message->status);
        $this->assertNotNull($message->ip);
    }

    public function test_the_caller_cannot_set_status_or_ip(): void
    {
        $this->postPublic($this->payload(['status' => 'archived', 'ip' => '1.2.3.4']))->assertCreated();

        $message = ContactMessage::first();
        $this->assertSame('unread', $message->status);
        $this->assertNotSame('1.2.3.4', $message->ip);
    }

    public function test_a_filled_honeypot_is_silently_discarded(): void
    {
        $response = $this->postPublic($this->payload(['website' => 'https://spam.example']));

        // Looks successful to the bot — it just never actually wrote a row.
        $response->assertCreated();
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_required_fields_are_validated(): void
    {
        $this->postPublic($this->payload(['name' => '', 'email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    public function test_contact_is_absent_for_a_tenant_without_the_feature(): void
    {
        $this->rekam->update(['features' => array_merge($this->rekam->features, ['contacts' => false])]);

        $this->postPublic($this->payload())->assertNotFound();
    }

    public function test_it_is_rate_limited(): void
    {
        $limit = config('cms.public_api.contact_rate_limit');

        for ($i = 0; $i < $limit; $i++) {
            $this->postPublic($this->payload())->assertCreated();
        }

        $this->postPublic($this->payload())->assertStatus(429);
    }
}
