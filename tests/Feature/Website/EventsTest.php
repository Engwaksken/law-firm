<?php

namespace Tests\Feature\Website;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_listing_shows_upcoming_visible_events_and_a_past_section(): void
    {
        Event::factory()->published()->create(['title' => 'Upcoming seminar']);
        Event::factory()->published()->past()->create(['title' => 'Last year conference']);
        Event::factory()->create(['title' => 'Draft workshop']);
        Event::factory()->create(['title' => 'Scheduled webinar', 'status' => 'published', 'published_at' => now()->addDay()]);

        $this->get('/events')->assertOk()
            ->assertSeeInOrder(['Upcoming events', 'Upcoming seminar', 'Past events', 'Last year conference'])
            ->assertDontSee('Draft workshop')
            ->assertDontSee('Scheduled webinar');
    }

    public function test_empty_listing_renders(): void
    {
        $this->get('/events')->assertOk()->assertSee('There are no upcoming events');
    }

    public function test_draft_and_scheduled_events_return_404(): void
    {
        $this->get(route('events.show', Event::factory()->create()))->assertNotFound();
        $this->get(route('events.show', Event::factory()->create(['status' => 'published', 'published_at' => now()->addDay()])))->assertNotFound();
    }

    public function test_event_page_shows_details_registration_and_json_ld(): void
    {
        $event = Event::factory()->published()->create([
            'title' => 'Employment law update',
            'registration_url' => 'https://example.test/register',
            'description' => '<p>Agenda</p><script>alert(1)</script>',
        ]);

        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Employment law update')
            ->assertSee('https://example.test/register')
            ->assertSee('"@type":"Event"', false)
            ->assertSee('EventScheduled', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_cancelled_event_shows_notice_and_hides_registration(): void
    {
        $event = Event::factory()->published()->create([
            'status' => 'cancelled',
            'registration_url' => 'https://example.test/register',
        ]);

        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('This event has been cancelled')
            ->assertDontSee('https://example.test/register')
            ->assertSee('EventCancelled', false);
    }

    public function test_past_event_hides_registration(): void
    {
        $event = Event::factory()->published()->past()->create(['registration_url' => 'https://example.test/register']);

        $this->get(route('events.show', $event))->assertOk()
            ->assertSee('This event has taken place')
            ->assertDontSee('https://example.test/register');
    }

    public function test_events_link_appears_in_default_navigation_only_with_upcoming_events(): void
    {
        $this->get('/')->assertDontSee(route('events.index'));

        Event::factory()->published()->create();

        $this->get('/')->assertSee(route('events.index'));
    }

    public function test_sitemap_includes_only_visible_events(): void
    {
        $visible = Event::factory()->published()->create();
        $draft = Event::factory()->create();

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(route('events.show', $visible))
            ->assertDontSee(route('events.show', $draft));
    }
}
