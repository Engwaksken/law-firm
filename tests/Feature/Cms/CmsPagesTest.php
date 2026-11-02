<?php

namespace Tests\Feature\Cms;

use App\Cms\PageBlocks;
use App\Cms\PageWorkflow;
use App\Filament\Pages\ManageSeo;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Notifications\Cms\PageReviewUpdate;
use App\Support\Access;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class CmsPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
    }

    /** Can edit content but not publish: the role the workflow exists for. */
    private function author(): User
    {
        Role::firstOrCreate(['name' => 'junior-editor', 'guard_name' => 'web'])->syncPermissions(['content.view', 'content.manage']);

        return User::factory()->withRole('junior-editor')->create();
    }

    private function textBlock(string $text): array
    {
        return ['type' => 'rich_text', 'data' => ['heading' => null, 'body' => "<p>{$text}</p>", 'width' => 'narrow', 'is_active' => true]];
    }

    public function test_published_page_renders_at_its_slug(): void
    {
        Page::factory()->published()->withBlocks([$this->textBlock('Hello from the page builder')])
            ->create(['title' => 'Pro Bono Work', 'slug' => 'pro-bono']);

        $this->get('/pro-bono')->assertOk()->assertSee('Pro Bono Work')->assertSee('Hello from the page builder');
    }

    public function test_draft_in_review_archived_and_scheduled_pages_are_not_public(): void
    {
        foreach (['draft', 'in_review', 'archived'] as $status) {
            Page::factory()->create(['slug' => "p-{$status}", 'status' => $status, 'published_at' => now()->subDay()]);
            $this->get("/p-{$status}")->assertNotFound();
        }
        Page::factory()->scheduled()->create(['slug' => 'future']);
        $this->get('/future')->assertNotFound();
    }

    public function test_every_block_type_renders(): void
    {
        $image = Media::factory()->create(['mime_type' => 'image/jpeg', 'alt_text' => 'Courtroom']);
        $data = [
            'hero' => ['title' => 'Hero title', 'subtitle' => 'Sub', 'image_id' => $image->id, 'primary_label' => 'Go', 'primary_url' => '/contact', 'style' => 'dark'],
            'rich_text' => ['body' => '<p>Rich body</p>'],
            'text_image' => ['heading' => 'TI heading', 'body' => '<p>TI body</p>', 'image_id' => $image->id],
            'image' => ['image_id' => $image->id, 'caption' => 'A caption'],
            'cards' => ['heading' => 'Cards heading', 'items' => [['title' => 'Card one', 'text' => 'x', 'url' => '/about']]],
            'stats' => ['items' => [['value' => 42, 'suffix' => '+', 'label' => 'Stat label']]],
            'faq' => ['heading' => 'FAQ heading', 'items' => [['question' => 'Q one?', 'answer' => 'A one']]],
            'quote' => ['quote' => 'Quote text', 'attribution' => 'Someone'],
            'cta' => ['title' => 'CTA title', 'button_label' => 'Act', 'button_url' => 'https://example.org'],
            'practice_areas' => ['heading' => 'Areas', 'featured_only' => false, 'limit' => 3],
            'advocates' => ['heading' => 'People', 'limit' => 4],
            'latest_insights' => ['heading' => 'Insights', 'limit' => 3],
            'upcoming_events' => ['heading' => 'Events', 'limit' => 3],
            'testimonials' => ['heading' => 'Testimonials', 'limit' => 3],
            'form' => ['heading' => 'Form heading', 'form' => 'contact'],
        ];
        $this->assertEqualsCanonicalizing(array_keys(PageBlocks::TYPES), array_keys($data), 'Test covers every block type');

        Page::factory()->published()
            ->withBlocks(collect($data)->map(fn ($d, $type) => ['type' => $type, 'data' => $d])->values()->all())
            ->create(['slug' => 'all-blocks']);

        $this->get('/all-blocks')->assertOk()
            ->assertSee('Hero title')->assertSee('Rich body')->assertSee('TI heading')->assertSee('A caption')
            ->assertSee('Card one')->assertSee('Stat label')->assertSee('Q one?')->assertSee('Quote text')
            ->assertSee('CTA title')->assertSee('Form heading')->assertSee('alt="Courtroom"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_hidden_blocks_are_not_rendered(): void
    {
        $hidden = $this->textBlock('Invisible text');
        $hidden['data']['is_active'] = false;
        Page::factory()->published()->withBlocks([$hidden, $this->textBlock('Visible text')])->create(['slug' => 'mixed']);

        $this->get('/mixed')->assertSee('Visible text')->assertDontSee('Invisible text');
    }

    public function test_unsafe_links_and_html_in_blocks_are_neutralised(): void
    {
        Page::factory()->published()->withBlocks([
            ['type' => 'cta', 'data' => ['title' => 'Click', 'button_label' => 'Evil', 'button_url' => 'javascript:alert(1)']],
            ['type' => 'rich_text', 'data' => ['body' => '<p>ok</p><script>alert(2)</script>']],
        ])->create(['slug' => 'unsafe']);

        $this->get('/unsafe')->assertOk()
            ->assertDontSee('javascript:alert(1)', false)
            ->assertDontSee('<script>alert(2)</script>', false);
    }

    public function test_reserved_and_malformed_slugs_are_rejected(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        foreach (['admin', 'careers', 'Bad Slug', 'events/x'] as $slug) {
            Livewire::test(CreatePage::class)
                ->fillForm(['title' => 'X', 'slug' => $slug, 'template' => 'default'])
                ->call('create')
                ->assertHasFormErrors(['slug']);
        }
    }

    public function test_cms_pages_never_shadow_built_in_routes(): void
    {
        Page::factory()->published()->withBlocks([$this->textBlock('Fake about')])->create(['slug' => 'about-us']);
        // Force a reserved slug directly in the DB (bypassing validation).
        Page::factory()->published()->withBlocks([$this->textBlock('Hijack')])->create(['slug' => 'contact']);

        $this->get('/contact')->assertOk()->assertDontSee('Hijack');
    }

    public function test_author_creates_draft_and_cannot_publish(): void
    {
        $author = $this->author();
        $this->actingAs($author);

        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Our Approach', 'slug' => 'our-approach', 'template' => 'default', 'content_blocks' => [$this->textBlock('Draft text')]])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::where('slug', 'our-approach')->sole();
        $this->assertSame('draft', $page->status);
        $this->assertCount(1, $page->blocks);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertActionHidden('publish')
            ->assertActionVisible('submit');

        $this->expectException(AuthorizationException::class);
        app(PageWorkflow::class)->publish($page, $author);
    }

    public function test_submit_notifies_publishers_and_publisher_publishes_with_revision(): void
    {
        Notification::fake();
        $author = $this->author();
        $publisher = $this->userWithRole(Access::CONTENT_EDITOR);
        $page = Page::factory()->withBlocks([$this->textBlock('Version one')])->create(['slug' => 'v-page']);

        $this->actingAs($author);
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->callAction('submit');
        $this->assertSame('in_review', $page->fresh()->status);
        Notification::assertSentTo($publisher, PageReviewUpdate::class, fn ($n) => $n->event === 'submitted');

        $this->actingAs($publisher);
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('publish', data: ['published_at' => now()->subMinute()->toDateTimeString()]);

        $page->refresh();
        $this->assertTrue($page->isPublished());
        $this->assertSame($publisher->id, $page->published_by);
        $this->assertSame(1, $page->revisions()->where('reason', 'published')->count());
        Notification::assertSentTo($author, PageReviewUpdate::class, fn ($n) => $n->event === 'published');
        $this->get('/v-page')->assertOk()->assertSee('Version one');
    }

    public function test_edits_to_a_live_page_stay_private_until_published(): void
    {
        $publisher = $this->userWithRole(Access::CONTENT_EDITOR);
        $page = Page::factory()->withBlocks([$this->textBlock('Approved wording')])->create(['slug' => 'fees']);
        app(PageWorkflow::class)->publish($page, $publisher);

        // An author without publishing rights edits the live page.
        $this->actingAs($this->author());
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['content_blocks' => [$this->textBlock('Unapproved wording')]])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertTrue($page->hasUnpublishedChanges());
        $this->get('/fees')->assertSee('Approved wording')->assertDontSee('Unapproved wording');
        $this->get(route('pages.preview', $page))->assertOk()->assertSee('Unapproved wording');

        app(PageWorkflow::class)->publish($page, $publisher);
        $this->get('/fees')->assertSee('Unapproved wording');
    }

    public function test_author_cannot_publish_by_tampering_with_form_data(): void
    {
        $page = Page::factory()->create(['slug' => 'tamper']);
        $this->actingAs($this->author());

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->set('data.status', 'published')
            ->set('data.published_at', now()->subDay()->toDateTimeString())
            ->call('save');

        $this->assertSame('draft', $page->fresh()->status);
        $this->get('/tamper')->assertNotFound();
    }

    public function test_request_changes_returns_page_to_author_with_notes(): void
    {
        Notification::fake();
        $author = $this->author();
        $publisher = $this->userWithRole(Access::CONTENT_EDITOR);
        $page = Page::factory()->create();
        app(PageWorkflow::class)->submit($page, $author);

        $this->actingAs($publisher);
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
            ->callAction('requestChanges', data: ['notes' => 'Please cite the statute.']);

        $page->refresh();
        $this->assertSame('draft', $page->status);
        $this->assertSame('Please cite the statute.', $page->review_notes);
        Notification::assertSentTo($author, PageReviewUpdate::class, fn ($n) => $n->event === 'changes_requested' && $n->notes === 'Please cite the statute.');
    }

    public function test_restoring_a_revision_updates_working_copy_only(): void
    {
        $publisher = $this->userWithRole(Access::CONTENT_EDITOR);
        $this->actingAs($publisher);
        $page = Page::factory()->withBlocks([$this->textBlock('First version')])->create(['slug' => 'history']);
        app(PageWorkflow::class)->publish($page, $publisher);
        $first = $page->liveRevision();

        $page->syncBlocks([$this->textBlock('Second version')]);
        app(PageWorkflow::class)->publish($page->refresh(), $publisher);

        $page->restoreRevision($first);

        $this->assertStringContainsString('First version', json_encode($page->fresh()->snapshot()));
        $this->get('/history')->assertSee('Second version');
        $this->assertTrue($page->fresh()->hasUnpublishedChanges());
    }

    public function test_workflow_transitions_are_audited(): void
    {
        $publisher = $this->userWithRole(Access::CONTENT_EDITOR);
        $this->actingAs($publisher);
        $page = Page::factory()->create();

        app(PageWorkflow::class)->publish($page, $publisher);
        app(PageWorkflow::class)->unpublish($page, $publisher);

        $this->assertDatabaseHas('activity_logs', ['event' => 'page.published', 'subject_id' => $page->id]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'page.unpublished', 'subject_id' => $page->id]);
    }

    public function test_preview_requires_signed_in_staff_with_content_access(): void
    {
        $page = Page::factory()->withBlocks([$this->textBlock('Secret draft')])->create();

        $this->get(route('pages.preview', $page))->assertRedirect(route('filament.admin.auth.login'));

        $this->actingAs($this->userWithRole(Access::FINANCE_OFFICER))
            ->get(route('pages.preview', $page))->assertForbidden();

        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR))
            ->get(route('pages.preview', $page))->assertOk()
            ->assertSee('Secret draft')->assertSee('Preview:')
            ->assertSee('noindex', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_cms_page_can_replace_the_homepage_and_its_slug_redirects(): void
    {
        $page = Page::factory()->published()->withBlocks([
            ['type' => 'hero', 'data' => ['title' => 'Custom homepage hero', 'style' => 'dark']],
        ])->create(['slug' => 'welcome']);
        Settings::setMany(['homepage_page_id' => $page->id]);

        $this->get('/')->assertOk()->assertSee('Custom homepage hero');
        $this->get('/welcome')->assertRedirect('/')->assertStatus(301);

        // Unpublishing falls back to the built-in homepage.
        $page->update(['status' => 'draft']);
        $this->get('/')->assertOk()->assertDontSee('Custom homepage hero')->assertSee(Settings::get('home_hero_title'));
    }

    public function test_seo_settings_page_only_accepts_published_pages_as_homepage(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));
        $draft = Page::factory()->create();

        Livewire::test(ManageSeo::class)
            ->fillForm(['homepage_page_id' => $draft->id])
            ->call('save')
            ->assertHasFormErrors(['homepage_page_id']);
        $this->assertNull(Settings::get('homepage_page_id'));

        Livewire::test(ManageSeo::class)
            ->fillForm(['seo_google_verification' => 'abc123', 'seo_organization_schema' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('abc123', Settings::get('seo_google_verification'));
        $this->assertFalse(Settings::get('seo_organization_schema'), 'Boolean false must persist, not revert to default true');
    }

    public function test_seo_settings_require_publishing_rights(): void
    {
        $this->actingAs($this->author())->get('/admin/settings/seo')->assertForbidden();
    }

    public function test_robots_txt_and_indexing_controls(): void
    {
        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')->assertSee('Sitemap: '.route('sitemap'));

        Settings::setMany(['seo_discourage_indexing' => true]);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
        $this->get('/')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_homepage_publishes_organisation_schema_and_verification_tags(): void
    {
        Settings::setMany(['seo_google_verification' => 'g-code-1', 'contact_phone' => '+256 700 000000']);

        $this->get('/')->assertSee('"@type":"LegalService"', false)
            ->assertSee('<meta name="google-site-verification" content="g-code-1">', false);
        $this->get('/about')->assertDontSee('"@type":"LegalService"', false);
    }

    public function test_sitemap_includes_only_indexable_published_pages(): void
    {
        $public = Page::factory()->published()->create(['slug' => 'in-map']);
        Page::factory()->create(['slug' => 'draft-page']);
        Page::factory()->published()->create(['slug' => 'hidden-page', 'noindex' => true]);

        $this->get('/sitemap.xml')->assertSee(url('in-map'))->assertDontSee(url('draft-page'))->assertDontSee(url('hidden-page'));
    }

    public function test_slug_of_a_live_page_is_locked_for_non_publishers(): void
    {
        $page = Page::factory()->create(['slug' => 'locked']);
        app(PageWorkflow::class)->publish($page, $this->userWithRole(Access::CONTENT_EDITOR));

        $this->actingAs($this->author());
        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->assertFormFieldIsDisabled('slug');
    }
}
