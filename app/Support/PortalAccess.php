<?php

namespace App\Support;

use App\Models\ClientMessage;
use App\Models\ClientUpdate;
use App\Models\Document;
use App\Models\DocumentRequest;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE rule for what a client sees in the portal (spec §13). Clients see only
 * records of the client(s) they are actively linked to, and only material the
 * firm explicitly released: published updates, shared documents, issued
 * invoices. Internal notes, unshared documents and drafts are never reachable.
 */
class PortalAccess
{
    /** Matter statuses a client may see (not intake / conflict review). */
    public const VISIBLE_MATTER_STATUSES = ['awaiting_engagement', 'active', 'pending', 'stayed', 'closed', 'archived'];

    /** @return list<int> */
    public static function clientIds(?User $user): array
    {
        if (! $user || ! $user->is_active || $user->trashed() || ! $user->isPortalUser()) {
            return [];
        }

        return $user->portalClientIds();
    }

    public static function hasClient(?User $user, ?int $clientId): bool
    {
        return $clientId !== null && in_array($clientId, static::clientIds($user), true);
    }

    public static function matters(?User $user): Builder
    {
        return Matter::query()
            ->whereIn('matters.client_id', static::clientIds($user))
            ->whereIn('matters.status', self::VISIBLE_MATTER_STATUSES);
    }

    public static function canSeeMatter(?User $user, ?Matter $matter): bool
    {
        return $matter !== null && static::matters($user)->whereKey($matter->id)->exists();
    }

    /** Published client updates on visible matters. */
    public static function updates(?User $user): Builder
    {
        return ClientUpdate::query()
            ->where('status', 'published')->whereNotNull('published_at')
            ->whereIn('matter_id', static::matters($user)->select('matters.id'));
    }

    /**
     * Documents the client may download: shared by the firm on a visible matter,
     * or uploaded by the client themselves; always with a downloadable current version.
     */
    public static function documents(?User $user): Builder
    {
        $ids = static::clientIds($user);

        return Document::query()
            ->whereNotNull('documents.current_version_id')
            ->whereHas('currentVersion', fn (Builder $query) => $query->whereIn('scan_status', ['clean', 'not_scanned']))
            ->where(function (Builder $query) use ($user, $ids) {
                $query->where(fn (Builder $shared) => $shared
                    ->whereNotNull('documents.shared_with_client_at')
                    ->whereIn('documents.matter_id', static::matters($user)->select('matters.id')))
                    ->orWhere(fn (Builder $own) => $own
                        ->where('documents.uploaded_by_client', true)
                        ->where(fn (Builder $scope) => $scope
                            ->whereIn('documents.client_id', $ids)
                            ->orWhereIn('documents.matter_id', static::matters($user)->select('matters.id'))));
            });
    }

    public static function canSeeDocument(?User $user, ?Document $document): bool
    {
        return $document !== null && static::documents($user)->whereKey($document->id)->exists();
    }

    public static function documentRequests(?User $user): Builder
    {
        return DocumentRequest::query()
            ->where('status', '!=', 'cancelled')
            ->whereIn('matter_id', static::matters($user)->select('matters.id'));
    }

    /** Issued invoices only: never drafts, pending approval or void. */
    public static function invoices(?User $user): Builder
    {
        return Invoice::query()
            ->whereIn('invoices.client_id', static::clientIds($user))
            ->whereIn('invoices.status', Invoice::ISSUED_STATUSES);
    }

    public static function canSeeInvoice(?User $user, ?Invoice $invoice): bool
    {
        return $invoice !== null && static::invoices($user)->whereKey($invoice->id)->exists();
    }

    /** Receipts for non-reversed payments on visible invoices. */
    public static function receipts(?User $user): Builder
    {
        return Receipt::query()->whereHas('payment', fn (Builder $query) => $query
            ->whereNull('reversed_at')
            ->whereIn('invoice_id', static::invoices($user)->select('invoices.id')));
    }

    public static function payments(?User $user): Builder
    {
        return Payment::query()->whereNull('reversed_at')
            ->whereIn('invoice_id', static::invoices($user)->select('invoices.id'));
    }

    /** Message threads (root messages) for the user's clients, limited to visible matters when tied to one. */
    public static function threads(?User $user): Builder
    {
        return ClientMessage::query()
            ->whereNull('client_messages.parent_id')
            ->whereIn('client_messages.client_id', static::clientIds($user))
            ->where(fn (Builder $query) => $query
                ->whereNull('client_messages.matter_id')
                ->orWhereIn('client_messages.matter_id', static::matters($user)->select('matters.id')));
    }

    public static function canSeeThread(?User $user, ?ClientMessage $message): bool
    {
        return $message !== null && $message->parent_id === null && static::threads($user)->whereKey($message->id)->exists();
    }

    /** Portal users (active links, active accounts) who represent a client. Used for notifications. */
    public static function usersForClient(int $clientId)
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('portalClients', fn (Builder $query) => $query->where('clients.id', $clientId))
            ->get()
            ->filter(fn (User $user) => $user->isPortalUser())
            ->values();
    }
}
