<?php

namespace Tests\Feature\Admin\Cms;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Models\Event;
use App\Models\Media;
use App\Models\Role;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EventsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    private function writerWithoutPublish(): User
    {
        $role = Role::create(['name' => 'writer', 'guard_name' => 'web']);
        $role->givePermissionTo(['content.view', 'content.manage']);

        return User::factory()->withRole('writer')->create();
    }

    private function eventData(array $overrides = []): array
    {
        return [
            'title' => 'Data protection seminar',
            'slug' => 'data-protection-seminar',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i'),
            'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i'),
            'location' => 'Kampala',
            'status' => 'published',
            ...$overrides,
        ];
    }

    public function test_publisher_creates_a_published_event_with_a_library_image(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));
        $image = Media::factory()->create(['mime_type' => 'image/jpeg']);

        Livewire::test(CreateEvent::class)
            ->fillForm($this->eventData(['image_id' => $image->id]))
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::sole();
        $this->assertSame('published', $event->status);
        $this->assertNotNull($event->published_at);
        $this->assertSame($image->id, $event->image_id);
    }

    public function test_non_publisher_can_only_save_drafts(): void
    {
        $this->actingAs($this->writerWithoutPublish());

        Livewire::test(CreateEvent::class)->fillForm($this->eventData())->call('create')->assertHasNoFormErrors();

        $event = Event::sole();
        $this->assertSame('draft', $event->status);

        Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
            ->fillForm(['status' => 'cancelled'])->call('save');

        $this->assertSame('draft', $event->fresh()->status);
    }

    public function test_end_before_start_and_unsafe_registration_links_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(CreateEvent::class)
            ->fillForm($this->eventData([
                'ends_at' => now()->addDays(2)->format('Y-m-d H:i'),
                'registration_url' => 'javascript:alert(1)',
            ]))
            ->call('create')
            ->assertHasFormErrors(['ends_at', 'registration_url']);
    }

    public function test_media_picker_rejects_non_image_media(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));
        $pdf = Media::factory()->create(['mime_type' => 'application/pdf', 'path' => 'media/x.pdf']);

        Livewire::test(CreateEvent::class)
            ->fillForm($this->eventData(['image_id' => $pdf->id]))
            ->call('create')
            ->assertHasFormErrors(['image_id']);
    }

    /** @return array<string, array{string}> */
    public static function cmsUrls(): array
    {
        return ['media' => ['/admin/media'], 'menus' => ['/admin/menus'], 'events' => ['/admin/events'], 'create event' => ['/admin/events/create']];
    }

    #[DataProvider('cmsUrls')]
    public function test_users_without_content_permissions_are_denied(string $url): void
    {
        $this->actingAs($this->userWithRole(Access::FINANCE_OFFICER))->get($url)->assertForbidden();
    }

    #[DataProvider('cmsUrls')]
    public function test_cms_pages_render_for_content_editors(string $url): void
    {
        $media = Media::factory()->create(['mime_type' => 'image/jpeg']);
        Media::factory()->create(['mime_type' => 'application/pdf', 'path' => 'media/doc.pdf']);
        Event::factory()->published()->create(['image_id' => $media->id]);

        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR))->get($url)->assertOk();
    }

    public function test_edit_pages_render(): void
    {
        $editor = $this->userWithRole(Access::CONTENT_EDITOR);
        $media = Media::factory()->create(['mime_type' => 'image/jpeg']);
        $event = Event::factory()->published()->create();
        $menu = \App\Models\Menu::create(['name' => 'Main', 'location' => 'header']);

        $this->actingAs($editor);
        $this->get("/admin/media/{$media->id}/edit")->assertOk();
        $this->get("/admin/events/{$event->getRouteKey()}/edit")->assertOk();
        $this->get("/admin/menus/{$menu->id}/edit")->assertOk();
        $this->get('/admin/media/create')->assertOk();
        $this->get('/admin/menus/create')->assertOk();
    }
}
