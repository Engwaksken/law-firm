<?php

namespace App\Filament\Pages;

use App\Models\Task;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Productivity report: per assignee, tasks open / overdue / completed in a date range.
 * Counts only tasks the viewer can see (so restricted matters stay walled off).
 */
class TaskReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Legal Practice';

    protected static ?int $navigationSort = 9;

    protected static ?string $title = 'Task report';

    protected static ?string $slug = 'reports/tasks';

    protected string $view = 'filament.pages.task-report';

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) $user && ($user->can('reports.export') || $user->can('matters.assign'));
    }

    public function mount(): void
    {
        $this->from = $this->validDate($this->from) ?? now()->startOfMonth()->toDateString();
        $this->until = $this->validDate($this->until) ?? now()->toDateString();
    }

    /** @return Collection<int, array{name: string, open: int, overdue: int, completed: int, completed_on_time: int}> */
    public function rows(): Collection
    {
        $from = Carbon::parse($this->validDate($this->from) ?? now()->startOfMonth())->startOfDay();
        $until = Carbon::parse($this->validDate($this->until) ?? now())->endOfDay();

        $tasks = Task::query()->visibleTo(auth()->user())->whereNotNull('assigned_to')
            ->get(['id', 'assigned_to', 'status', 'due_at', 'completed_at', 'created_at']);
        $names = User::query()->whereIn('id', $tasks->pluck('assigned_to')->unique())->pluck('name', 'id');

        return $tasks->groupBy('assigned_to')->map(function (Collection $items, $userId) use ($names, $from, $until) {
            $completed = $items->filter(fn (Task $t) => $t->completed_at && $t->completed_at->between($from, $until));

            return [
                'name' => $names[$userId] ?? 'Unknown',
                'open' => $items->where('status', '!=', 'done')->count(),
                'overdue' => $items->filter(fn (Task $t) => $t->isOverdue())->count(),
                'completed' => $completed->count(),
                'completed_on_time' => $completed->filter(fn (Task $t) => ! $t->due_at || $t->completed_at->lte($t->due_at))->count(),
            ];
        })->sortBy('name')->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn () => $this->exportCsv()),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $rows = $this->rows();
        \App\Models\ActivityLog::record('report.tasks-exported', description: "Task report exported ({$this->from} to {$this->until})");

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Assignee', 'Open', 'Overdue', 'Completed in period', 'Completed on time']);
            foreach ($rows as $row) {
                fputcsv($out, [static::safeCell($row['name']), $row['open'], $row['overdue'], $row['completed'], $row['completed_on_time']]);
            }
            fclose($out);
        }, 'task-report-'.$this->from.'-to-'.$this->until.'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @, tab, CR). */
    public static function safeCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function validDate(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
    }
}
