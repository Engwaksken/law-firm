<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Testimonial')
                ->description('Shown on the website only when the client\'s consent is recorded AND the testimonial is approved. Check professional conduct rules on client testimonials before publishing.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('author_name')->required()->maxLength(255),
                    TextInput::make('author_title')->maxLength(255)->helperText('e.g. Managing Director, Example Ltd'),
                    Textarea::make('quote')->required()->rows(4)->maxLength(1000)->columnSpanFull(),
                    Toggle::make('consent_obtained')->label('Written client consent obtained'),
                    Toggle::make('is_approved')->label('Approved for publication')
                        ->disabled(fn () => ! auth()->user()->can('content.publish'))
                        ->helperText(fn () => auth()->user()->can('content.publish') ? null : 'Requires publishing rights.'),
                    TextInput::make('sort_order')->numeric()->default(0)->minValue(0),
                ]),
        ]);
    }
}
