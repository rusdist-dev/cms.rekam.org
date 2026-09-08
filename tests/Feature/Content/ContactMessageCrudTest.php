<?php

namespace Tests\Feature\Content;

use App\Models\ContactMessage;
use Tests\TenantTestCase;

class ContactMessageCrudTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
        $this->useTenant($this->rekam);
    }

    public function test_opening_a_message_marks_it_read(): void
    {
        $message = ContactMessage::factory()->create(['status' => 'unread']);

        $this->getJson(route('dash-api.contacts.show', $message))
            ->assertOk()
            ->assertJsonPath('data.status', 'read');

        $this->assertSame('read', $message->fresh()->status);
    }

    public function test_opening_an_already_read_message_does_not_touch_it_again(): void
    {
        $message = ContactMessage::factory()->archived()->create();

        $this->getJson(route('dash-api.contacts.show', $message))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    public function test_it_archives_a_message(): void
    {
        $message = ContactMessage::factory()->create();

        $this->patchJson(route('dash-api.contacts.archive', $message))
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertSame('archived', $message->fresh()->status);
    }

    public function test_it_deletes_a_message_permanently(): void
    {
        $message = ContactMessage::factory()->create();

        $this->deleteJson(route('dash-api.contacts.destroy', $message))->assertNoContent();

        $this->assertSame(0, ContactMessage::count());
    }

    public function test_filters_narrow_the_list(): void
    {
        ContactMessage::factory()->create(['status' => 'unread']);
        ContactMessage::factory()->archived()->create();

        $this->assertSame(2, $this->getJson(route('dash-api.contacts.index'))->json('meta.total'));
        $this->assertSame(1, $this->getJson(route('dash-api.contacts.index', ['status' => 'archived']))->json('meta.total'));
    }

    public function test_an_editor_can_archive_but_not_delete(): void
    {
        $message = ContactMessage::factory()->create();

        $this->actingAsUserWith('editor');
        $this->useTenant($this->rekam);

        $this->patchJson(route('dash-api.contacts.archive', $message))->assertOk();
        $this->deleteJson(route('dash-api.contacts.destroy', $message))->assertForbidden();
    }

    public function test_messages_are_invisible_from_the_other_company(): void
    {
        ContactMessage::factory()->count(2)->create();

        $this->useTenant($this->perikanan);

        $this->assertSame(0, ContactMessage::count());
        $this->assertSame(0, $this->getJson(route('dash-api.contacts.index'))->json('meta.total'));
    }

    public function test_settings_round_trip(): void
    {
        $this->getJson(route('dash-api.contacts.settings.show'))
            ->assertOk()
            ->assertJsonPath('data.email', '');

        $payload = [
            'email' => 'info@rekam.org',
            'phone' => '0210000000',
            'whatsapp' => '6281234567890',
            'address' => ['id' => 'Jakarta, Indonesia', 'en' => null],
            'map_embed' => '<iframe></iframe>',
        ];

        $this->putJson(route('dash-api.contacts.settings.update'), $payload)->assertOk();

        $this->getJson(route('dash-api.contacts.settings.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'info@rekam.org')
            ->assertJsonPath('data.address.id', 'Jakarta, Indonesia');
    }

    public function test_settings_require_an_email(): void
    {
        $this->putJson(route('dash-api.contacts.settings.update'), [
            'email' => '',
            'address' => ['id' => 'Jakarta'],
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_viewer_cannot_update_settings(): void
    {
        // Editors are deliberately granted contacts.update too (they write and
        // arrange contact info); only view-only roles are locked out.
        $this->actingAsUserWith('viewer');
        $this->useTenant($this->rekam);

        $this->putJson(route('dash-api.contacts.settings.update'), [
            'email' => 'info@rekam.org',
            'address' => ['id' => 'Jakarta'],
        ])->assertForbidden();
    }
}
