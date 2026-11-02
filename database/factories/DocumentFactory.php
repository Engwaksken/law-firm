<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Matter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'matter_id' => Matter::factory(),
            'category' => 'correspondence',
            'title' => ucfirst(fake()->words(3, true)),
            'is_evidence' => false,
            'is_template' => false,
        ];
    }

    /** Adds a stored version (writes a small file to the documents disk) and makes it current. */
    public function withVersion(string $scanStatus = 'clean', string $content = '%PDF-1.4 test'): static
    {
        return $this->afterCreating(function (Document $document) use ($scanStatus, $content) {
            $path = 'documents/matter-'.($document->matter_id ?? 'x').'/'.Str::uuid().'.pdf';
            Storage::disk(config('firm.documents.disk'))->put($path, $content);
            $version = DocumentVersion::create([
                'document_id' => $document->id, 'version_number' => 1, 'disk' => config('firm.documents.disk'),
                'path' => $path, 'original_name' => 'file.pdf', 'mime_type' => 'application/pdf',
                'size_bytes' => strlen($content), 'sha256' => hash('sha256', $content), 'scan_status' => $scanStatus,
            ]);
            $document->forceFill(['current_version_id' => $version->id])->save();
        });
    }
}
