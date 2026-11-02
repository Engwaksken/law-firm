<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Group::make()->columnSpan(['lg' => 2])->schema([
                Section::make('Content')->schema([
                    TextInput::make('title')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                    Textarea::make('excerpt')->rows(3)->maxLength(500),
                    RichEditor::make('body')->required(),
                ]),
                Section::make('SEO')->columns(2)->collapsed()->schema([
                    TextInput::make('meta_title')->maxLength(255),
                    TextInput::make('meta_description')->maxLength(300),
                ]),
            ]),
            Group::make()->columnSpan(['lg' => 1])->schema([
                Section::make('Publishing')->schema([
                    Select::make('status')
                        ->options(['draft' => 'Draft', 'published' => 'Published'])
                        ->default('draft')->required()
                        ->helperText(fn () => auth()->user()->can('content.publish') ? null : 'You can save drafts only; an editor with publishing rights must publish.'),
                    DateTimePicker::make('published_at')->label('Publish at')->seconds(false)
                        ->helperText('Set a future date to schedule publication. Defaults to now when published.'),
                    Toggle::make('is_featured')->label('Feature on homepage'),
                ]),
                Section::make('Classification')->schema([
                    Select::make('type')->options(Article::TYPES)->default('article')->required(),
                    Select::make('categories')->relationship('categories', 'name')->multiple()->preload()
                        ->createOptionForm([
                            TextInput::make('name')->required()->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                            TextInput::make('slug')->required()->unique('article_categories', 'slug'),
                        ]),
                    TagsInput::make('tags'),
                    Select::make('author_id')->label('Author')->relationship('author', 'name')->searchable()->preload()
                        ->default(fn () => auth()->id()),
                ]),
                Section::make('Media')->schema([
                    FileUpload::make('featured_image')->image()->disk('public')->directory('articles')
                        ->visibility('public')->maxSize(4096),
                    FileUpload::make('attachment_path')->label('Downloadable document')
                        ->disk('public')->directory('publications')->visibility('public')
                        ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                        ->maxSize(10240)
                        ->helperText('PDF or DOCX, up to 10 MB. Publicly downloadable once published.'),
                ]),
            ]),
        ]);
    }
}
