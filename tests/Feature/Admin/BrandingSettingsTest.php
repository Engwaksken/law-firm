<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\ManageBranding;
use App\Support\Access;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    public function test_defaults_apply_before_anything_is_saved(): void
    {
        $this->assertSame('#14283F', Settings::get('color_primary'));
        $this->assertSame('UGX', Settings::get('default_currency'));
    }

    public function test_admin_saves_branding_and_it_is_used_immediately(): void
    {
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));

        Livewire::test(ManageBranding::class)
            ->fillForm([
                'firm_name' => 'Okello & Partners Advocates',
                'color_primary' => '#1A2B3C',
                'contact_email' => 'info@example.test',
                'social_links' => ['LinkedIn' => 'https://www.linkedin.com/company/example'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Okello & Partners Advocates', Settings::get('firm_name'));
        $this->assertSame('#1A2B3C', Settings::get('color_primary'));
        $this->assertSame(['LinkedIn' => 'https://www.linkedin.com/company/example'], Settings::get('social_links'));

        // Panel brand name reads the setting.
        $this->get('/admin')->assertSee('Okello &amp; Partners Advocates', false);
    }

    public function test_invalid_colours_and_unsafe_links_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));

        Livewire::test(ManageBranding::class)
            ->fillForm(['color_primary' => 'red; background:url(x)'])
            ->call('save')
            ->assertHasFormErrors(['color_primary']);

        Livewire::test(ManageBranding::class)
            ->fillForm(['social_links' => ['Evil' => 'javascript:alert(1)']])
            ->call('save')
            ->assertHasErrors(['data.social_links']);

        $this->assertSame([], Settings::get('social_links'));
    }

    public function test_settings_changes_are_audited(): void
    {
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));

        Livewire::test(ManageBranding::class)->fillForm(['firm_name' => 'Audited Firm'])->call('save');

        $this->assertDatabaseHas('activity_logs', ['event' => 'SiteSetting.created']);
    }

    public function test_users_without_settings_permission_cannot_save(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(ManageBranding::class)->assertForbidden();
    }
}
