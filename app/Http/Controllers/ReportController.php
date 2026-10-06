<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Member;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Sized to fill whole pages exactly (30 matrix rows per A3 landscape
     * page) so stitched sections never leave half-empty pages behind.
     */
    public const MATRIX_PDF_CHUNK_SIZE = 90;

    public const AREA_PDF_CHUNK_SIZE = 200;

    /**
     * A single-area record stays in one table, so "all members at once"
     * renders fine up to this size (993 rows peak at ~475M).
     */
    public const AREA_PDF_ALL_LIMIT = 1500;

    /**
     * The lean fixed-layout table renders ~1,000 members in one pass
     * (~800M peak), so "all members" stays a single continuous document
     * up to this size. Beyond it, chunks are rendered and merged.
     */
    public const MATRIX_PDF_ALL_LIMIT = 1100;

    /**
     * Build "Members X–Y" range options for download dialogs.
     *
     * @return array<string, string>
     */
    public static function memberRangeOptions(int $total, int $chunkSize, ?int $allLimit = null): array
    {
        $options = [];

        if ($total <= $chunkSize || ($allLimit !== null && $total <= $allLimit)) {
            $options['all'] = "All members ({$total})";
        }

        if ($total > $chunkSize) {
            for ($offset = 0; $offset < $total; $offset += $chunkSize) {
                $from = $offset + 1;
                $to = min($offset + $chunkSize, $total);
                $options["{$offset}:{$chunkSize}"] = "Members {$from}–{$to} of {$total}";
            }
        }

        return $options;
    }

    /**
     * @return array{offset: int, limit: ?int}
     */
    public static function parseRange(?string $range): array
    {
        if ($range === null || $range === 'all' || ! str_contains($range, ':')) {
            return ['offset' => 0, 'limit' => null];
        }

        [$offset, $limit] = explode(':', $range, 2);

        return ['offset' => max(0, (int) $offset), 'limit' => max(1, (int) $limit)];
    }

    private function bumpPdfLimits(): void
    {
        // Large member tables are heavy for Dompdf.
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }
        ini_set('memory_limit', '1024M');
    }

    private function rangeLabel(int $offset, ?int $limit, int $total, int $shown): string
    {
        if ($limit === null) {
            return "All {$total} members";
        }

        return 'Members '.($offset + 1).'–'.($offset + $shown)." of {$total}";
    }

    public function condolence(Request $request, Condolence $condolence): Response
    {
        $this->bumpPdfLimits();

        $condolence->load([
            'deceasedMember',
            'levies.member',
            'levies.payments',
        ]);

        $total = $condolence->levies()->count();
        ['offset' => $offset, 'limit' => $limit] = self::parseRange($request->query('members'));

        $leviesQuery = $condolence->levies()
            ->with(['member', 'payments'])
            ->join('members', 'members.id', '=', 'condolence_levies.member_id')
            ->orderBy('members.last_name')
            ->orderBy('members.first_name')
            ->select('condolence_levies.*');

        if ($limit !== null) {
            $leviesQuery->offset($offset)->limit($limit);
        }

        $levies = $leviesQuery->get();

        $expected = (float) $condolence->levies()->sum('amount_expected');
        $collected = (float) $condolence->levies()->sum('amount_paid');

        $pdf = Pdf::loadView('reports.condolence', [
            'condolence' => $condolence,
            'levies' => $levies,
            'expected' => $expected,
            'collected' => $collected,
            'outstanding' => $expected - $collected,
            'rangeLabel' => $this->rangeLabel($offset, $limit, $total, $levies->count()),
        ])->setPaper('a4', 'landscape');

        $suffix = $limit === null ? 'all-members' : 'members-'.($offset + 1).'-'.($offset + $levies->count());

        return $pdf->download('condolence-'.$condolence->id.'-record-'.$suffix.'.pdf');
    }

    public function condolenceCsv(Condolence $condolence): StreamedResponse
    {
        $condolence->load('deceasedMember');

        $filename = 'condolence-'.$condolence->id.'-record-all-members.csv';

        return response()->streamDownload(function () use ($condolence): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['NZE NA OZO ASSOCIATION – '.$condolence->title], escape: '\\');
            fputcsv($out, ['Deceased', $condolence->deceasedMember?->full_name ?? '—', 'Amount per member', (float) $condolence->amount_per_member, 'Announced', $condolence->date_announced?->format('d/m/Y')], escape: '\\');
            fputcsv($out, ['S/N', 'Member', 'Phone', 'Arrears', 'Received', 'Outstanding', 'Status', 'Date(s) paid'], escape: '\\');

            $i = 0;
            $condolence->levies()
                ->with(['member', 'payments'])
                ->join('members', 'members.id', '=', 'condolence_levies.member_id')
                ->orderBy('members.last_name')
                ->orderBy('members.first_name')
                ->select('condolence_levies.*')
                ->chunk(200, function ($levies) use ($out, &$i): void {
                    foreach ($levies as $levy) {
                        $i++;
                        fputcsv($out, [
                            $i,
                            $levy->member?->full_name ?? '—',
                            $levy->member?->phone ?? '',
                            (float) $levy->amount_expected,
                            (float) $levy->amount_paid,
                            (float) $levy->amount_expected - (float) $levy->amount_paid,
                            $levy->status,
                            $levy->payments->pluck('paid_at')->filter()->map(fn ($d) => $d->format('d/m/Y'))->unique()->join(', '),
                        ], escape: '\\');
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function memberStatement(Member $member): Response
    {
        $member->load(['levies.condolence', 'levies.payments', 'payments']);

        $levies = $member->levies()
            ->with(['condolence', 'payments'])
            ->join('condolences', 'condolences.id', '=', 'condolence_levies.condolence_id')
            ->orderBy('condolences.date_announced')
            ->select('condolence_levies.*')
            ->get();

        $expected = (float) $member->levies()->sum('amount_expected');
        $paid = (float) $member->levies()->sum('amount_paid');

        $pdf = Pdf::loadView('reports.member-statement', [
            'member' => $member,
            'levies' => $levies,
            'expected' => $expected,
            'paid' => $paid,
            'outstanding' => $expected - $paid,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('member-'.$member->id.'-statement.pdf');
    }

    public function matrix(Request $request): Response
    {
        $this->bumpPdfLimits();

        $statusFilter = $request->query('status', 'all');

        $condolencesQuery = Condolence::with('deceasedMember')->orderBy('date_announced');

        if ($statusFilter === 'open') {
            $condolencesQuery->where('status', 'open');
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Condolence> $condolences */
        $condolences = $condolencesQuery->get();

        $totalMembers = Member::where('status', MemberStatus::Active->value)->count();
        ['offset' => $offset, 'limit' => $limit] = self::parseRange($request->query('members'));

        $suffix = $statusFilter === 'open' ? 'open' : 'all';

        // The lean table renders ~1,100 members in one continuous pass.
        // Beyond that, safe chunks are rendered and merged into one file.
        if ($limit === null && $totalMembers > self::MATRIX_PDF_ALL_LIMIT) {
            $merged = $this->renderMergedMatrix($condolences, $totalMembers, $statusFilter);

            return response($merged, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="condolence-levies-matrix-'.$suffix.'-all-members.pdf"',
            ]);
        }

        $membersQuery = Member::where('status', MemberStatus::Active->value)
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($limit !== null) {
            $membersQuery->offset($offset)->limit($limit);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Member> $members */
        $members = $membersQuery->get();

        $chunk = $this->renderMatrixChunk(
            $condolences,
            $members,
            $this->rangeLabel($offset, $limit, $totalMembers, $members->count()),
            $statusFilter,
            null,
            0.0,
            0.0,
            $offset,
            true,
            true,
            true,
            $totalMembers,
        );

        $suffix .= '-'.($limit === null ? 'all-members' : 'members-'.($offset + 1).'-'.($offset + $members->count()));

        return response($chunk, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="condolence-levies-matrix-'.$suffix.'.pdf"',
        ]);
    }

    /**
     * Render one matrix chunk (a safe row count) to PDF bytes.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Condolence>  $condolences
     * @param  \Illuminate\Database\Eloquent\Collection<int, Member>  $members
     * @param  array<string, array{expected: float, collected: float, outstanding: float}>|null  $globalPerCondolence
     */
    private function renderMatrixChunk($condolences, $members, string $rangeLabel, string $statusFilter, ?array $globalPerCondolence = null, float $grandExpected = 0.0, float $grandCollected = 0.0, int $startIndex = 0, bool $showTotals = true, bool $showHeader = true, bool $showSignature = true, int $totalMembers = 0): string
    {
        $condolenceIds = $condolences->pluck('id')->all();
        $memberIds = $members->pluck('id')->all();

        /** @var Collection<string, CondolenceLevy> $levyMap */
        $levyMap = CondolenceLevy::with('payments')
            ->whereIn('condolence_id', $condolenceIds)
            ->whereIn('member_id', $memberIds)
            ->get()
            ->keyBy(fn (CondolenceLevy $levy): string => $levy->member_id.'_'.$levy->condolence_id);

        $perCondolence = [];
        foreach ($condolences as $condolence) {
            $expected = 0.0;
            $collected = 0.0;
            foreach ($members as $member) {
                $levy = $levyMap->get($member->id.'_'.$condolence->id);
                if ($levy) {
                    $expected += (float) $levy->amount_expected;
                    $collected += (float) $levy->amount_paid;
                }
            }
            $perCondolence[$condolence->id] = [
                'expected' => $expected,
                'collected' => $collected,
                'outstanding' => $expected - $collected,
            ];
        }

        $perMember = [];
        foreach ($members as $member) {
            $expected = 0.0;
            $paid = 0.0;
            foreach ($condolences as $condolence) {
                $levy = $levyMap->get($member->id.'_'.$condolence->id);
                if ($levy) {
                    $expected += (float) $levy->amount_expected;
                    $paid += (float) $levy->amount_paid;
                }
            }
            $perMember[$member->id] = [
                'expected' => $expected,
                'paid' => $paid,
                'outstanding' => $expected - $paid,
            ];
        }

        if ($globalPerCondolence !== null) {
            $perCondolence = $globalPerCondolence;
        } else {
            $grandExpected = array_sum(array_column($perCondolence, 'expected'));
            $grandCollected = array_sum(array_column($perCondolence, 'collected'));
        }

        $pdf = Pdf::loadView('reports.condolences-matrix', [
            'condolences' => $condolences,
            'members' => $members,
            'levyMap' => $levyMap,
            'perCondolence' => $perCondolence,
            'perMember' => $perMember,
            'grandExpected' => $grandExpected,
            'grandCollected' => $grandCollected,
            'grandOutstanding' => $grandExpected - $grandCollected,
            'statusFilter' => $statusFilter,
            'rangeLabel' => $rangeLabel,
            'startIndex' => $startIndex,
            'showTotals' => $showTotals,
            'showHeader' => $showHeader,
            'showSignature' => $showSignature,
            'totalMembers' => $totalMembers > 0 ? $totalMembers : $members->count(),
        ])->setPaper('a3', 'landscape');

        $bytes = $pdf->output();
        unset($pdf, $levyMap);
        gc_collect_cycles();

        return $bytes;
    }

    /**
     * Render every member chunk and merge them into one PDF file.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Condolence>  $condolences
     */
    private function renderMergedMatrix($condolences, int $totalMembers, string $statusFilter): string
    {
        $condolenceIds = $condolences->pluck('id')->all();

        $global = CondolenceLevy::query()
            ->whereIn('condolence_id', $condolenceIds)
            ->selectRaw('condolence_id, SUM(amount_expected) as expected, SUM(amount_paid) as collected')
            ->groupBy('condolence_id')
            ->get()
            ->keyBy('condolence_id');

        $globalPerCondolence = [];
        foreach ($condolences as $condolence) {
            $row = $global->get($condolence->id);
            $expected = $row ? (float) $row->expected : 0.0;
            $collected = $row ? (float) $row->collected : 0.0;
            $globalPerCondolence[$condolence->id] = [
                'expected' => $expected,
                'collected' => $collected,
                'outstanding' => $expected - $collected,
            ];
        }

        $grandExpected = array_sum(array_column($globalPerCondolence, 'expected'));
        $grandCollected = array_sum(array_column($globalPerCondolence, 'collected'));

        $files = [];
        try {
            $size = self::MATRIX_PDF_CHUNK_SIZE;
            for ($offset = 0; $offset < $totalMembers; $offset += $size) {
                $members = Member::where('status', MemberStatus::Active->value)
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->offset($offset)
                    ->limit($size)
                    ->get();

                $bytes = $this->renderMatrixChunk(
                    $condolences,
                    $members,
                    'Members '.($offset + 1).'–'.($offset + $members->count())." of {$totalMembers}",
                    $statusFilter,
                    $globalPerCondolence,
                    $grandExpected,
                    $grandCollected,
                    $offset,
                    $offset + $size >= $totalMembers,
                    $offset === 0,
                    $offset + $size >= $totalMembers,
                    $totalMembers,
                );

                $path = tempnam(sys_get_temp_dir(), 'matrix').'.pdf';
                file_put_contents($path, $bytes);
                unset($bytes);
                $files[] = $path;
            }

            $merger = new Fpdi;
            foreach ($files as $file) {
                $pageCount = $merger->setSourceFile($file);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $template = $merger->importPage($i);
                    $dimensions = $merger->getTemplateSize($template);
                    $merger->AddPage($dimensions['orientation'], [$dimensions['width'], $dimensions['height']]);
                    $merger->useTemplate($template);
                }
            }

            return $merger->Output('S');
        } finally {
            foreach ($files as $file) {
                if (is_string($file) && file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    public function matrixCsv(Request $request): StreamedResponse
    {
        $statusFilter = $request->query('status', 'all');

        $condolencesQuery = Condolence::with('deceasedMember')->orderBy('date_announced');

        if ($statusFilter === 'open') {
            $condolencesQuery->where('status', 'open');
        }

        $condolences = $condolencesQuery->get();
        $filename = 'condolence-levies-matrix-'.($statusFilter === 'open' ? 'open' : 'all').'.csv';

        return response()->streamDownload(function () use ($condolences): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['NZE NA OZO ASSOCIATION – Condolence Levies Matrix (arrears | received | outstanding per area)'], escape: '\\');

            $header = ['S/N', 'Member', 'Phone'];
            foreach ($condolences as $c) {
                $label = ($c->deceasedMember?->full_name ?? $c->title).' (₦'.number_format((float) $c->amount_per_member, 0).', '.$c->date_announced?->format('d/m/y').')';
                $header[] = $label.' – Arrears';
                $header[] = $label.' – Arrears date';
                $header[] = $label.' – Received';
                $header[] = $label.' – Received dates';
                $header[] = $label.' – Outstanding';
                $header[] = $label.' – Due date';
            }
            $header[] = 'Tot. arrears';
            $header[] = 'Tot. received';
            $header[] = 'Tot. outstanding';
            fputcsv($out, $header, escape: '\\');

            $condolenceIds = $condolences->pluck('id')->all();
            $i = 0;
            Member::where('status', MemberStatus::Active->value)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->chunk(200, function ($members) use ($out, $condolences, $condolenceIds, &$i): void {
                    $levyMap = CondolenceLevy::with('payments')
                        ->whereIn('condolence_id', $condolenceIds)
                        ->whereIn('member_id', $members->pluck('id')->all())
                        ->get()
                        ->keyBy(fn (CondolenceLevy $levy): string => $levy->member_id.'_'.$levy->condolence_id);

                    foreach ($members as $m) {
                        $i++;
                        $row = [$i, $m->full_name, $m->phone ?? ''];
                        $totExpected = 0.0;
                        $totPaid = 0.0;
                        foreach ($condolences as $c) {
                            $levy = $levyMap->get($m->id.'_'.$c->id);
                            if (! $levy) {
                                array_push($row, '', '', '', '', '', '');

                                continue;
                            }
                            $expected = (float) $levy->amount_expected;
                            $paid = (float) $levy->amount_paid;
                            $totExpected += $expected;
                            $totPaid += $paid;
                            $row[] = $expected;
                            $row[] = $c->date_announced?->format('d/m/Y') ?? '';
                            $row[] = $paid;
                            $row[] = $levy->payments->pluck('paid_at')->filter()->map(fn ($d) => $d->format('d/m/Y'))->unique()->join(', ');
                            $row[] = $expected - $paid;
                            $row[] = $c->due_date?->format('d/m/Y') ?? '';
                        }
                        $row[] = $totExpected;
                        $row[] = $totPaid;
                        $row[] = $totExpected - $totPaid;
                        fputcsv($out, $row, escape: '\\');
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
