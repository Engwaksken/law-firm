<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Filament\Resources\Menus\MenuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListMenus extends ListRecords
{
    protected static string $resource = MenuResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(function (array $data): \App\Models\Menu {
            return DB::transaction(function () use ($data) {
                $menu = \App\Models\Menu::create(['name' => $data['name'], 'location' => $data['location']]);
                MenuResource::syncItems($menu, $data['items'] ?? []);

                return $menu;
            });
        })];
    }
}
