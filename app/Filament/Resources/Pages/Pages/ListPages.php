<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data) {
                $blocks = $data['content_blocks'] ?? [];
                unset($data['content_blocks']);

                return DB::transaction(function () use ($data, $blocks) {
                    $page = \App\Models\Page::create(['status' => 'draft'] + $data);
                    $page->syncBlocks($blocks);

                    return $page;
                });
            }),
        ];
    }
}
