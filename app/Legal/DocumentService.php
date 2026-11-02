<?php

namespace App\Legal;

use App\Models\Document;
use App\Models\DocumentAccessLog;
use App\Models\DocumentVersion;
use App\Models\Matter;
use App\Models\User;
use App\Security\Scanning\Scanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;

/**
 * The only way documents enter or leave storage.
 *
 *  - files go to the PRIVATE disk under random names;
 *  - each version stores a SHA-256 of its content (integrity / chain of custody);
 *  - every upload is malware-scanned; infected files are quarantined and never downloadable;
 *  - downloads use short-lived signed links, issued only after authorisation, and are logged.
 */
class DocumentService
{
    /** Extensions accepted for legal documents. Archives and executables are refused. */
    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'eml', 'msg'];

    public const ALLOWED_MIME_TYPES = [
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'application/rtf', 'text/rtf', 'application/vnd.oasis.opendocument.text',
        'image/jpeg', 'image/png', 'image/tiff', 'message/rfc822', 'application/vnd.ms-outlook',
    ];

    public function __construct(private readonly Scanner $scanner) {}

    public static function disk(): string
    {
        $disk = (string) config('firm.documents.disk');
        if ($disk === 'public') {
            throw new InvalidArgumentException('Confidential documents must not be stored on the public disk.');
        }

        return $disk;
    }

    /**
     * Create a document with its first version.
     *
     * @param  array{matter_id?: int|null, client_id?: int|null, task_id?: int|null, folder?: string|null, category?: string, title: string, description?: string|null, is_evidence?: bool, is_template?: bool}  $attributes
     */
    public function store(UploadedFile|string $file, array $attributes, User $user, ?string $notes = null): Document
    {
        $this->authorizeCreate($attributes, $user);

        return DB::transaction(function () use ($file, $attributes, $user, $notes) {
            $document = new Document($attributes);
            $document->forceFill(['uploaded_by' => $user->id, 'status' => 'draft'])->save();

            $this->addVersionUnchecked($document, $file, $notes, $user);

            return $document->refresh();
        });
    }

    /**
     * A client uploads a document the firm requested, through the portal.
     * Authorised by PortalAccess (clients have no staff permissions); same
     * type checks, scanning and private storage as staff uploads.
     */
    public function storeForClient(UploadedFile|string $file, \App\Models\DocumentRequest $request, User $client): Document
    {
        abort_unless(
            $request->status === 'open' && \App\Support\PortalAccess::documentRequests($client)->whereKey($request->id)->exists(),
            404,
        );

        return DB::transaction(function () use ($file, $request, $client) {
            $document = new Document([
                'matter_id' => $request->matter_id,
                'client_id' => $request->matter?->client_id,
                'category' => 'other',
                'folder' => 'Client uploads',
                'title' => $request->title,
            ]);
            $document->forceFill(['uploaded_by' => $client->id, 'status' => 'draft', 'uploaded_by_client' => true])->save();

            $version = $this->addVersionUnchecked($document, $file, 'Uploaded by the client via the portal', $client);

            // Infected files never satisfy the request.
            if ($version->scan_status !== 'infected') {
                $request->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'document_id' => $document->id])->save();
            }

            return $document->refresh();
        });
    }

    public function addVersion(Document $document, UploadedFile|string $file, ?string $notes, User $user): DocumentVersion
    {
        Gate::forUser($user)->authorize('uploadVersion', $document);

        return DB::transaction(fn () => $this->addVersionUnchecked($document, $file, $notes, $user));
    }

    /** Short-lived signed link. Authorised and logged here; re-authorised on use. */
    public function temporaryDownloadUrl(DocumentVersion $version, User $user, bool $inline = false): string
    {
        $document = $version->document;
        Gate::forUser($user)->authorize('download', $document);
        abort_unless($version->isDownloadable(), 403, 'This file is not available for download.');

        $this->log($document, 'link_issued', $version, $user);

        return URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes((int) config('firm.documents.link_ttl_minutes', 5)),
            array_filter(['version' => $version->id, 'inline' => $inline ? 1 : null]),
        );
    }

    public function log(Document $document, string $action, ?DocumentVersion $version, ?User $user): DocumentAccessLog
    {
        return DocumentAccessLog::create([
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : (substr((string) request()->userAgent(), 0, 255) ?: null),
        ]);
    }

    private function addVersionUnchecked(Document $document, UploadedFile|string $file, ?string $notes, User $user): DocumentVersion
    {
        [$sourcePath, $originalName] = $file instanceof UploadedFile
            ? [$file->getRealPath(), $file->getClientOriginalName()]
            : [$file, basename($file)];

        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            throw new InvalidArgumentException('Uploaded file is not readable.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = (new MimeTypes)->guessMimeType($sourcePath) ?? 'application/octet-stream';
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true) || ! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException("File type not allowed ({$extension}, {$mime}).");
        }

        $size = filesize($sourcePath);
        if ($size > (int) config('firm.documents.max_upload_kb', 51200) * 1024) {
            throw new InvalidArgumentException('File is too large.');
        }

        // Scan BEFORE the file joins the document store.
        $scan = $this->scanner->scan($sourcePath);

        $folder = $scan->isInfected()
            ? 'documents/quarantine'
            : 'documents/'.match (true) {
                $document->matter_id !== null => 'matter-'.$document->matter_id,
                $document->is_template => 'templates',
                default => 'client-'.$document->client_id,
            };

        $storedName = Str::uuid().'.'.$extension;
        $stream = fopen($sourcePath, 'rb');
        Storage::disk(self::disk())->writeStream($folder.'/'.$storedName, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => ((int) $document->versions()->max('version_number')) + 1,
            'disk' => self::disk(),
            'path' => $folder.'/'.$storedName,
            'original_name' => Str::limit(basename($originalName), 200, ''),
            'mime_type' => $mime,
            'size_bytes' => $size,
            'sha256' => hash_file('sha256', $sourcePath),
            'scan_status' => $scan->status,
            'scanned_at' => $scan->status === 'not_scanned' ? null : now(),
            'change_notes' => $notes,
            'uploaded_by' => $user->id,
        ]);

        // Infected uploads never become the current version.
        if (! $scan->isInfected()) {
            $document->forceFill(['current_version_id' => $version->id])->save();
        }

        $this->log($document, 'upload', $version, $user);
        if ($scan->isInfected()) {
            \App\Models\ActivityLog::record('document.quarantined', $document,
                "Upload quarantined: malware detected ({$scan->signature})", userId: $user->id);
        }

        return $version;
    }

    private function authorizeCreate(array $attributes, User $user): void
    {
        $gate = Gate::forUser($user);

        if (! empty($attributes['matter_id'])) {
            $matter = Matter::withTrashed()->findOrFail($attributes['matter_id']);
            $gate->authorize('createFor', [Document::class, $matter]);

            return;
        }

        if (! empty($attributes['is_template'])) {
            abort_unless($gate->allows('documents.upload'), 403);

            return;
        }

        abort_unless(! empty($attributes['client_id']) && $gate->allows('documents.upload') && $gate->allows('clients.manage'), 403,
            'A document must belong to a matter, a client or be a template.');
    }
}
