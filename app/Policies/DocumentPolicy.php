<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DocumentPolicy extends MatterChildPolicy
{
    protected ?string $writePermission = 'documents.upload';

    public function viewAny(User $user): bool
    {
        return parent::viewAny($user) || $user->can('clients.view');
    }

    public function download(User $user, Document $document): bool
    {
        return $document->canBeSeenBy($user);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->canWrite($user, $record);
    }

    /** New version or metadata. */
    public function uploadVersion(User $user, Document $document): bool
    {
        return $this->canWrite($user, $document) && $document->status !== 'approved';
    }

    public function approve(User $user, Document $document): bool
    {
        return $user->can('documents.approve') && $document->canBeSeenBy($user)
            && ($document->matter === null || $document->matter->status !== 'archived');
    }

    /** Evidence is never deleted through the UI (chain of custody). */
    public function delete(User $user, Model $record): bool
    {
        return ! $record->is_evidence && $user->can('documents.approve') && $record->canBeSeenBy($user);
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->can('documents.approve') && $record->canBeSeenBy($user);
    }

    public function createFor(User $user, Matter $matter): bool
    {
        return parent::createFor($user, $matter);
    }

    private function canWrite(User $user, Document $document): bool
    {
        if ($document->matter_id !== null) {
            return $this->canWriteOn($user, $document->matter);
        }

        return $user->can('documents.upload') && $document->canBeSeenBy($user) && ($document->is_template || $user->can('clients.manage'));
    }
}
