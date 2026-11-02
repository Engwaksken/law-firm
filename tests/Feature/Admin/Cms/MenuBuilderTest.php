<?php

namespace Tests\Feature\Admin\Cms;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedAccess();
        $this->withoutMfaEnforcement();
        $this->withoutVite();
    }

    public function test_menu_builder_saves_nested_items(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(CreateMenu::class)
            ->fillForm([
                'name' => 'Main navigation',
                'location' => 'header',
                'items' => [
                    ['label' => 'Firm', 'link_type' => 'route', 'route_name' => 'about', 'is_active' => true, 'open_in_new_tab' => false, 'children' => [
                        ['label' => 'Our team', 'link_type' => 'route', 'route_name' => 'advocates.index', 'is_active' => true, 'open_in_new_tab' => false],
                        ['label' => 'Law Society', 'link_type' => 'url', 'url' => 'https://uls.or.ug', 'is_active' => true, 'open_in_new_tab' => true],
                    ]],
                    ['label' => 'Contact', 'link_type' => 'route', 'route_name' => 'contact', 'is_active' => true, 'open_in_new_tab' => false, 'children' => []],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $menu = Menu::sole();
        $top = MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->orderBy('sort_order')->get();
        $this->assertSame(['Firm', 'Contact'], $top->pluck('label')->all());
        $this->assertSame(['Our team', 'Law Society'], $top[0]->children->pluck('label')->all());

        $this->get('/')->assertOk()
            ->assertSee('Our team')
            ->assertSee('href="https://uls.or.ug" target="_blank" rel="noopener noreferrer"', false)
            ->assertDontSee('Book a consultation</a></li>'."\n".'            <li', false);
    }

    public function test_edit_hydrates_and_replaces_items(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));
        $menu = Menu::create(['name' => 'Main', 'location' => 'header']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Old item', 'link_type' => 'route', 'route_name' => 'about', 'sort_order' => 0]);

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertFormSet(fn (array $state) => $this->assertSame('Old item', array_values($state['items'])[0]['label']) ?? [])
            ->fillForm(['items' => [['label' => 'New item', 'link_type' => 'route', 'route_name' => 'contact', 'is_active' => true, 'open_in_new_tab' => false, 'children' => []]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['New item'], MenuItem::where('menu_id', $menu->id)->pluck('label')->all());
    }

    public function test_unsafe_urls_are_rejected_by_the_form(): void
    {
        $this->actingAs($this->userWithRole(Access::CONTENT_EDITOR));

        Livewire::test(CreateMenu::class)
            ->fillForm([
                'name' => 'Main', 'location' => 'header',
                'items' => [['label' => 'Evil', 'link_type' => 'url', 'url' => 'javascript:alert(1)', 'is_active' => true, 'open_in_new_tab' => false, 'children' => []]],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, Menu::count());
    }

    public function test_items_pointing_at_drafts_or_unsafe_urls_are_not_rendered(): void
    {
        $menu = Menu::create(['name' => 'Main', 'location' => 'header']);
        $draft = Page::factory()->create(['title' => 'Draft page', 'slug' => 'draft-page']);
        $published = Page::factory()->published()->create(['title' => 'Fees', 'slug' => 'fees']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Unpublished link', 'link_type' => 'page', 'page_id' => $draft->id, 'sort_order' => 0]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Injected link', 'link_type' => 'url', 'url' => 'javascript:alert(1)', 'sort_order' => 1]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Our fees', 'link_type' => 'page', 'page_id' => $published->id, 'sort_order' => 2]);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Hidden item', 'link_type' => 'route', 'route_name' => 'about', 'is_active' => false, 'sort_order' => 3]);

        $this->get('/')->assertOk()
            ->assertSee('Our fees')->assertSee(url('fees'))
            ->assertDontSee('Unpublished link')
            ->assertDontSee('Injected link')
            ->assertDontSee('javascript:alert', false)
            ->assertDontSee('Hidden item');
    }

    public function test_default_navigation_is_used_without_a_header_menu(): void
    {
        $this->get('/')->assertOk()->assertSee(route('practice-areas.index'))->assertSee('Our advocates');
    }

    public function test_footer_menu_overrides_the_firm_links(): void
    {
        $menu = Menu::create(['name' => 'Footer', 'location' => 'footer']);
        MenuItem::create(['menu_id' => $menu->id, 'label' => 'Pro bono work', 'link_type' => 'url', 'url' => '/pro-bono', 'sort_order' => 0]);

        $this->get('/')->assertOk()
            ->assertSee('Pro bono work')
            ->assertSee('href="'.e(MenuItem::first()->href()).'"', false)
            ->assertDontSee('>About us</a>', false);
    }
}
