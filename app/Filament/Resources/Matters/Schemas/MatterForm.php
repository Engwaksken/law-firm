<?php

namespace App\Filament\Resources\Matters\Schemas;

use App\Models\Client;
use App\Models\Court;
use App\Models\Matter;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MatterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Matter')->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Select::make('client_id')->label('Client')->required()
                    ->options(fn () => Client::query()->whereIn('status', ['prospect', 'active'])->orderBy('name')->limit(1000)->get()
                        ->mapWithKeys(fn (Client $c) => [$c->id => "{$c->reference} · {$c->name}"]))
                    ->searchable()
                    ->disabledOn('edit')
                    ->helperText('Create the client first if they are new. The client cannot be changed after opening.'),
                Select::make('practice_area_id')->label('Practice area')->relationship('practiceArea', 'name')->searchable()->preload(),
                TextInput::make('case_type')->maxLength(120)->datalist(['Civil suit', 'Criminal case', 'Arbitration', 'Advisory', 'Transaction', 'Application', 'Appeal', 'Probate']),
                Select::make('priority')->options(Matter::PRIORITIES)->default('normal')->required(),
                Select::make('lead_user_id')->label('Lead advocate')
                    ->options(fn () => User::query()->where('is_active', true)->permission('matters.view-assigned')->orderBy('name')->pluck('name', 'id'))
                    ->default(fn () => auth()->id())->searchable()->required(),
                Select::make('office_id')->relationship('office', 'name'),
            ]),
            Section::make('Court / tribunal')->columns(2)->columnSpanFull()->collapsible()->schema([
                Select::make('court_id')->label('Court')
                    ->options(fn () => Court::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn (Court $c) => [$c->id => $c->label()]))
                    ->searchable(),
                TextInput::make('jurisdiction')->maxLength(120),
                TextInput::make('case_number')->maxLength(120),
                DatePicker::make('filing_date'),
            ]),
            Section::make('Confidentiality')->columnSpanFull()->schema([
                Toggle::make('is_restricted')->label('Restricted matter (ethical wall)')
                    ->helperText('Only people assigned to this matter can see it, including partners and administrators.')
                    ->disabled(fn () => ! auth()->user()->can('matters.assign')),
                Textarea::make('description')->label('Summary')->rows(5)->helperText('Encrypted. Visible to the matter team only.'),
            ]),
        ]);
    }
}
