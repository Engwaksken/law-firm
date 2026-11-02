<?php

namespace App\Security\Scanning;

/** Outcome of a malware scan. Status matches DocumentVersion::SCAN_STATUSES. */
final class ScanResult
{
    public function __construct(
        public readonly string $status,        // clean | infected | error | not_scanned
        public readonly ?string $signature = null,
        public readonly ?string $message = null,
    ) {}

    public static function clean(): self { return new self('clean'); }
    public static function infected(string $signature): self { return new self('infected', $signature); }
    public static function error(string $message): self { return new self('error', message: $message); }
    public static function notScanned(): self { return new self('not_scanned'); }

    public function isInfected(): bool { return $this->status === 'infected'; }
}
