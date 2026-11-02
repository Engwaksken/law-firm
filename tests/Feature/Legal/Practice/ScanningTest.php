<?php

namespace Tests\Feature\Legal\Practice;

use App\Security\Scanning\ClamAvScanner;
use App\Security\Scanning\NullScanner;
use App\Security\Scanning\Scanner;
use Tests\TestCase;

class ScanningTest extends TestCase
{
    public function test_container_binds_null_scanner_by_default(): void
    {
        config(['firm.scanner.driver' => 'none']);

        $scanner = app(Scanner::class);

        $this->assertInstanceOf(NullScanner::class, $scanner);
        $this->assertSame('not_scanned', $scanner->scan(__FILE__)->status);
    }

    public function test_container_binds_clamav_when_configured(): void
    {
        config(['firm.scanner.driver' => 'clamav']);

        $this->assertInstanceOf(ClamAvScanner::class, app(Scanner::class));
    }

    public function test_clamav_reply_parsing(): void
    {
        $scanner = new ClamAvScanner('tcp://127.0.0.1:1');

        $this->assertSame('clean', $scanner->parse("stream: OK\0")->status);
        $infected = $scanner->parse("stream: Eicar-Test-Signature FOUND\0");
        $this->assertTrue($infected->isInfected());
        $this->assertSame('Eicar-Test-Signature', $infected->signature);
        $this->assertSame('error', $scanner->parse('INSTREAM size limit exceeded. ERROR')->status);
    }

    public function test_unreachable_clamd_returns_error_not_clean(): void
    {
        $result = (new ClamAvScanner('tcp://127.0.0.1:1', 1))->scan(__FILE__);

        $this->assertSame('error', $result->status, 'A scanner outage must never be reported as clean');
    }
}
