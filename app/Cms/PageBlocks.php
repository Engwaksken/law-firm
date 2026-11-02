<?php

namespace App\Cms;

use App\Models\Article;
use App\Models\Media;
use App\Support\SafeUrl;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;

/**
 * Catalogue of page-builder blocks. Each block has a Filament form here and a
 * Blade partial at resources/views/cms/blocks/{type}.blade.php. Adding a block
 * means adding both; unknown types are skipped when rendering.
 */
class PageBlocks
{
    public const TYPES = [
        'hero' => 'Hero banner',
        'rich_text' => 'Text',
        'text_image' => 'Text with image',
        'image' => 'Image',
        'cards' => 'Cards / features',
        'stats' => 'Statistics',
        'faq' => 'FAQs',
        'quote' => 'Quote',
        'cta' => 'Call to action',
        'practice_areas' => 'Practice areas (live)',
        'advocates' => 'Advocates (live)',
        'latest_insights' => 'Latest insights (live)',
        'upcoming_events' => 'Upcoming events (live)',
        'testimonials' => 'Testimonials (live)',
        'form' => 'Form',
    ];

    /** @return list<Block> */
    public static function blocks(): array
    {
        return [
            static::block('hero', Heroicon::OutlinedSparkles, [
                TextInput::make('eyebrow')->maxLength(80),
                TextInput::make('title')->required()->maxLength(160),
                Textarea::make('subtitle')->rows(2)->maxLength(400),
                static::mediaSelect('image_id', 'Background image'),
                Grid::make(2)->schema([
                    ...static::link('primary', 'Primary button'),
                    ...static::link('secondary', 'Secondary button'),
                ]),
                Select::make('style')->options(['dark' => 'Dark (brand colour)', 'light' => 'Light'])->default('dark')->required(),
            ]),
            static::block('rich_text', Heroicon::OutlinedDocumentText, [
                TextInput::make('heading')->maxLength(160),
                RichEditor::make('body')->required(),
                Select::make('width')->options(['narrow' => 'Narrow (reading width)', 'wide' => 'Wide'])->default('narrow'),
            ]),
            static::block('text_image', Heroicon::OutlinedPhoto, [
                TextInput::make('heading')->maxLength(160),
                RichEditor::make('body')->required(),
                static::mediaSelect('image_id', 'Image')->required(),
                Select::make('image_position')->options(['right' => 'Image on the right', 'left' => 'Image on the left'])->default('right'),
                Grid::make(2)->schema(static::link('button', 'Button')),
            ]),
            static::block('image', Heroicon::OutlinedPhoto, [
                static::mediaSelect('image_id', 'Image')->required(),
                TextInput::make('caption')->maxLength(200),
                Select::make('size')->options(['narrow' => 'Reading width', 'wide' => 'Wide', 'full' => 'Full width'])->default('wide'),
            ]),
            static::block('cards', Heroicon::OutlinedSquares2x2, [
                TextInput::make('heading')->maxLength(160),
                Textarea::make('intro')->rows(2),
                Select::make('columns')->options([2 => '2', 3 => '3', 4 => '4'])->default(3),
                Repeater::make('items')->schema([
                    TextInput::make('title')->required()->maxLength(120),
                    Textarea::make('text')->rows(2)->maxLength(400),
                    TextInput::make('url')->label('Link (optional)')->rule(SafeUrl::rule()),
                ])->minItems(1)->collapsible()->itemLabel(fn (array $state) => $state['title'] ?? null),
            ]),
            static::block('stats', Heroicon::OutlinedChartBar, [
                TextInput::make('heading')->maxLength(160),
                Repeater::make('items')->schema([
                    TextInput::make('value')->numeric()->required(),
                    TextInput::make('suffix')->maxLength(5)->placeholder('+, %'),
                    TextInput::make('label')->required()->maxLength(80),
                ])->columns(3)->minItems(1)->maxItems(4)
                    ->helperText('Only publish figures the firm can substantiate.'),
            ]),
            static::block('faq', Heroicon::OutlinedQuestionMarkCircle, [
                TextInput::make('heading')->default('Frequently asked questions')->maxLength(160),
                Repeater::make('items')->schema([
                    TextInput::make('question')->required()->maxLength(255),
                    Textarea::make('answer')->required()->rows(3),
                ])->minItems(1)->collapsible()->itemLabel(fn (array $state) => $state['question'] ?? null),
            ]),
            static::block('quote', Heroicon::OutlinedChatBubbleBottomCenterText, [
                Textarea::make('quote')->required()->rows(3)->maxLength(600),
                TextInput::make('attribution')->maxLength(120),
                TextInput::make('role')->maxLength(120),
            ]),
            static::block('cta', Heroicon::OutlinedMegaphone, [
                TextInput::make('title')->required()->maxLength(160),
                Textarea::make('text')->rows(2)->maxLength(400),
                Grid::make(2)->schema(static::link('button', 'Button')),
                Select::make('style')->options(['secondary' => 'Burgundy', 'primary' => 'Navy', 'light' => 'Light'])->default('secondary'),
            ]),
            static::block('practice_areas', Heroicon::OutlinedScale, [
                TextInput::make('heading')->default('Practice areas')->maxLength(160),
                Textarea::make('intro')->rows(2),
                Toggle::make('featured_only')->label('Featured areas only')->default(true),
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(24)->default(6),
            ]),
            static::block('advocates', Heroicon::OutlinedUserGroup, [
                TextInput::make('heading')->default('Our advocates')->maxLength(160),
                Toggle::make('featured_only')->label('Featured advocates only'),
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(24)->default(4),
            ]),
            static::block('latest_insights', Heroicon::OutlinedNewspaper, [
                TextInput::make('heading')->default('Legal insights')->maxLength(160),
                Select::make('type')->options(Article::TYPES)->placeholder('All types'),
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(12)->default(3),
            ]),
            static::block('upcoming_events', Heroicon::OutlinedCalendarDays, [
                TextInput::make('heading')->default('Upcoming events')->maxLength(160),
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(12)->default(3),
            ]),
            static::block('testimonials', Heroicon::OutlinedChatBubbleLeftRight, [
                TextInput::make('heading')->default('What our clients say')->maxLength(160),
                TextInput::make('limit')->numeric()->minValue(1)->maxValue(6)->default(3),
            ]),
            static::block('form', Heroicon::OutlinedEnvelope, [
                TextInput::make('heading')->maxLength(160),
                Textarea::make('intro')->rows(2),
                Select::make('form')->options(['contact' => 'Contact form', 'consultation' => 'Consultation request', 'newsletter' => 'Newsletter sign-up'])->required(),
            ]),
        ];
    }

