<?php

namespace Tests\Feature\Admin\Cms;

use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Models\Event;
use App\Models\Media;
use App\Support\Access;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
        Storage::fake('public');
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));
    }

    public function test_image_upload_stores_file_and_reads_dimensions(): void
    {
        Livewire::test(CreateMedia::class)
            ->fillForm([
                'path' => UploadedFile::fake()->image('office.jpg', 640, 480),
                'alt_text' => 'The firm reception area',
                'collection' => 'default',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::sole();
        Storage::disk('public')->assertExists($media->path);
        $this->assertStringStartsWith('media/'.now()->format('Y/m'), $media->path);
        $this->assertSame(640, $media->width);
        $this->assertSame(480, $media->height);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame('office.jpg', $media->original_name);
        $this->assertSame(auth()->id(), $media->uploaded_by);
    }

    public function test_alt_text_is_required_for_images(): void
    {
        Livewire::test(CreateMedia::class)
            ->fillForm(['path' => UploadedFile::fake()->image('photo.png'), 'collection' => 'default'])
            ->call('create')
            ->assertHasFormErrors(['alt_text' => 'required']);

        $this->assertSame(0, Media::count());
    }

    public function test_alt_text_is_optional_for_documents(): void
    {
        Livewire::test(CreateMedia::class)
            ->fillForm(['path' => UploadedFile::fake()->create('brochure.pdf', 100, 'application/pdf'), 'collection' => 'default'])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::sole();
        $this->assertSame('application/pdf', $media->mime_type);
        $this->assertNull($media->width);
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        Livewire::test(CreateMedia::class)
            ->fillForm(['path' => UploadedFile::fake()->create('script.php', 1, 'application/x-php'), 'alt_text' => 'x', 'collection' => 'default'])
            ->call('create')
            ->assertHasFormErrors(['path']);
    }

    public function test_editing_changes_metadata_only(): void
    {
        $media = Media::factory()->create(['path' => 'media/a.jpg', 'alt_text' => 'Old']);

        Livewire::test(EditMedia::class, ['record' => $media->getRouteKey()])
            ->fillForm(['alt_text' => 'New description', 'title' => 'Team photo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $media->refresh();
        $this->assertSame('New description', $media->alt_text);
        $this->assertSame('media/a.jpg', $media->path);
    }

    public function test_force_delete_is_blocked_while_the_file_is_used(): void
    {
        Storage::disk('public')->put('media/used.jpg', 'x');
        $media = Media::factory()->create(['path' => 'media/used.jpg']);
        Event::factory()->create(['image_id' => $media->id]);
        $media->delete();

        Livewire::test(ListMedia::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make('forceDelete')->table($media));

        $this->assertNotNull(Media::withTrashed()->find($media->id));
        Storage::disk('public')->assertExists('media/used.jpg');
    }

    public function test_force_delete_removes_record_and_file_when_unused(): void
    {
        Storage::disk('public')->put('media/unused.jpg', 'x');
        $media = Media::factory()->create(['path' => 'media/unused.jpg']);
        $media->delete();

        Livewire::test(ListMedia::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make('forceDelete')->table($media));

        $this->assertNull(Media::withTrashed()->find($media->id));
        Storage::disk('public')->assertMissing('media/unused.jpg');
    }
}
