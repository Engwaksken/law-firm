<?php

namespace App\Security\Scanning;

/** Used when no scanner is configured. Files are flagged "not_scanned" so staff can see the risk. */
class NullScanner implements Scanner
{
    public function scan(string $absolutePath): ScanResult
    {
        return ScanResult::notScanned();
    }
}
