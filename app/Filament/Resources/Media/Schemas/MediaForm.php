<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Filament\Resources\Media\MediaResource;
use App\Models\Media;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('File')
                ->columnSpanFull()
                ->schema([
                    self::uploadField()->hiddenOn('edit'),
                    Html::make(fn (?Media $record) => $record ? new HtmlString(
                        $record->isImage()
                            ? '<img src="'.e($record->url()).'" alt="" style="max-height:16rem;border-radius:.5rem">'
                            : '<p><a href="'.e($record->url()).'" target="_blank" rel="noopener" style="text-decoration:underline">'.e($record->original_name).'</a></p>'
                    ) : null)->visibleOn('edit'),
                ]),
            Section::make('Details')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('title')->maxLength(255),
                    Select::make('collection')->options(MediaResource::COLLECTIONS)->default('default')->required(),
                    self::altTextField()->columnSpanFull(),
                    TextInput::make('caption')->maxLength(255)->columnSpanFull(),
                ]),
        ]);
    }

    public static function uploadField(string $name = 'path'): FileUpload
    {
        return FileUpload::make($name)
            ->label('File')
            ->disk(MediaResource::DISK)
            ->directory(fn () => MediaResource::uploadDirectory())
            ->visibility('public')
            ->storeFileNamesIn('original_name')
            ->acceptedFileTypes([...MediaResource::IMAGE_TYPES, ...MediaResource::DOCUMENT_TYPES])
            ->maxSize(10240)
            ->required()
            ->live()
            ->helperText('Images (JPG, PNG, WebP, SVG, GIF) or documents (PDF, DOCX), up to 10 MB. Files here are PUBLIC: never upload client or case documents.');
    }

    /** Alt text is required for images (WCAG 1.1.1) but not for documents. */
    public static function altTextField(string $uploadField = 'path'): TextInput
    {
        return TextInput::make('alt_text')
            ->label('Alternative text')
            ->maxLength(255)
            ->required(fn (Get $get, ?Media $record) => $record ? $record->isImage() : MediaResource::uploadIsImage($get($uploadField)))
            ->helperText('Describe the image for people using screen readers. Leave blank only for documents.');
    }
}
