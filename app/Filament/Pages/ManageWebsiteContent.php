<?php

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Homepage, About and Privacy copy, stored in site_settings and rendered by the public site. */
class ManageWebsiteContent extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Website content';

    protected static ?string $title = 'Website content';

    protected static ?string $slug = 'settings/website-content';

    public const KEYS = [
        'home_hero_title', 'home_hero_subtitle', 'home_hero_image', 'home_intro_title', 'home_intro_body',
        'home_why_title', 'home_why_points', 'home_stats',
        'about_history', 'about_mission', 'about_vision', 'about_values', 'about_affiliations',
        'about_milestones', 'about_governance', 'privacy_notice',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('content.manage');
    }

    public function mount(): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => Settings::get($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('content')->columnSpanFull()->tabs([
                    Tab::make('Homepage')->icon(Heroicon::OutlinedHome)->schema([
                        Section::make('Hero')->columns(2)->schema([
                            TextInput::make('home_hero_title')->label('Headline')->required()->maxLength(160),
                            FileUpload::make('home_hero_image')->label('Background image')->image()
                                ->disk('public')->directory('branding')->visibility('public')->maxSize(4096),
                            Textarea::make('home_hero_subtitle')->label('Subheading')->rows(3)->maxLength(400)->columnSpanFull(),
                        ]),
                        Section::make('Introduction')->schema([
                            TextInput::make('home_intro_title')->maxLength(160),
                            Textarea::make('home_intro_body')->rows(4),
                        ]),
                        Section::make('Why choose the firm')->schema([
                            TextInput::make('home_why_title')->maxLength(160),
                            Repeater::make('home_why_points')->label('Points')
                                ->schema([
                                    TextInput::make('title')->required()->maxLength(100),
                                    Textarea::make('text')->required()->rows(2)->maxLength(300),
                                ])
                                ->columns(2)->collapsible()->defaultItems(0)->maxItems(8)
                                ->itemLabel(fn (array $state) => $state['title'] ?? null),
                        ]),
                        Section::make('Impact statistics')
                            ->description('Only enter figures the firm can substantiate. The section is hidden when empty.')
                            ->schema([
                                Repeater::make('home_stats')->hiddenLabel()
                                    ->schema([
                                        TextInput::make('value')->required()->numeric()->minValue(0),
                                        TextInput::make('suffix')->maxLength(5)->placeholder('+ or %'),
                                        TextInput::make('label')->required()->maxLength(60),
                                    ])
                                    ->columns(3)->defaultItems(0)->maxItems(6)
                                    ->itemLabel(fn (array $state) => $state['label'] ?? null),
                            ]),
                    ]),
                    Tab::make('About')->icon(Heroicon::OutlinedBuildingLibrary)->schema([
                        RichEditor::make('about_history')->label('Firm history'),
                        Textarea::make('about_mission')->label('Mission')->rows(3),
                        Textarea::make('about_vision')->label('Vision')->rows(3),
                        Repeater::make('about_values')->label('Core values')
                            ->schema([
                                TextInput::make('title')->required()->maxLength(100),
                                Textarea::make('text')->rows(2)->maxLength(300),
                            ])
                            ->columns(2)->collapsible()->defaultItems(0)
                            ->itemLabel(fn (array $state) => $state['title'] ?? null),
                        Repeater::make('about_affiliations')->label('Professional affiliations')
                            ->schema([
                                TextInput::make('name')->required()->maxLength(160),
                                TextInput::make('url')->label('Website')->url()->maxLength(255)
                                    ->rule('regex:#^https?://#i'),
                            ])
                            ->columns(2)->defaultItems(0)
                            ->itemLabel(fn (array $state) => $state['name'] ?? null),
                        Repeater::make('about_milestones')->label('Milestones')
                            ->schema([
                                TextInput::make('year')->required()->maxLength(10),
                                TextInput::make('text')->required()->maxLength(255),
                            ])
                            ->columns(2)->defaultItems(0)->reorderable()
                            ->itemLabel(fn (array $state) => trim(($state['year'] ?? '').' '.($state['text'] ?? '')) ?: null),
                        RichEditor::make('about_governance')->label('Governance and ethics'),
                    ]),
                    Tab::make('Privacy')->icon(Heroicon::OutlinedLockClosed)->schema([
                        RichEditor::make('privacy_notice')
                            ->helperText('Shown at /privacy and linked from every form. It must be approved by the firm (and reviewed against the Data Protection and Privacy Act, 2019) before the site goes live.'),
                    ]),
                ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save content')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        foreach (['home_why_points', 'home_stats', 'about_values', 'about_affiliations', 'about_milestones'] as $list) {
            $data[$list] = array_values($data[$list] ?? []);
        }

        Settings::setMany(array_intersect_key($data, array_flip(self::KEYS)));

        Notification::make()->title('Website content saved')->success()->send();
    }
}
