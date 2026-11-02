<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    /** New pages always start as drafts; publishing goes through the workflow actions. */
    protected function handleRecordCreation(array $data): Model
    {
        $blocks = $data['content_blocks'] ?? [];
        unset($data['content_blocks']);

        return DB::transaction(function () use ($data, $blocks) {
            $page = static::getModel()::create(['status' => 'draft'] + $data);
            $page->syncBlocks($blocks);

            return $page;
        });
    }
}
