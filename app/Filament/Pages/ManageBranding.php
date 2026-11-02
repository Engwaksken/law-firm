<?php

namespace App\Filament\Pages;

use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
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
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** Firm identity, colours and public contact details, stored in site_settings. */
class ManageBranding extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Branding & contact';

    protected static ?string $title = 'Branding & contact';

    protected static ?string $slug = 'settings/branding';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('settings.manage');
    }

    public function mount(): void
    {
        $this->form->fill(collect(Settings::DEFINITIONS)->keys()->mapWithKeys(fn ($key) => [$key => Settings::get($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $hex = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $fonts = array_combine($f = ['Poppins', 'Outfit', 'Inter', 'Lato', 'Merriweather', 'Playfair Display', 'Source Sans 3'], $f);

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings')->columnSpanFull()->tabs([
                    Tab::make('Identity')->icon(Heroicon::OutlinedIdentification)->columns(2)->schema([
                        TextInput::make('firm_name')->required()->maxLength(120),
                        TextInput::make('firm_tagline')->maxLength(160),
                        FileUpload::make('logo_path')->label('Logo (light backgrounds)')
                            ->image()->disk('public')->directory('branding')->visibility('public')->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp', 'image/jpeg']),
                        FileUpload::make('logo_dark_path')->label('Logo (dark backgrounds)')
                            ->image()->disk('public')->directory('branding')->visibility('public')->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp', 'image/jpeg']),
                        FileUpload::make('favicon_path')->label('Favicon')
                            ->image()->disk('public')->directory('branding')->visibility('public')->maxSize(512)
                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml']),
                    ]),
                    Tab::make('Colours & fonts')->icon(Heroicon::OutlinedSwatch)->columns(3)->schema([
                        ColorPicker::make('color_primary')->label('Primary')->rules($hex),
                        ColorPicker::make('color_secondary')->label('Secondary')->rules($hex),
                        ColorPicker::make('color_accent')->label('Accent')->rules($hex),
                        ColorPicker::make('color_background')->label('Background')->rules($hex),
                        ColorPicker::make('color_text')->label('Text')->rules($hex),
                        Select::make('font_heading')->label('Heading font')->options($fonts)->required(),
                        Select::make('font_body')->label('Body font')->options($fonts)->required(),
                    ]),
                    Tab::make('Contact & footer')->icon(Heroicon::OutlinedEnvelope)->columns(2)->schema([
                        TextInput::make('contact_email')->email()->maxLength(255),
                        TextInput::make('contact_phone')->tel()->maxLength(50),
                        Textarea::make('contact_address')->rows(3),
                        TextInput::make('office_hours')->maxLength(160),
                        Textarea::make('footer_text')->rows(3)->columnSpanFull(),
                        Textarea::make('confidentiality_notice')->rows(3)->columnSpanFull()
                            ->helperText('Shown on contact and consultation forms.'),
                        KeyValue::make('social_links')->keyLabel('Network')->valueLabel('URL')->columnSpanFull(),
                    ]),
                    Tab::make('Regional')->icon(Heroicon::OutlinedGlobeAlt)->columns(2)->schema([
                        Select::make('default_currency')->options(['UGX' => 'UGX – Ugandan Shilling', 'USD' => 'USD – US Dollar', 'KES' => 'KES – Kenyan Shilling', 'EUR' => 'EUR – Euro', 'GBP' => 'GBP – Pound Sterling'])->required(),
                        Select::make('timezone')->options(fn () => array_combine($z = timezone_identifiers_list(), $z))->searchable()->required(),
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
                        Action::make('save')->label('Save settings')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();

        // Only http(s) URLs: blocks javascript: and data: links on the public site.
        $badLinks = collect($data['social_links'] ?? [])
            ->reject(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $url));
        if ($badLinks->isNotEmpty()) {
            throw ValidationException::withMessages([
                'data.social_links' => 'Invalid URL for: '.$badLinks->keys()->implode(', ').'. Links must start with http:// or https://.',
            ]);
        }

        Settings::setMany(array_intersect_key($data, Settings::DEFINITIONS));

        Notification::make()->title('Settings saved')->success()->send();
    }
}
