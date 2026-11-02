<?php

namespace App\Filament\Resources\PracticeAreas\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PracticeAreaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Practice area')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)->unique(ignoreRecord: true)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true)
                        ->helperText('Used in the page URL.'),
                    TextInput::make('summary')->maxLength(300)->columnSpanFull()
                        ->helperText('One or two sentences shown on cards.'),
                    Textarea::make('description')->rows(3)->columnSpanFull()->label('Introduction'),
                    RichEditor::make('body')->columnSpanFull()->label('Full description'),
                    FileUpload::make('image_path')->label('Image')->image()->disk('public')->directory('practice-areas')
                        ->visibility('public')->maxSize(4096),
                    TextInput::make('icon')->maxLength(100)->helperText('Optional Heroicon name, e.g. heroicon-o-scale.'),
                    Select::make('advocates')->relationship('advocates', 'name')->multiple()->preload()->searchable()
                        ->columnSpanFull()->helperText('Only verified, public advocate profiles appear on the website.'),
                    Toggle::make('is_active')->label('Active (visible on website)')->default(true),
                    Toggle::make('is_featured')->label('Feature on homepage'),
                    TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                ]),
            Section::make('Frequently asked questions')
                ->columnSpanFull()
                ->collapsible()
                ->schema([
                    Repeater::make('faqs')->hiddenLabel()
                        ->schema([
                            TextInput::make('question')->required()->maxLength(255),
                            Textarea::make('answer')->required()->rows(3),
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['question'] ?? null)
                        ->defaultItems(0)
                        ->addActionLabel('Add question'),
                ]),
            Section::make('SEO')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed()
                ->schema([
                    TextInput::make('meta_title')->maxLength(255),
                    TextInput::make('meta_description')->maxLength(300),
                ]),
        ]);
    }
}
