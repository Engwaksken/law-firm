<?php

namespace App\Filament\Resources\Matters\RelationManagers;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Models\Document;
use App\Models\Matter;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/** Documents tab on a matter. Register on MatterResource::getRelations(). */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(DocumentResource::uploadFields(withOwner: false));
    }

    public function table(Table $table): Table
    {
        return DocumentResource::tableFor($table, showMatter: false)
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['currentVersion', 'uploader']))
            ->headerActions([
                Action::make('upload')->label('Upload document')->icon(Heroicon::OutlinedArrowUpTray)
                    ->visible(fn () => Gate::allows('createFor', [Document::class, $this->getOwnerRecord()]))
                    ->schema(DocumentResource::uploadFields(withOwner: false))
                    ->action(function (array $data) {
                        /** @var Matter $matter */
                        $matter = $this->getOwnerRecord();
                        Gate::authorize('createFor', [Document::class, $matter]);
                        CreateDocument::storeUpload($data, 'mountedActions.0.data.file', ['matter_id' => $matter->id, 'client_id' => null, 'is_template' => false]);
                    }),
            ]);
    }
}
