<?php

namespace App\Console\Commands;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Models\Arrear;
use App\Models\ArrearPayment;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\Payment;
use Carbon\Carbon;
use DOMDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

class ImportOzoLedger extends Command
{
    protected $signature = 'import:ozo-ledger {--file=ozo_2026.xlsx} {--force : skip the wipe confirmation}';

    protected $description = 'Import members, condolences, levies and payments from the ozo_2026 spreadsheet ledger.';

    /**
     * Condolences in the ledger, oldest first.
     *
     * @var array<int, array{key: string, title: string, first_name: string, middle_name: ?string, last_name: string, email: string, amount: float, announced: string, due: ?string}>
     */
    public const CONDOLENCES = [
        ['key' => 'okonkwo', 'title' => 'Chief', 'first_name' => 'Late', 'middle_name' => 'Okey', 'last_name' => 'Okonkwo', 'email' => 'late.okey.okonkwo@nzena-ozo.local', 'amount' => 3000, 'announced' => '2024-06-01', 'due' => '2024-07-31'],
        ['key' => 'ifeacho', 'title' => 'Chief', 'first_name' => 'Late', 'middle_name' => null, 'last_name' => 'Ifeacho', 'email' => 'late.ifeacho@nzena-ozo.local', 'amount' => 3000, 'announced' => '2024-12-01', 'due' => '2025-01-15'],
        ['key' => 'vincent', 'title' => 'Chief', 'first_name' => 'Vincent', 'middle_name' => null, 'last_name' => 'Onyesoh', 'email' => 'late.vincent.onyesoh@nzena-ozo.local', 'amount' => 3000, 'announced' => '2024-12-01', 'due' => '2025-01-15'],
        ['key' => 'egolum', 'title' => 'Chief', 'first_name' => 'Ikenna', 'middle_name' => null, 'last_name' => 'Egolum', 'email' => 'late.ikenna.egolum@nzena-ozo.local', 'amount' => 3000, 'announced' => '2025-05-10', 'due' => '2025-06-10'],
        ['key' => 'nriezedi', 'title' => 'Justice', 'first_name' => 'Late', 'middle_name' => null, 'last_name' => 'Nriezedi', 'email' => 'late.nriezedi@nzena-ozo.local', 'amount' => 3000, 'announced' => '2025-09-01', 'due' => '2025-10-01'],
        ['key' => 'okafor', 'title' => 'Prof.', 'first_name' => 'Nduka', 'middle_name' => null, 'last_name' => 'Okafor', 'email' => 'late.nduka.okafor@nzena-ozo.local', 'amount' => 3000, 'announced' => '2025-10-15', 'due' => '2025-11-15'],
        ['key' => 'ofoma', 'title' => 'Oba', 'first_name' => 'Chukwudi', 'middle_name' => null, 'last_name' => 'Ofoma', 'email' => 'late.chukwudi.ofoma@nzena-ozo.local', 'amount' => 5000, 'announced' => '2026-01-10', 'due' => '2026-02-10'],
    ];

    /**
     * Payment buckets in chronological order. Each bucket pays the oldest
     * debt first (opening balance, then levies in condolence order).
     *
     * Columns are 0-indexed. levyCols maps sheet column => condolence key.
     *
     * @var array<int, array{code: string, pay_col: int, paid_at: string, levies: array<int, string>}>
     */
    public const BUCKETS = [
        ['code' => '202406', 'pay_col' => 4, 'paid_at' => '2024-06-01', 'levies' => [3 => 'okonkwo']],
        ['code' => '202412', 'pay_col' => 6, 'paid_at' => '2024-12-31', 'levies' => []],
        ['code' => '202504', 'pay_col' => 10, 'paid_at' => '2025-04-14', 'levies' => [8 => 'ifeacho', 9 => 'vincent']],
        ['code' => '202505', 'pay_col' => 13, 'paid_at' => '2025-05-31', 'levies' => [12 => 'egolum']],
        ['code' => '202510', 'pay_col' => 16, 'paid_at' => '2025-10-31', 'levies' => [15 => 'nriezedi']],
        ['code' => '202601', 'pay_col' => 19, 'paid_at' => '2026-01-31', 'levies' => [18 => 'okafor']],
        ['code' => '202602', 'pay_col' => 22, 'paid_at' => '2026-02-28', 'levies' => [21 => 'ofoma']],
    ];

    public const OPENING_COL = 2;

    public const NAME_COL = 1;

    /** @var array<string, true> */
    private array $usedEmails = [];

