<?php

namespace App\Finance;

use Illuminate\Support\Facades\DB;

/**
 * Gap-free sequential numbers per series and year (e.g. INV-2026-00001).
 * Must be called inside the transaction that creates the numbered record,
 * so a rollback also rolls back the number.
 */
class DocumentNumbers
{
    public const SERIES = ['INV' => 'Invoice', 'RCT' => 'Receipt', 'CRN' => 'Credit note'];

    public function next(string $series, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Document numbers must be allocated inside a database transaction.');
        }

        // Row lock serialises concurrent issuers (no duplicates, no gaps).
        $row = DB::table('invoice_sequences')->where(['series' => $series, 'year' => $year])->lockForUpdate()->first();
        if (! $row) {
            DB::table('invoice_sequences')->insertOrIgnore(['series' => $series, 'year' => $year, 'last_number' => 0]);
            $row = DB::table('invoice_sequences')->where(['series' => $series, 'year' => $year])->lockForUpdate()->first();
        }

        $number = $row->last_number + 1;
        DB::table('invoice_sequences')->where(['series' => $series, 'year' => $year])->update(['last_number' => $number]);

        return sprintf('%s-%d-%05d', $series, $year, $number);
    }
}
