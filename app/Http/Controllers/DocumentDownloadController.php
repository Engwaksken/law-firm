<?php

namespace App\Http\Controllers;

use App\Legal\DocumentService;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a document version. The route requires sign-in AND a valid,
 * unexpired signature; authorisation is checked again here because a
 * link may outlive the user's access (reassignment, wall, deactivation).
 */
class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, DocumentVersion $version, DocumentService $documents): StreamedResponse
    {
        $document = $version->document;
        abort_if($document === null || $document->trashed(), 404);
        Gate::authorize('download', $document);
        abort_unless($version->isDownloadable(), 403, 'This file is not available for download.');

        $disk = Storage::disk($version->disk);
        abort_unless($disk->exists($version->path), 404);

        $inline = $request->boolean('inline')
            && ($version->mime_type === 'application/pdf' || str_starts_with((string) $version->mime_type, 'image/'));

        $documents->log($document, $inline ? 'view' : 'download', $version, $request->user());

        $headers = [
            'Content-Type' => $version->mime_type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'Content-Security-Policy' => 'sandbox',
            'Referrer-Policy' => 'no-referrer',
        ];

        return $inline
            ? $disk->response($version->path, $version->original_name, $headers, 'inline')
            : $disk->download($version->path, $version->original_name, $headers);
    }
}
