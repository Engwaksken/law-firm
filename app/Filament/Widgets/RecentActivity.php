<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentActivity extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Recent activity';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('activity-logs.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ActivityLog::query()->with('user')->latest()->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('created_at')->label('When')->since(),
                TextColumn::make('event')->badge(),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('description')->limit(60),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('all')->label('View audit log')->url(ActivityLogResource::getUrl())->link(),
            ]);
    }
}
