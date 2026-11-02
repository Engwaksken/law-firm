<?php

namespace Tests\Feature\Website;

use App\Models\Advocate;
use App\Models\Article;
use App\Models\ArticleCategory;
use App\Models\Career;
use App\Models\PracticeArea;
use App\Models\Testimonial;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array<string, array{string}> */
    public static function staticPages(): array
    {
        return [
            'home' => ['/'], 'about' => ['/about'], 'practice areas' => ['/practice-areas'],
            'advocates' => ['/advocates'], 'insights' => ['/insights'], 'careers' => ['/careers'],
            'contact' => ['/contact'], 'consultation' => ['/book-a-consultation'], 'privacy' => ['/privacy'],
        ];
    }

    #[DataProvider('staticPages')]
    public function test_pages_render_on_an_empty_site(string $url): void
    {
        $this->get($url)->assertOk()->assertSee(Settings::get('firm_name'));
    }

    public function test_homepage_shows_published_content_and_hides_the_rest(): void
    {
        $area = PracticeArea::factory()->featured()->create(['name' => 'Visible Area Law']);
        PracticeArea::factory()->inactive()->create(['name' => 'Hidden Area Law']);
        Advocate::factory()->published()->create(['name' => 'Published Advocate']);
        Advocate::factory()->create(['name' => 'Unverified Advocate', 'is_public' => true]);
        Article::factory()->published()->create(['title' => 'Published Insight']);
        Article::factory()->create(['title' => 'Draft Insight']);
        Article::factory()->scheduled()->create(['title' => 'Scheduled Insight']);
        Testimonial::factory()->publishable()->create(['quote' => 'Approved testimonial quote']);
        Testimonial::factory()->create(['quote' => 'Unapproved testimonial quote', 'consent_obtained' => true]);

        $this->get('/')->assertOk()
            ->assertSee('Visible Area Law')->assertDontSee('Hidden Area Law')
            ->assertSee('Published Advocate')->assertDontSee('Unverified Advocate')
            ->assertSee('Published Insight')->assertDontSee('Draft Insight')->assertDontSee('Scheduled Insight')
            ->assertSee('Approved testimonial quote')->assertDontSee('Unapproved testimonial quote');
    }

    public function test_statistics_section_is_hidden_until_the_firm_enters_figures(): void
    {
        $this->get('/')->assertDontSee('The firm in numbers');

        Settings::setMany(['home_stats' => [['value' => 250, 'suffix' => '+', 'label' => 'Matters concluded']]]);

        $this->get('/')->assertSee('The firm in numbers')->assertSee('Matters concluded');
    }

    public function test_practice_area_page_shows_faqs_and_only_published_advocates(): void
    {
        $area = PracticeArea::factory()->create(['faqs' => [['question' => 'Is the first meeting free?', 'answer' => 'Please ask us.']]]);
        $area->advocates()->attach([
            Advocate::factory()->published()->create(['name' => 'Listed Advocate'])->id,
            Advocate::factory()->create(['name' => 'Unlisted Advocate'])->id,
        ]);

        $this->get(route('practice-areas.show', $area))->assertOk()
            ->assertSee('Is the first meeting free?')
            ->assertSee('Listed Advocate')->assertDontSee('Unlisted Advocate')
            ->assertSee('FAQPage', false);
    }

    public function test_inactive_practice_area_returns_404(): void
    {
        $this->get(route('practice-areas.show', PracticeArea::factory()->inactive()->create()))->assertNotFound();
    }

    public function test_advocate_profile_requires_publication_and_verification(): void
    {
        $verified = Advocate::factory()->published()->create();
        $notPublic = Advocate::factory()->create(['credentials_verified_at' => now()]);
        $notVerified = Advocate::factory()->create(['is_public' => true]);

        $this->get(route('advocates.show', $verified))->assertOk()->assertSee($verified->name);
        $this->get(route('advocates.show', $notPublic))->assertNotFound();
        $this->get(route('advocates.show', $notVerified))->assertNotFound();
    }

    public function test_advocate_contact_details_appear_only_when_opted_in(): void
    {
        $private = Advocate::factory()->published()->create(['email' => 'private@example.test', 'show_email' => false]);
        $open = Advocate::factory()->published()->create(['email' => 'open@example.test', 'show_email' => true]);

        $this->get(route('advocates.show', $private))->assertDontSee('private@example.test');
        $this->get(route('advocates.show', $open))->assertSee('open@example.test');
    }

    public function test_advocate_directory_filters_by_practice_area_and_name(): void
    {
        $area = PracticeArea::factory()->create();
        $inArea = Advocate::factory()->published()->create(['name' => 'Alpha Advocate']);
        $inArea->practiceAreas()->attach($area);
        Advocate::factory()->published()->create(['name' => 'Beta Advocate']);

        $this->get('/advocates?practice_area='.$area->slug)->assertSee('Alpha Advocate')->assertDontSee('Beta Advocate');
        $this->get('/advocates?q=Beta')->assertSee('Beta Advocate')->assertDontSee('Alpha Advocate');
    }

    public function test_unpublished_and_scheduled_articles_return_404(): void
    {
        $this->get(route('insights.show', Article::factory()->create()))->assertNotFound();
        $this->get(route('insights.show', Article::factory()->scheduled()->create()))->assertNotFound();
        $this->get(route('insights.show', Article::factory()->published()->create(['title' => 'Readable'])))->assertOk()->assertSee('Readable');
    }

    public function test_insights_filter_by_category_type_and_search(): void
    {
        $category = ArticleCategory::factory()->create(['name' => 'Tax Updates', 'slug' => 'tax-updates']);
        $taxArticle = Article::factory()->published()->create(['title' => 'New VAT rules', 'type' => 'legal-update']);
        $taxArticle->categories()->attach($category);
        Article::factory()->published()->create(['title' => 'Firm anniversary', 'type' => 'announcement']);

        $this->get(route('insights.category', $category))->assertSee('New VAT rules')->assertDontSee('Firm anniversary');
        $this->get('/insights?type=announcement')->assertSee('Firm anniversary')->assertDontSee('New VAT rules');
        $this->get('/insights?q=VAT')->assertSee('New VAT rules')->assertDontSee('Firm anniversary');
    }

    public function test_rich_text_is_sanitised_before_rendering(): void
    {
        $article = Article::factory()->published()->create([
            'body' => '<p>Safe paragraph</p><script>alert("xss")</script><img src=x onerror="alert(1)">',
        ]);

        $this->get(route('insights.show', $article))->assertOk()
            ->assertSee('Safe paragraph')
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertDontSee('onerror', false);
    }

    public function test_careers_page_lists_only_open_vacancies(): void
    {
        Career::factory()->open()->create(['title' => 'Open Role']);
        Career::factory()->closed()->create(['title' => 'Closed Role']);
        Career::factory()->create(['title' => 'Draft Role']);

        $this->get('/careers')->assertSee('Open Role')->assertDontSee('Closed Role')->assertDontSee('Draft Role');
    }

    public function test_closed_vacancy_page_shows_no_application_form_and_drafts_404(): void
    {
        $closed = Career::factory()->closed()->create();

        $this->get(route('careers.show', $closed))->assertOk()->assertSee('Applications closed');
        $this->get(route('careers.show', Career::factory()->create()))->assertNotFound();
    }

    public function test_sitemap_lists_only_public_content(): void
    {
        $area = PracticeArea::factory()->create();
        $hiddenArea = PracticeArea::factory()->inactive()->create();
        $article = Article::factory()->published()->create();
        $draft = Article::factory()->create();
        $advocate = Advocate::factory()->published()->create();
        $hiddenAdvocate = Advocate::factory()->create();

        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('practice-areas.show', $area))->assertDontSee(route('practice-areas.show', $hiddenArea))
            ->assertSee(route('insights.show', $article))->assertDontSee(route('insights.show', $draft))
            ->assertSee(route('advocates.show', $advocate))->assertDontSee(route('advocates.show', $hiddenAdvocate));
    }

    public function test_brand_colours_from_settings_are_applied_and_unsafe_values_ignored(): void
    {
        Settings::setMany(['color_primary' => '#112233']);
        $this->get('/')->assertSee('--brand-primary: #112233', false);

        // A value that bypassed form validation must not be injected into CSS.
        Settings::setMany(['color_primary' => 'red;}</style><script>x</script>']);
        $this->get('/')->assertSee('--brand-primary: #14283F', false)->assertDontSee('<script>x</script>', false);
    }

    public function test_practice_area_seeder_creates_spec_areas_once(): void
    {
        $this->seed(\Database\Seeders\PracticeAreaSeeder::class);
        $this->seed(\Database\Seeders\PracticeAreaSeeder::class);

        $this->assertSame(13, PracticeArea::count());
        $this->get('/practice-areas')->assertSee('Technology and Data Protection Law');
    }
}
