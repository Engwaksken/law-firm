<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Filament\Resources\Menus\MenuResource;
use App\Models\MenuItem;
use App\Models\Page;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Menu')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->required()->maxLength(100),
                Select::make('location')->options(MenuResource::LOCATIONS)->required()->unique(ignoreRecord: true)
                    ->helperText('Leave a location without a menu to use the built-in default navigation.'),
            ]),
            Section::make('Items')->columnSpanFull()
                ->description('Drag to reorder. The header supports one level of sub-items (shown as a dropdown).')
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->schema([
                            ...self::itemFields(),
                            Repeater::make('children')->label('Sub-items')
                                ->schema(self::itemFields())
                                ->reorderable()->collapsible()->collapsed()
                                ->itemLabel(fn (array $state) => $state['label'] ?? null)
                                ->defaultItems(0)->addActionLabel('Add sub-item')
                                ->columnSpanFull(),
                        ])
                        ->reorderable()->collapsible()
                        ->itemLabel(fn (array $state) => $state['label'] ?? null)
                        ->defaultItems(0)->addActionLabel('Add menu item'),
                ]),
        ]);
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    private static function itemFields(): array
    {
        return [
            Grid::make(3)->schema([
                TextInput::make('label')->required()->maxLength(100),
                Select::make('link_type')->label('Links to')->options(MenuItem::LINK_TYPES)->default('route')->required()->live(),
                Select::make('route_name')->label('Page')->options(MenuItem::ROUTES)
                    ->visible(fn (Get $get) => $get('link_type') === 'route')
                    ->required(fn (Get $get) => $get('link_type') === 'route'),
                Select::make('page_id')->label('CMS page')
                    ->options(fn () => Page::query()->orderBy('title')->pluck('title', 'id'))
                    ->searchable()
                    ->visible(fn (Get $get) => $get('link_type') === 'page')
                    ->required(fn (Get $get) => $get('link_type') === 'page')
                    ->helperText('Hidden on the site until the page is published.'),
                TextInput::make('url')->label('URL')->maxLength(500)
                    ->visible(fn (Get $get) => $get('link_type') === 'url')
                    ->required(fn (Get $get) => $get('link_type') === 'url')
                    ->rules([fn () => self::safeUrlRule()])
                    ->placeholder('https://… or /path'),
            ]),
            Grid::make(2)->schema([
                Toggle::make('open_in_new_tab')->label('Open in new tab'),
                Toggle::make('is_active')->label('Visible')->default(true),
            ]),
        ];
    }

    /** http(s), mailto:, tel:, or a site-relative path. Blocks javascript:, data: and protocol-relative URLs. */
    private static function safeUrlRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (blank($value)) {
                return;
            }
            $value = trim((string) $value);
            $relative = str_starts_with($value, '/') && ! str_starts_with($value, '//');
            $absolute = preg_match('#^(https?://[^\s]+|mailto:[^\s]+|tel:[0-9+\-\s()]+)$#i', $value)
                && ($value === filter_var($value, FILTER_SANITIZE_URL) || str_starts_with(strtolower($value), 'tel:'));
            if (! $relative && ! $absolute) {
                $fail('Enter a web address starting with https://, a mailto: or tel: link, or a path starting with /.');
            }
        };
    }
}
