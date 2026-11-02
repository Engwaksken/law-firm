<?php

namespace Tests\Feature\Legal\Practice;

use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Legal\DocumentService;
use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\Matter;
use App\Security\Scanning\ScanResult;
use App\Security\Scanning\Scanner;
use App\Support\Access;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

class DocumentsTest extends PracticeTestCase
{
    private function pdf(string $name = 'brief.pdf', string $body = 'Confidential brief'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%".$body."\n%%EOF");
    }

    private function upload(?Matter $matter = null, array $attrs = []): Document
    {
        $this->actingAs($this->lead);

        return app(DocumentService::class)->store($this->pdf(), array_merge(['matter_id' => ($matter ?? $this->matter)->id, 'title' => 'Plaint', 'category' => 'pleading'], $attrs), $this->lead);
    }

    public function test_upload_stores_privately_with_hash_scan_status_and_log(): void
    {
        $file = $this->pdf();
        $expectedHash = hash_file('sha256', $file->getRealPath());

        $document = $this->upload();
        $version = $document->currentVersion;

        $this->assertSame(1, $version->version_number);
        $this->assertSame($expectedHash, $version->sha256);
        $this->assertSame('application/pdf', $version->mime_type);
        $this->assertSame('not_scanned', $version->scan_status, 'No scanner configured → flagged, not silently trusted');
        $this->assertSame(config('firm.documents.disk'), $version->disk);
        $this->assertNotSame('public', $version->disk);
        Storage::disk($version->disk)->assertExists($version->path);
        $this->assertStringStartsWith('documents/matter-'.$this->matter->id.'/', $version->path);
        $this->assertStringNotContainsString('brief', $version->path, 'Stored name is random');
        $this->assertDatabaseHas('document_access_logs', ['document_id' => $document->id, 'action' => 'upload', 'user_id' => $this->lead->id]);
    }

    public function test_disallowed_file_types_are_rejected(): void
    {
        $this->actingAs($this->lead);
        $this->expectException(\InvalidArgumentException::class);

        app(DocumentService::class)->store(UploadedFile::fake()->createWithContent('tool.exe', "MZ\x90\x00binary"),
            ['matter_id' => $this->matter->id, 'title' => 'X'], $this->lead);
    }

    public function test_outsider_cannot_upload_to_a_matter_they_are_not_on(): void
    {
        $this->actingAs($this->outsider);
        $this->expectException(AuthorizationException::class);

        app(DocumentService::class)->store($this->pdf(), ['matter_id' => $this->matter->id, 'title' => 'X'], $this->outsider);
    }

