<?php

namespace App\Filament\Resources\Advocates\Schemas;

use App\Models\Advocate;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AdvocateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make(fn (?Advocate $record) => $record?->isVerified()
                ? 'Credentials verified '.$record->credentials_verified_at->format('j M Y').($record->verifiedBy ? ' by '.$record->verifiedBy->name : '')
                : 'Credentials not verified')
                ->description('A profile appears on the public website only when it is marked "Show on website" AND its credentials have been verified. Changing the title, qualifications, memberships or experience clears the verification.')
                ->color(fn (?Advocate $record) => $record?->isVerified() ? 'success' : 'warning')
                ->columnSpanFull(),

            Section::make('Profile')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('name')->required()->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, ?string $state, Set $set) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(255)->alphaDash()->unique(ignoreRecord: true),
                    TextInput::make('title')->maxLength(255)->helperText('e.g. Partner, Senior Associate'),
                    Select::make('user_id')->label('Linked staff account')->relationship('user', 'name')->searchable()->preload()
                        ->unique(ignoreRecord: true),
                    Select::make('office_id')->label('Office')->relationship('office', 'name')->preload(),
                    Select::make('practiceAreas')->relationship('practiceAreas', 'name')->multiple()->preload(),
                    FileUpload::make('photo_path')->label('Photograph')->image()->imageEditor()->disk('public')
                        ->directory('advocates')->visibility('public')->maxSize(4096),
                    Textarea::make('bio')->rows(5)->label('Biography'),
                ]),

            Section::make('Credentials')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TagsInput::make('qualifications')->placeholder('Add qualification')->helperText('e.g. LLB (Makerere), Dip. Legal Practice (LDC)'),
                    TagsInput::make('memberships')->placeholder('Add membership')->helperText('e.g. Uganda Law Society, East Africa Law Society'),
                    Textarea::make('experience')->rows(4)->columnSpanFull(),
                ]),

            Section::make('Contact & publication')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('email')->email()->maxLength(255),
                    TextInput::make('phone')->tel()->maxLength(50),
                    Toggle::make('show_email')->label('Show email publicly'),
                    Toggle::make('show_phone')->label('Show phone publicly'),
                    Toggle::make('is_public')->label('Show on website')->helperText('Also requires verified credentials.'),
                    Toggle::make('accepts_consultations')->label('Selectable for consultation bookings'),
                    Toggle::make('is_featured')->label('Feature on homepage'),
                    Toggle::make('is_active')->label('Active')->default(true),
                    TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                ]),
        ]);
    }
}