    /** Every block gets an on/off switch and an optional anchor for in-page links. */
    private static function block(string $type, Heroicon $icon, array $schema): Block
    {
        return Block::make($type)
            ->label(self::TYPES[$type])
            ->icon($icon)
            ->schema([
                ...$schema,
                Section::make('Block options')->collapsed()->compact()->columns(2)->schema([
                    Toggle::make('is_active')->label('Visible')->default(true),
                    TextInput::make('anchor')->label('Anchor ID')->alphaDash()->maxLength(40)
                        ->helperText('Link to this block with /page#anchor-id'),
                ]),
            ]);
    }

    /** @return list<TextInput> */
    private static function link(string $prefix, string $label): array
    {
        return [
            TextInput::make("{$prefix}_label")->label("{$label} label")->maxLength(40),
            TextInput::make("{$prefix}_url")->label("{$label} link")->rule(SafeUrl::rule())
                ->requiredWith("{$prefix}_label"),
        ];
    }

    private static function mediaSelect(string $name, string $label): Select
    {
        if (class_exists(\App\Filament\Forms\MediaPicker::class)) {
            return \App\Filament\Forms\MediaPicker::make($name, $label);
        }

        return Select::make($name)->label($label)->searchable()
            ->options(fn () => Media::query()->where('mime_type', 'like', 'image/%')->latest()->limit(200)->get()
                ->mapWithKeys(fn (Media $m) => [$m->id => $m->label()]));
    }
}
