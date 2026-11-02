<?php

namespace Tests\Feature\Admin\Website;

use App\Models\Advocate;
use App\Models\Article;
use App\Models\Career;
use App\Models\CareerApplication;
use App\Models\Consultation;
use App\Models\ContactInquiry;
use App\Models\NewsletterSubscriber;
use App\Models\PracticeArea;
use App\Models\Testimonial;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every Phase 2 admin page renders for a Super Administrator with data present. */
class AdminPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_website_admin_pages_render(): void
    {
        $this->seedAccess();
        $this->withoutMfaEnforcement();
        $this->actingAs($this->userWithRole(Access::SUPER_ADMIN));

        $area = PracticeArea::factory()->create();
        $advocate = Advocate::factory()->published()->create();
        $article = Article::factory()->published()->create();
        $career = Career::factory()->open()->create();
        $application = CareerApplication::factory()->create(['career_id' => $career->id]);
        $consultation = Consultation::factory()->create(['practice_area_id' => $area->id]);
        ContactInquiry::factory()->create();
        Testimonial::factory()->publishable()->create();
        NewsletterSubscriber::factory()->confirmed()->create();

        $urls = [
            '/admin',
            '/admin/practice-areas', '/admin/practice-areas/create', "/admin/practice-areas/{$area->slug}/edit",
            '/admin/advocates', '/admin/advocates/create', "/admin/advocates/{$advocate->slug}/edit",
            '/admin/articles', '/admin/articles/create', "/admin/articles/{$article->slug}/edit",
            '/admin/article-categories', '/admin/article-categories/create',
            '/admin/testimonials', '/admin/testimonials/create',
            '/admin/newsletter-subscribers',
            '/admin/contact-inquiries',
            '/admin/consultations', "/admin/consultations/{$consultation->id}/edit",
            '/admin/careers', '/admin/careers/create', "/admin/careers/{$career->slug}/edit",
            '/admin/career-applications', "/admin/career-applications/{$application->id}/edit",
            '/admin/settings/website-content',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }
}