    public function handle(): int
    {
        $path = base_path($this->option('file'));

        if (! file_exists($path)) {
            $this->error("Spreadsheet not found: {$path}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Wipe all members, condolences, levies, payments, arrears and deposits (users are kept) and import the ledger?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $rows = self::readXlsxRows($path);
        $members = self::memberRows($rows);
        $this->info('Member rows in sheet: '.count($members));

        DB::transaction(function () use ($members): void {
            $this->wipeMemberData();

            $condolences = $this->importCondolences();
            $this->importMembers($members);
            $this->importLeviesAndPayments($members, $condolences);
        });

        $this->validateImport($members);

        return self::SUCCESS;
    }

    /**
     * Read the first worksheet of an .xlsx file without extra packages.
     *
     * @return array<int, array<int, int|float|string|null>>
     */
    public static function readXlsxRows(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new \RuntimeException("Cannot open spreadsheet: {$path}");
        }

        $shared = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $dom = new DOMDocument;
            $dom->loadXML($sharedXml);
            foreach ($dom->getElementsByTagName('si') as $si) {
                $text = '';
                foreach ($si->getElementsByTagName('t') as $t) {
                    $text .= $t->textContent;
                }
                $shared[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            throw new \RuntimeException('sheet1.xml missing from spreadsheet.');
        }

        $dom = new DOMDocument;
        $dom->loadXML($sheetXml);

        $rows = [];
        foreach ($dom->getElementsByTagName('row') as $row) {
            $cells = [];
            foreach ($row->getElementsByTagName('c') as $cell) {
                if (! preg_match('/^([A-Z]+)/', $cell->getAttribute('r'), $m)) {
                    continue;
                }
                $col = self::columnIndex($m[1]);
                $type = $cell->getAttribute('t');
                $value = null;
                foreach ($cell->childNodes as $child) {
                    if ($child->nodeName === 'v') {
                        $value = $child->textContent;
                    } elseif ($child->nodeName === 'is') {
                        $text = '';
                        foreach ($child->getElementsByTagName('t') as $t) {
                            $text .= $t->textContent;
                        }
                        $value = $text;
                        $type = 'inlineStr';
                    }
                }
                if ($type === 's' && $value !== null) {
                    $value = $shared[(int) $value] ?? null;
                } elseif ($value !== null && $type !== 'inlineStr' && is_numeric($value)) {
                    $value = str_contains($value, '.') ? (float) $value : (int) $value;
                }
                $cells[$col] = $value;
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }
        $zip->close();

        $width = 0;
        foreach ($rows as $cells) {
            $width = max($width, max(array_keys($cells)));
        }

        return array_map(fn (array $cells): array => array_replace(array_fill(0, $width + 1, null), $cells), $rows);
    }

    public static function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * Member data rows (skips titles, repeated headers and the TOTAL row).
     *
     * @param  array<int, array<int, int|float|string|null>>  $rows
     * @return array<int, array<int, int|float|string|null>>
     */
    public static function memberRows(array $rows): array
    {
        return array_values(array_filter($rows, function (array $row): bool {
            $name = $row[self::NAME_COL] ?? null;
            $sn = $row[0] ?? null;

            return $name !== null && ! in_array(trim((string) $name), ['', 'NAMES', 'TOTAL'], true)
                && strtolower(trim((string) $sn)) !== 's/n';
        }));
    }

    /**
     * @return array{title: string, first_name: string, middle_name: ?string, last_name: string}
     */
    public static function parseMemberName(string $name): array
    {
        $prefixes = [
            'Chief Justice', 'Prince Dr.', 'Chief Dr.', 'Ichie Dr.', 'Chief Barr.', 'Oba Barr.',
            'Prince', 'Chief', 'Oba', 'Ichie', 'Dr.', 'Dr', 'Barr.', 'Barr', 'Justice', 'Alhaji', 'Ide',
        ];

        $title = 'Mr';
        // Trailing ** flags the row in the ledger; kept in notes, not the name.
        $rest = trim(str_replace('**', '', $name));
        foreach ($prefixes as $prefix) {
            if (str_starts_with($rest, $prefix.' ')) {
                $title = $prefix;
                $rest = trim(substr($rest, strlen($prefix)));
                break;
            }
        }

        $parts = preg_split('/\s+/', $rest) ?: [];
        if (count($parts) < 2) {
            throw new \RuntimeException("Cannot parse member name: {$name}");
        }

        return [
            'title' => $title,
            'first_name' => $parts[0],
            'middle_name' => count($parts) > 2 ? implode(' ', array_slice($parts, 1, -1)) : null,
            'last_name' => end($parts),
        ];
    }

    public static function amountCell(int|float|string|null $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-' || $text === '—') {
            return 0.0;
        }

        throw new \RuntimeException("Unexpected amount cell: {$value}");
    }

    public function emailFor(string $firstName, string $lastName): string
    {
        $base = trim(Str::slug($firstName).'.'.Str::slug($lastName), '.');
        $base = $base !== '' && $base !== '.' ? $base : 'member';
        $email = $base.'@nzena-ozo.local';
        $counter = 2;
        while (isset($this->usedEmails[$email]) || Member::where('email', $email)->exists()) {
            $email = $base.$counter.'@nzena-ozo.local';
            $counter++;
        }
        $this->usedEmails[$email] = true;

        return $email;
    }

    private function wipeMemberData(): void
    {
        Payment::query()->delete();
        ArrearPayment::query()->delete();
        Deposit::query()->delete();
        CondolenceLevy::query()->delete();
        Arrear::query()->delete();
        Condolence::query()->delete();
        Member::query()->delete();
    }

    /**
     * @return array<string, Condolence>
     */
    private function importCondolences(): array
    {
        $map = [];
        foreach (self::CONDOLENCES as $def) {
            $deceased = Member::create([
                'title' => $def['title'],
                'first_name' => $def['first_name'],
                'middle_name' => $def['middle_name'],
                'last_name' => $def['last_name'],
                'email' => $def['email'],
                'password' => Hash::make(Str::random(32)),
                'status' => MemberStatus::Deceased->value,
            ]);

            $map[$def['key']] = Condolence::create([
                'deceased_member_id' => $deceased->id,
                'amount_per_member' => $def['amount'],
                'date_announced' => $def['announced'],
                'due_date' => $def['due'],
                'status' => 'open',
                'description' => 'Imported from ozo_2026.xlsx ledger.',
            ]);

            $this->line("Condolence: {$map[$def['key']]->title}");
        }

        return $map;
    }

    /**
     * @param  array<int, array<int, int|float|string|null>>  $members
     * @param  array<string, Condolence>  $condolences
     */
    private function importMembers(array $members): void
    {
        $password = Hash::make('password');
        $now = Carbon::now()->toDateTimeString();
        $chunk = [];

        foreach ($members as $row) {
            $parsed = self::parseMemberName((string) $row[self::NAME_COL]);
            $email = $this->emailFor($parsed['first_name'], $parsed['last_name']);

            $chunk[] = [
                'title' => $parsed['title'],
                'first_name' => $parsed['first_name'],
                'middle_name' => $parsed['middle_name'],
                'last_name' => $parsed['last_name'],
                'email' => $email,
                'password' => $password,
                'status' => MemberStatus::Active->value,
                'opening_arrears' => self::amountCell($row[self::OPENING_COL]),
                'notes' => str_contains((string) $row[self::NAME_COL], '**') ? 'Marked ** in ledger.' : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= 100) {
                Member::insert($chunk);
                $chunk = [];
            }
        }

        if ($chunk !== []) {
            Member::insert($chunk);
        }

        $this->info('Members imported: '.Member::where('status', MemberStatus::Active->value)->count());
    }

    /**
     * @param  array<int, array<int, int|float|string|null>>  $members
     * @param  array<string, Condolence>  $condolences
     */
    private function importLeviesAndPayments(array $members, array $condolences): void
    {
        foreach ($condolences as $condolence) {
            $condolence->generateLevies();
        }

        $membersByName = Member::where('status', MemberStatus::Active->value)->get()->keyBy(fn (Member $m): string => $m->first_name.'|'.$m->middle_name.'|'.$m->last_name);

        $levyCount = 0;
        $paymentCount = 0;
        $depositCount = 0;

        foreach ($members as $row) {
            $parsed = self::parseMemberName((string) $row[self::NAME_COL]);
            $member = $membersByName->get($parsed['first_name'].'|'.$parsed['middle_name'].'|'.$parsed['last_name']);

            if (! $member) {
                throw new \RuntimeException('Member missing after import: '.$row[self::NAME_COL]);
            }

            // Drop levies for blank levy cells (member not levied for that area).
            foreach (self::BUCKETS as $bucket) {
                foreach ($bucket['levies'] as $col => $key) {
                    $cell = $row[$col] ?? null;
                    if ($cell === null || (is_string($cell) && trim($cell) === '') || $cell === '-' || (float) $cell == 0) {
                        CondolenceLevy::where('condolence_id', $condolences[$key]->id)
                            ->where('member_id', $member->id)
                            ->delete();
                    }
                }
            }

            // Opening balance is the oldest debt; bucket payments eat it first.
            $opening = max(0, (float) $member->opening_arrears);

            // Running outstanding for per-deposit before/after snapshots.
            $running = $opening + (float) CondolenceLevy::where('member_id', $member->id)->sum('amount_expected');

            foreach (self::BUCKETS as $bucket) {
                $paid = self::amountCell($row[$bucket['pay_col']] ?? null);

                if ($paid <= 0) {
                    continue;
                }

                // Every levy announced up to the payment date, oldest first.
                $targets = CondolenceLevy::where('condolence_levies.member_id', $member->id)
                    ->whereIn('condolence_levies.status', [LevyStatus::Unpaid->value, LevyStatus::Partial->value])
                    ->join('condolences', 'condolences.id', '=', 'condolence_levies.condolence_id')
                    ->where('condolences.date_announced', '<=', $bucket['paid_at'])
                    ->orderBy('condolences.date_announced')
                    ->orderBy('condolence_levies.id')
                    ->select('condolence_levies.*')
                    ->get();

                $remaining = $paid;
                $splits = [];

                $openingShare = min($remaining, $opening);
                $remaining -= $openingShare;
                $opening -= $openingShare;

                foreach ($targets as $levy) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $due = max(0, (float) $levy->amount_expected - (float) $levy->amount_paid);
                    $share = min($remaining, $due);
                    if ($share > 0) {
                        $splits[] = ['levy' => $levy, 'share' => $share];
                        $remaining -= $share;
                    }
                }

                // Anything beyond all debt so far becomes credit (matches sheet running totals).
                $creditShare = max(0, $remaining);

                $reference = 'LEDGER-'.$bucket['code'].'-M'.$member->id;
                $before = $running;
                $running = max(0, $running - $paid);

                Deposit::create([
                    'member_id' => $member->id,
                    'amount' => $paid,
                    'paid_at' => $bucket['paid_at'],
                    'payment_method' => PaymentMethod::Other->value,
                    'reference' => $reference,
                    'reason' => ArrearReason::Condolence->value,
                    'opening_applied' => $openingShare,
                    'credit_added' => $creditShare,
                    'outstanding_before' => $before,
                    'outstanding_after' => $running,
                    'recorded_by' => null,
                    'notes' => 'Imported from ozo_2026.xlsx ledger.',
                ]);
                $depositCount++;

                $splitCount = count($splits) + ($openingShare > 0 ? 1 : 0);
                $splitIndex = 0;
                foreach ($splits as $split) {
                    $splitIndex++;
                    Payment::create([
                        'condolence_levy_id' => $split['levy']->id,
                        'condolence_id' => $split['levy']->condolence_id,
                        'member_id' => $member->id,
                        'amount' => $split['share'],
                        'paid_at' => $bucket['paid_at'],
                        'payment_method' => PaymentMethod::Other->value,
                        'reference' => $reference,
                        'reason' => ArrearReason::Condolence->value,
                        'notes' => "Imported split {$splitIndex} of {$splitCount} — LEDGER {$bucket['code']}.",
                    ]);
                    $paymentCount++;
                }

                if ($openingShare > 0) {
                    $member->decrement('opening_arrears', $openingShare);
                }

                if ($creditShare > 0) {
                    Member::query()->whereKey($member->id)->increment('credit_balance', $creditShare);
                }

                $member->refresh();
                $opening = max(0, (float) $member->opening_arrears);
            }

            $levyCount = CondolenceLevy::count();
        }

        $this->info("Levies kept: {$levyCount}, payments: {$paymentCount}, deposits: {$depositCount}.");
    }

    /**
     * @param  array<int, array<int, int|float|string|null>>  $members
     */
    private function validateImport(array $members): void
    {
        $bad = 0;
        foreach ($members as $row) {
            $parsed = self::parseMemberName((string) $row[self::NAME_COL]);
            $query = Member::where('first_name', $parsed['first_name'])->where('last_name', $parsed['last_name']);
            $member = $parsed['middle_name'] !== null
                ? $query->where('middle_name', $parsed['middle_name'])->first()
                : $query->whereNull('middle_name')->first();

            if (! $member) {
                $this->error('Missing member: '.$row[self::NAME_COL]);
                $bad++;

                continue;
            }

            // Sheet final outstanding = U + V - W (cols 20, 21, 22).
            // Negative sheet balances are overpayments: our books hold them
            // as credit, so compare net of credit.
            $expected = self::amountCell($row[20] ?? null)
                + self::amountCell($row[21] ?? null)
                - self::amountCell($row[22] ?? null);

            $member = $member->refresh();
            $actual = $member->accountTotals()['outstanding'] - (float) $member->credit_balance;

            if (abs($expected - $actual) > 0.01) {
                $this->error("Mismatch {$member->full_name}: sheet {$expected}, db {$actual}");
                $bad++;
            }
        }

        if ($bad === 0) {
            $this->info('All '.count($members).' member balances reconcile with the sheet.');
        } else {
            $this->error("Reconciliation problems: {$bad}.");
        }
    }
}
