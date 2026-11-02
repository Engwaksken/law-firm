<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Legal\DocumentService;
use App\Models\DocumentVersion;
use App\Support\PortalAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Client download of a shared document. Route: signed + expiring + portal
 * authentication; access is re-checked here with PortalAccess, and only the
 * CURRENT version of a shared document can be downloaded (older drafts never).
 * Anything not visible returns 404 so existence isn't revealed.
 */
class PortalDocumentController extends Controller
{
    public static function temporaryUrl(DocumentVersion $version): string
    {
        return URL::temporarySignedRoute(
            'filament.portal.files.document',
            now()->addMinutes((int) config('firm.documents.link_ttl_minutes', 5)),
            ['version' => $version->id],
        );
    }

    public function __invoke(Request $request, DocumentVersion $version, DocumentService $documents): StreamedResponse
    {
        $document = $version->document;
        $user = $request->user();

        abort_unless(
            $document && ! $document->trashed()
            && $document->current_version_id === $version->id
            && $version->isDownloadable()
            && PortalAccess::canSeeDocument($user, $document),
            404,
        );

        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);

        $documents->log($document, 'download', $version, $user);

        return $disk->download($version->path, $version->original_name, [
            'Content-Type' => $version->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => 'sandbox',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
