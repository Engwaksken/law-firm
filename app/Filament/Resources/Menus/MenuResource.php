<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Filament\Resources\Menus\Schemas\MenuForm;
use App\Models\Menu;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Menus';

    protected static ?string $recordTitleAttribute = 'name';

    public const LOCATIONS = ['header' => 'Header (main navigation)', 'footer' => 'Footer ("The firm" links)'];

    public static function form(Schema $schema): Schema
    {
        return MenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('location')->badge()->formatStateUsing(fn (string $state) => self::LOCATIONS[$state] ?? $state),
                TextColumn::make('all_items_count')->label('Items')
                    ->state(fn (Menu $record) => MenuItem::where('menu_id', $record->id)->count()),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'create' => CreateMenu::route('/create'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }

    /** Repeater state from stored rows (two levels). */
    public static function itemsState(Menu $menu): array
    {
        $map = fn (MenuItem $item) => $item->only(['label', 'link_type', 'url', 'route_name', 'page_id', 'open_in_new_tab', 'is_active']);

        return MenuItem::where('menu_id', $menu->id)->whereNull('parent_id')->orderBy('sort_order')->with('children')->get()
            ->map(fn (MenuItem $item) => $map($item) + ['children' => $item->children->map($map)->values()->all()])
            ->values()->all();
    }

    /** Replace a menu's items with the repeater state, in one transaction. */
    public static function syncItems(Menu $menu, array $items): void
    {
        DB::transaction(function () use ($menu, $items) {
            MenuItem::where('menu_id', $menu->id)->whereNotNull('parent_id')->delete();
            MenuItem::where('menu_id', $menu->id)->delete();

            foreach (array_values($items) as $i => $item) {
                $parent = $menu->hasMany(MenuItem::class)->create(self::attributes($item, $i));
                foreach (array_values($item['children'] ?? []) as $j => $child) {
                    MenuItem::create(self::attributes($child, $j) + ['menu_id' => $menu->id, 'parent_id' => $parent->id]);
                }
            }
        });
    }

    private static function attributes(array $item, int $order): array
    {
        $type = $item['link_type'] ?? 'url';

        return [
            'label' => $item['label'],
            'link_type' => $type,
            // Keep only the field relevant to the chosen link type.
            'url' => $type === 'url' ? ($item['url'] ?? null) : null,
            'route_name' => $type === 'route' ? ($item['route_name'] ?? null) : null,
            'page_id' => $type === 'page' ? ($item['page_id'] ?? null) : null,
            'open_in_new_tab' => (bool) ($item['open_in_new_tab'] ?? false),
            'is_active' => (bool) ($item['is_active'] ?? true),
            'sort_order' => $order,
        ];
    }
}
