<?php

namespace Tests\Feature\Admin\Website;

use App\Filament\Pages\ManageWebsiteContent;
use App\Filament\Resources\Advocates\Pages\EditAdvocate;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\PracticeAreas\Pages\CreatePracticeArea;
use App\Filament\Resources\PracticeAreas\Pages\EditPracticeArea;
use App\Filament\Resources\PracticeAreas\Pages\ListPracticeAreas;
use App\Filament\Resources\Testimonials\Pages\CreateTestimonial;
use App\Models\Advocate;
use App\Models\Article;
use App\Models\PracticeArea;
use App\Models\Role;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\Access;
use App\Support\Settings;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WebsiteContentAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    /** A writer who can draft content but not publish it. */
    private function writer(): User
    {
        Role::create(['name' => 'writer', 'guard_name' => 'web'])->givePermissionTo(['content.view', 'content.manage']);

        return User::factory()->withRole('writer')->create();
    }

    public function test_content_editor_can_create_edit_and_delete_practice_areas(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(CreatePracticeArea::class)
            ->fillForm([
                'name' => 'Land and Property Law',
                'slug' => 'land-and-property-law',
                'summary' => 'Conveyancing, title disputes and leases.',
                'is_active' => true,
                'faqs' => [['question' => 'Do you handle mailo land?', 'answer' => 'Yes.']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $area = PracticeArea::where('slug', 'land-and-property-law')->firstOrFail();
        $this->assertSame('Do you handle mailo land?', $area->faqs[0]['question']);

        Livewire::test(EditPracticeArea::class, ['record' => $area->getRouteKey()])
            ->fillForm(['summary' => 'Updated summary'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Updated summary', $area->fresh()->summary);

        Livewire::test(ListPracticeAreas::class)
            ->callTableAction(DeleteAction::class, $area);
        $this->assertSoftDeleted($area);
    }

    public function test_receptionist_cannot_manage_practice_areas(): void
    {
        $this->actingAs($this->userWithRole(Access::RECEPTIONIST))
            ->get('/admin/practice-areas')
            ->assertForbidden();
    }

    public function test_user_without_publish_permission_can_only_save_drafts(): void
    {
        $this->actingAs($this->writer());

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'New employment regulations',
                'slug' => 'new-employment-regulations',
                'type' => 'legal-update',
                'body' => '<p>Body</p>',
                'status' => 'published',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('slug', 'new-employment-regulations')->firstOrFail();
        $this->assertSame('draft', $article->status);
        $this->assertFalse(Article::query()->published()->whereKey($article->id)->exists());
    }

    public function test_publisher_publishing_without_a_date_publishes_now(): void
    {
        $editor = $this->userWithRole(Access::CONTENT_EDITOR);
        $this->actingAs($editor);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Court fees update',
                'slug' => 'court-fees-update',
                'type' => 'legal-update',
                'body' => '<p>Body</p>',
                'status' => 'published',
                'published_at' => null,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $article = Article::where('slug', 'court-fees-update')->firstOrFail();
        $this->assertTrue($article->isPublished());
        $this->assertSame($editor->id, $article->author_id);
    }

    public function test_verify_action_marks_credentials_and_editing_credentials_revokes_verification(): void
    {
        $editor = $this->userWithRole(Access::CONTENT_EDITOR);
        $this->actingAs($editor);
        $advocate = Advocate::factory()->create(['is_public' => true, 'phone' => '+256 700 000000']);

        Livewire::test(EditAdvocate::class, ['record' => $advocate->getRouteKey()])
            ->callAction('verifyCredentials');

        $advocate->refresh();
        $this->assertNotNull($advocate->credentials_verified_at);
        $this->assertSame($editor->id, $advocate->credentials_verified_by);
        $this->assertTrue(Advocate::query()->published()->whereKey($advocate->id)->exists());

        // A non-credential edit keeps verification…
        Livewire::test(EditAdvocate::class, ['record' => $advocate->getRouteKey()])
            ->fillForm(['bio' => 'New biography'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertTrue($advocate->fresh()->isVerified());

        // …changing qualifications clears it.
        Livewire::test(EditAdvocate::class, ['record' => $advocate->getRouteKey()])
            ->fillForm(['qualifications' => ['LLB', 'LLM (Harvard)']])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertFalse($advocate->fresh()->isVerified());
        $this->assertFalse(Advocate::query()->published()->whereKey($advocate->id)->exists());
    }

    public function test_verify_action_is_hidden_from_users_without_publish_permission(): void
    {
        $this->actingAs($this->writer());
        $advocate = Advocate::factory()->create();

        Livewire::test(EditAdvocate::class, ['record' => $advocate->getRouteKey()])
            ->assertActionHidden('verifyCredentials');
    }

    public function test_writer_cannot_approve_testimonials(): void
    {
        $this->actingAs($this->writer());

        Livewire::test(CreateTestimonial::class)
            ->fillForm(['author_name' => 'A Client', 'quote' => 'Excellent service.', 'consent_obtained' => true, 'is_approved' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertFalse(Testimonial::firstOrFail()->is_approved);
    }

    public function test_publisher_approval_records_approver(): void
    {
        $editor = $this->userWithRole(Access::CONTENT_EDITOR);
        $this->actingAs($editor);

        Livewire::test(CreateTestimonial::class)
            ->fillForm(['author_name' => 'A Client', 'quote' => 'Excellent service.', 'consent_obtained' => true, 'is_approved' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $t = Testimonial::firstOrFail();
        $this->assertTrue($t->is_approved);
        $this->assertSame($editor->id, $t->approved_by);
    }

    public function test_website_content_page_saves_settings(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(ManageWebsiteContent::class)
            ->fillForm([
                'home_hero_title' => 'Advocates you can rely on',
                'home_stats' => [['value' => 25, 'suffix' => '+', 'label' => 'Years in practice']],
                'about_mission' => 'To provide excellent legal services.',
                'about_affiliations' => [['name' => 'Uganda Law Society', 'url' => 'https://uls.or.ug']],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Advocates you can rely on', Settings::get('home_hero_title'));
        $this->assertSame('Years in practice', Settings::get('home_stats')[0]['label']);
        $this->assertSame('To provide excellent legal services.', Settings::get('about_mission'));
        $this->assertSame('Uganda Law Society', Settings::get('about_affiliations')[0]['name']);
    }

    public function test_website_content_rejects_non_http_affiliation_links(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(ManageWebsiteContent::class)
            ->fillForm(['about_affiliations' => [['name' => 'Bad', 'url' => 'javascript:alert(1)']]])
            ->call('save')
            ->assertHasFormErrors();
    }

    public function test_website_content_page_denied_without_content_permission(): void
    {
        $this->actingAs($this->userWithRole(Access::FINANCE_OFFICER))
            ->get('/admin/settings/website-content')
            ->assertForbidden();
    }
}
