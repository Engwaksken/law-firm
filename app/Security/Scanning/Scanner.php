<?php

namespace App\Security\Scanning;

interface Scanner
{
    /** Scan a file on the local filesystem. Must never throw: failures return ScanResult::error(). */
    public function scan(string $absolutePath): ScanResult;
}
