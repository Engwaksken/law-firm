<?php

namespace App\Security\Scanning;

use Throwable;

/**
 * Streams a file to a clamd daemon using the INSTREAM command
 * (zINSTREAM\0, then <uint32 big-endian length><chunk>…, terminated by a zero length).
 * Replies: "stream: OK" | "stream: <Signature> FOUND" | "… ERROR".
 */
class ClamAvScanner implements Scanner
{
    private const CHUNK = 8192;

    public function __construct(
        private readonly string $socket,
        private readonly int $timeout = 30,
    ) {}

    public function scan(string $absolutePath): ScanResult
    {
        try {
            $file = @fopen($absolutePath, 'rb');
            if ($file === false) {
                return ScanResult::error('Cannot read file');
            }

            $conn = @stream_socket_client($this->socket, $errno, $errstr, $this->timeout);
            if ($conn === false) {
                fclose($file);

                return ScanResult::error("clamd unreachable: {$errstr}");
            }
            stream_set_timeout($conn, $this->timeout);

            fwrite($conn, "zINSTREAM\0");
            while (! feof($file)) {
                $chunk = fread($file, self::CHUNK);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                fwrite($conn, pack('N', strlen($chunk)).$chunk);
            }
            fwrite($conn, pack('N', 0));
            fclose($file);

            $reply = '';
            while (! feof($conn)) {
                $part = fread($conn, 4096);
                if ($part === false || $part === '') {
                    break;
                }
                $reply .= $part;
            }
            fclose($conn);

            return $this->parse($reply);
        } catch (Throwable $e) {
            return ScanResult::error($e->getMessage());
        }
    }

    public function parse(string $reply): ScanResult
    {
        $reply = trim($reply, "\0\r\n ");

        if (preg_match('/:\s*OK$/', $reply)) {
            return ScanResult::clean();
        }
        if (preg_match('/:\s*(.+)\s+FOUND$/', $reply, $m)) {
            return ScanResult::infected(trim($m[1]));
        }

        return ScanResult::error($reply === '' ? 'Empty reply from clamd' : $reply);
    }
}
