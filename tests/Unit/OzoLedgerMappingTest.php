<?php

namespace Tests\Unit;

use App\Console\Commands\ImportOzoLedger;
use PHPUnit\Framework\TestCase;

class OzoLedgerMappingTest extends TestCase
{
    public function test_column_index(): void
    {
        $this->assertSame(0, ImportOzoLedger::columnIndex('A'));
        $this->assertSame(2, ImportOzoLedger::columnIndex('C'));
        $this->assertSame(22, ImportOzoLedger::columnIndex('W'));
        $this->assertSame(26, ImportOzoLedger::columnIndex('AA'));
    }

    public function test_parse_member_name(): void
    {
        $this->assertSame(
            ['title' => 'Chief', 'first_name' => 'Kizito', 'middle_name' => null, 'last_name' => 'Obiegbunem'],
            ImportOzoLedger::parseMemberName('Chief Kizito Obiegbunem')
        );

        $this->assertSame(
            ['title' => 'Prince Dr.', 'first_name' => 'Charles', 'middle_name' => null, 'last_name' => 'Tabansi'],
            ImportOzoLedger::parseMemberName('Prince Dr. Charles Tabansi')
        );

        $this->assertSame(
            ['title' => 'Ide', 'first_name' => 'Martin', 'middle_name' => null, 'last_name' => 'Onuorah'],
            ImportOzoLedger::parseMemberName('Ide Martin Onuorah')
        );

        $this->assertSame(
            ['title' => 'Oba', 'first_name' => 'Emelie', 'middle_name' => null, 'last_name' => 'Okika'],
            ImportOzoLedger::parseMemberName('Oba Emelie Okika')
        );
    }

    public function test_amount_cell(): void
    {
        $this->assertSame(0.0, ImportOzoLedger::amountCell(null));
        $this->assertSame(0.0, ImportOzoLedger::amountCell(''));
        $this->assertSame(0.0, ImportOzoLedger::amountCell('-'));
        $this->assertSame(5000.0, ImportOzoLedger::amountCell(5000));
        $this->assertSame(5000.0, ImportOzoLedger::amountCell('5000'));
    }

    public function test_member_rows_skips_headers_and_totals(): void
    {
        $rows = [
            [null, ' NAMES', 'x'],
            ['S/N', ' NAMES', 'x'],
            [1, 'Oba Emelie Okika', 12000],
            [null, 'TOTAL', 3927800],
            [null, null, null],
        ];

        $this->assertCount(1, ImportOzoLedger::memberRows($rows));
    }
}
