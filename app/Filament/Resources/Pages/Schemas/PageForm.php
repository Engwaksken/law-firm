<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Cms\PageBlocks;
use App\Models\Media;
use App\Models\Page;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('Changes requested')
                ->description(fn (?Page $record) => $record?->review_notes)
                ->warning()
                ->visible(fn (?Page $record) => filled($record?->review_notes))
                ->columnSpanFull(),
            Callout::make('This page has unpublished changes')
                ->description('Visitors see the last published version until someone with publishing rights publishes these changes. Use Preview to check them.')
                ->info()
                ->visible(fn (?Page $record) => (bool) $record?->hasUnpublishedChanges())
                ->columnSpanFull(),

            Tabs::make('page')->columnSpanFull()->persistTabInQueryString()->tabs([
                Tab::make('Content')->icon(Heroicon::OutlinedPencilSquare)->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('title')->required()->maxLength(160)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set, ?string $state, string $operation) => $operation === 'create' && blank($get('slug'))
                                ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('slug')->label('URL')->required()->maxLength(120)
                            ->prefix(url('/').'/')
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/')
                            ->unique(ignoreRecord: true)
                            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                                if (Page::isReservedSlug((string) $value)) {
                                    $fail('This address is used by a built-in page. Choose another.');
                                }
                            })
                            // Changing a live URL breaks links: only publishers may.
                            ->disabled(fn (?Page $record) => $record?->liveRevision() !== null && ! auth()->user()->can('content.publish'))
                            ->helperText('Lowercase letters, numbers and hyphens.'),
                        Select::make('template')->options(Page::TEMPLATES)->default('default')->required(),
                        Textarea::make('excerpt')->label('Introduction')->rows(2)->maxLength(300)
                            ->helperText('Shown under the title (standard template) and in search results if no meta description is set.'),
                    ]),
                    Builder::make('content_blocks')
                        ->label('Page content')
                        ->blocks(PageBlocks::blocks())
                        ->blockPickerColumns(3)
                        ->collapsible()
                        ->cloneable()
                        ->reorderableWithButtons()
                        ->addActionLabel('Add block')
                        ->columnSpanFull(),
                ]),

                Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('meta_title')->maxLength(70)->helperText('Defaults to the page title. Aim for under 60 characters.'),
                        Select::make('og_image_id')->label('Social sharing image')
                            ->searchable()
                            ->options(fn () => Media::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get()->mapWithKeys(fn (Media $m) => [$m->id => $m->label()])),
                        Textarea::make('meta_description')->rows(2)->maxLength(170)->columnSpanFull()
                            ->helperText('Shown in search results. Aim for 120–160 characters.'),
                        Toggle::make('noindex')->label('Hide from search engines'),
                        Toggle::make('show_in_sitemap')->label('Include in sitemap')->default(true),
                    ]),
                ]),

                Tab::make('Publishing')->icon(Heroicon::OutlinedCalendar)->schema([
                    Section::make()->columns(2)->schema([
                        TextInput::make('status_display')->label('Status')->disabled()->dehydrated(false)
                            ->formatStateUsing(fn (?Page $record) => $record?->displayStatus() ?? 'Draft')
                            ->helperText('Change status with the actions at the top of the page.'),
                        DateTimePicker::make('published_at')->label('Publish date')->seconds(false)
                            ->disabled(fn () => ! auth()->user()->can('content.publish'))
                            ->helperText('Set a future date to schedule publication. Applied when the page is published.'),
                    ]),
                ]),
            ]),
        ]);
    }
}
