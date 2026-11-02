<?php

namespace App\Filament\Pages;

use App\Models\Page as CmsPage;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Site-wide SEO defaults and the choice of homepage. */
class ManageSeo extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'SEO & homepage';

    protected static ?string $title = 'SEO & homepage';

    protected static ?string $slug = 'settings/seo';

    private const KEYS = [
        'homepage_page_id', 'seo_default_description', 'seo_default_image', 'seo_discourage_indexing',
        'seo_google_verification', 'seo_bing_verification', 'seo_organization_schema',
    ];

    public ?array $data = [];

    /** Choosing the homepage changes the whole public site: publishers only. */
    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('content.publish');
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Settings::get($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Homepage')->columns(1)->schema([
                Select::make('homepage_page_id')->label('Homepage')
                    ->placeholder('Built-in homepage')
                    ->options(fn () => CmsPage::query()->published()->orderBy('title')->pluck('title', 'id'))
                    ->helperText('Choose a published page built with the page builder, or keep the built-in homepage (which uses Website content settings).'),
            ]),
            Section::make('Search engines')->columns(2)->schema([
                Textarea::make('seo_default_description')->label('Default meta description')->rows(2)->maxLength(170)->columnSpanFull()
                    ->helperText('Used when a page has no description of its own.'),
                FileUpload::make('seo_default_image')->label('Default social sharing image')
                    ->image()->disk('public')->directory('branding')->visibility('public')->maxSize(2048)
                    ->helperText('Recommended 1200×630 pixels.'),
                Section::make()->compact()->schema([
                    Toggle::make('seo_organization_schema')->label('Publish firm details as structured data (schema.org)'),
                    Toggle::make('seo_discourage_indexing')->label('Discourage search engines from indexing this site')
                        ->helperText('Use only for staging or before launch. Adds noindex and blocks crawlers in robots.txt.'),
                ]),
                TextInput::make('seo_google_verification')->label('Google Search Console verification code')->maxLength(100)->alphaDash(),
                TextInput::make('seo_bing_verification')->label('Bing Webmaster verification code')->maxLength(100)->alphaDash(),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save settings')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        // Only a currently published page may become the homepage.
        if (filled($data['homepage_page_id']) && ! CmsPage::query()->published()->whereKey($data['homepage_page_id'])->exists()) {
            $data['homepage_page_id'] = null;
        }

        Settings::setMany(array_intersect_key($data, array_flip(self::KEYS)));

        Notification::make()->title('Settings saved')->success()->send();
    }
}
