<?php

namespace App\Cms;

use App\Models\ActivityLog;
use App\Models\Page;
use App\Models\User;
use App\Notifications\Cms\PageReviewUpdate;
use App\Support\StaffRecipients;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Page publishing workflow. Every transition is authorised here (not only in
 * the UI) and audited.
 *
 *   draft ──submit──▶ in_review ──publish──▶ published ──unpublish──▶ draft
 *     ▲                   │                      │
 *     └──request changes──┘                      └──archive──▶ archived
 */
class PageWorkflow
{
    public function submit(Page $page, User $by): void
    {
        $this->authorize($by->can('update', $page) && in_array($page->status, ['draft', 'published'], true));

        $page->forceFill(['status' => $page->status === 'published' ? 'published' : 'in_review', 'submitted_by' => $by->id, 'submitted_at' => now(), 'review_notes' => null])->save();
        ActivityLog::record('page.submitted', $page, "Page \"{$page->title}\" submitted for review");

        StaffRecipients::notify('content.publish', new PageReviewUpdate($page, 'submitted', $by->name));
    }

    public function publish(Page $page, User $by, ?Carbon $at = null): void
    {
        $this->authorize($by->can('publish', $page) && $page->status !== 'archived');

        DB::transaction(function () use ($page, $by, $at) {
            $page->forceFill([
                'status' => 'published',
                'published_at' => $at ?? ($page->isPublished() ? $page->published_at : now()),
                'published_by' => $by->id,
                'review_notes' => null,
                'submitted_at' => null,
            ])->save();
            $page->recordRevision('published');
        });

        ActivityLog::record('page.published', $page, $page->isScheduled()
            ? "Page \"{$page->title}\" scheduled for {$page->published_at->toDayDateTimeString()}"
            : "Page \"{$page->title}\" published");

        if ($page->submitted_by && $page->submitted_by !== $by->id) {
            User::find($page->submitted_by)?->notify(new PageReviewUpdate($page, 'published', $by->name));
        }
    }

    public function requestChanges(Page $page, User $by, string $notes): void
    {
        $this->authorize($by->can('publish', $page) && $page->isAwaitingReview());

        // A live page stays live; only the pending edits are sent back.
        $page->forceFill([
            'status' => $page->status === 'in_review' ? 'draft' : $page->status,
            'review_notes' => $notes,
            'submitted_at' => null,
        ])->save();
        ActivityLog::record('page.changes-requested', $page, "Changes requested on \"{$page->title}\"");

        if ($page->submitted_by) {
            User::find($page->submitted_by)?->notify(new PageReviewUpdate($page, 'changes_requested', $by->name, $notes));
        }
    }

    public function unpublish(Page $page, User $by): void
    {
        $this->authorize($by->can('publish', $page) && $page->status === 'published');

        $page->forceFill(['status' => 'draft'])->save();
        ActivityLog::record('page.unpublished', $page, "Page \"{$page->title}\" unpublished");
    }

    public function archive(Page $page, User $by): void
    {
        $this->authorize($by->can('publish', $page) && $page->status !== 'archived');

        $page->forceFill(['status' => 'archived'])->save();
        ActivityLog::record('page.archived', $page, "Page \"{$page->title}\" archived");
    }

    private function authorize(bool $allowed): void
    {
        if (! $allowed) {
            throw new AuthorizationException('This action is not allowed for this page.');
        }
    }
}
