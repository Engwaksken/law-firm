<?php

namespace App\Filament\Support;

use App\Models\Matter;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Matter choices for forms: only matters the user may add $modelClass records to. */
class MatterOptions
{
    /** @return array<int, string> id => "REF · title" */
    public static function contributable(string $modelClass, ?User $user = null): array
    {
        $user ??= auth()->user();

        return Matter::query()->visibleTo($user)
            ->whereNotIn('status', ['archived'])
            ->orderByDesc('updated_at')->limit(500)->get()
            ->filter(fn (Matter $m) => Gate::forUser($user)->allows('createFor', [$modelClass, $m]))
            ->mapWithKeys(fn (Matter $m) => [$m->id => $m->label()])
            ->all();
    }

    /** @return array<int, string> matters the user can see (for filters). */
    public static function visible(?User $user = null): array
    {
        return Matter::query()->visibleTo($user ?? auth()->user())->orderByDesc('updated_at')->limit(500)->get()
            ->mapWithKeys(fn (Matter $m) => [$m->id => $m->label()])->all();
    }

    /** Server-side guard used in create/save hooks. */
    public static function authorizeFor(string $modelClass, ?int $matterId): void
    {
        if ($matterId === null) {
            return;
        }
        $matter = Matter::withTrashed()->findOrFail($matterId);
        Gate::authorize('createFor', [$modelClass, $matter]);
    }

    /** @return array<int, string> users on a matter (lead + team). */
    public static function teamOptions(?int $matterId): array
    {
        if (! $matterId) {
            return User::query()->where('is_active', true)->whereHas('roles', fn ($query) => $query->where('name', '!=', 'client'))
                ->orderBy('name')->pluck('name', 'id')->all();
        }
        $matter = Matter::withTrashed()->find($matterId);
        if (! $matter) {
            return [];
        }

        return User::query()->where('is_active', true)
            ->whereIn('id', array_filter(array_merge([$matter->lead_user_id], $matter->assignments()->pluck('user_id')->all())))
            ->orderBy('name')->pluck('name', 'id')->all();
    }
}