    public function test_infected_upload_is_quarantined_and_never_downloadable(): void
    {
        $this->app->instance(Scanner::class, new class implements Scanner {
            public function scan(string $absolutePath): ScanResult { return ScanResult::infected('Eicar-Test-Signature'); }
        });

        $document = $this->upload();
        $version = $document->versions()->first();

        $this->assertSame('infected', $version->scan_status);
        $this->assertNull($document->current_version_id, 'Infected file never becomes current');
        $this->assertStringStartsWith('documents/quarantine/', $version->path);
        $this->assertFalse($version->isDownloadable());
        $this->assertDatabaseHas('activity_logs', ['event' => 'document.quarantined', 'subject_id' => $document->id]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(DocumentService::class)->temporaryDownloadUrl($version, $this->lead);
    }

    public function test_clean_scan_result_is_recorded(): void
    {
        $this->app->instance(Scanner::class, new class implements Scanner {
            public function scan(string $absolutePath): ScanResult { return ScanResult::clean(); }
        });

        $this->assertSame('clean', $this->upload()->currentVersion->scan_status);
    }

    public function test_lead_downloads_via_signed_link_and_download_is_logged(): void
    {
        $document = $this->upload();
        $url = app(DocumentService::class)->temporaryDownloadUrl($document->currentVersion, $this->lead);

        $response = $this->actingAs($this->lead)->get($url);

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(['upload', 'link_issued', 'download'], DocumentAccessLog::where('document_id', $document->id)->orderBy('id')->pluck('action')->all());
    }

    public function test_signed_link_does_not_work_for_another_user(): void
    {
        $document = $this->upload();
        $url = app(DocumentService::class)->temporaryDownloadUrl($document->currentVersion, $this->lead);

        $this->actingAs($this->outsider)->get($url)->assertForbidden();
    }

    public function test_outsider_cannot_obtain_a_link(): void
    {
        $document = $this->upload();

        $this->expectException(AuthorizationException::class);
        app(DocumentService::class)->temporaryDownloadUrl($document->currentVersion, $this->outsider);
    }

    public function test_tampered_expired_and_unsigned_links_are_rejected(): void
    {
        $document = $this->upload();
        $url = app(DocumentService::class)->temporaryDownloadUrl($document->currentVersion, $this->lead);
        $this->actingAs($this->lead);

        $this->get(preg_replace('/signature=[a-f0-9]+/', 'signature=deadbeef', $url))->assertForbidden();
        $this->get(route('documents.download', $document->currentVersion))->assertForbidden();

        $this->travel((int) config('firm.documents.link_ttl_minutes') + 1)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_link_stops_working_when_access_is_removed(): void
    {
        $assistant = $this->userWithRole(Access::LEGAL_ASSISTANT);
        $this->matter->assignments()->create(['user_id' => $assistant->id, 'role' => 'assistant']);
        $document = $this->upload();
        $url = app(DocumentService::class)->temporaryDownloadUrl($document->currentVersion, $assistant);

        $this->matter->assignments()->where('user_id', $assistant->id)->delete();

        $this->actingAs($assistant)->get($url)->assertForbidden();
    }

    public function test_document_list_only_shows_accessible_documents(): void
    {
        $mine = $this->upload(attrs: ['title' => 'Visible plaint']);
        $otherMatter = Matter::factory()->active()->ledBy($this->outsider)->create();
        $this->actingAs($this->outsider);
        $theirs = app(DocumentService::class)->store($this->pdf(), ['matter_id' => $otherMatter->id, 'title' => 'Hidden opinion'], $this->outsider);

        $this->actingAs($this->lead);
        Livewire::test(ListDocuments::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);

        $this->get(\App\Filament\Resources\Documents\DocumentResource::getUrl('view', ['record' => $theirs]))->assertNotFound();
    }

    public function test_restricted_matter_documents_hidden_from_view_all_and_system_owner_unless_assigned(): void
    {
        $walled = Matter::factory()->active()->restricted()->ledBy($this->lead)->create();
        $document = $this->upload($walled, ['title' => 'Walled document']);
        $partner = $this->userWithRole(Access::MANAGING_PARTNER);
        $owner = $this->userWithRole(Access::SYSTEM_OWNER);

        foreach ([$partner, $owner] as $user) {
            $this->assertFalse($user->can('view', $document));
            $this->assertFalse($user->can('download', $document));
            $this->assertSame(0, Document::query()->visibleTo($user)->whereKey($document->id)->count());
        }

        // An unrestricted matter's document is visible to view-all.
        $open = $this->upload(attrs: ['title' => 'Open document']);
        $this->assertTrue($partner->can('view', $open));

        // Assignment lifts the wall for that person only.
        $walled->assignments()->create(['user_id' => $partner->id, 'role' => 'associate']);
        $this->assertTrue($partner->fresh()->can('view', $document));
        $this->assertFalse($owner->can('view', $document));
    }

    public function test_new_version_increments_and_resets_approval(): void
    {
        $document = $this->upload();
        $document->forceFill(['status' => 'draft'])->save();

        $version = app(DocumentService::class)->addVersion($document, $this->pdf('brief-v2.pdf', 'Second draft'), 'Amended para 4', $this->lead);

        $this->assertSame(2, $version->version_number);
        $this->assertSame($version->id, $document->fresh()->current_version_id);
        $this->assertSame(2, $document->versions()->count());
    }

    public function test_versions_are_immutable(): void
    {
        $version = $this->upload()->currentVersion;
        $originalHash = $version->sha256;

        $version->sha256 = str_repeat('0', 64);
        $version->save();

        $this->assertSame($originalHash, $version->fresh()->sha256);
    }

    public function test_approval_by_approver_and_submit_notifies(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $partner = $this->userWithRole(Access::PARTNER);
        $this->matter->assignments()->create(['user_id' => $partner->id, 'role' => 'associate']);
        $document = $this->upload();

        $this->actingAs($this->lead);
        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])
            ->assertActionHidden('approve')
            ->callAction('submitForApproval');
        $this->assertSame('pending_approval', $document->fresh()->status);
        \Illuminate\Support\Facades\Notification::assertSentTo($partner, \App\Notifications\Practice\DocumentAwaitingApproval::class);

        $this->actingAs($partner);
        Livewire::test(ViewDocument::class, ['record' => $document->getRouteKey()])->callAction('approve');

        $document->refresh();
        $this->assertSame('approved', $document->status);
        $this->assertSame($partner->id, $document->approved_by);
        $this->assertDatabaseHas('document_access_logs', ['document_id' => $document->id, 'action' => 'approve']);
    }

    public function test_evidence_cannot_be_deleted(): void
    {
        $evidence = $this->upload(attrs: ['is_evidence' => true, 'category' => 'evidence']);
        $partner = $this->userWithRole(Access::PARTNER);
        $this->matter->assignments()->create(['user_id' => $partner->id, 'role' => 'associate']);

        $this->assertFalse($partner->can('delete', $evidence));
        $this->assertTrue($partner->can('delete', $this->upload()));
    }

    public function test_access_logs_cannot_be_altered(): void
    {
        $document = $this->upload();
        $log = DocumentAccessLog::where('document_id', $document->id)->first();

        $log->action = 'nothing';
        $log->save();
        $log->delete();

        $this->assertDatabaseHas('document_access_logs', ['id' => $log->id, 'action' => 'upload']);
    }

    public function test_public_disk_is_refused_for_documents(): void
    {
        config(['firm.documents.disk' => 'public']);

        $this->expectException(\InvalidArgumentException::class);
        DocumentService::disk();
    }
}
