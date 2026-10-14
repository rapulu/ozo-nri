<?php

namespace App\Console\Commands;

use App\Enums\LevyStatus;
use App\Models\CondolenceLevy;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class LedgerAudit extends Command
{
    protected $signature = 'ledger:audit';

    protected $description = 'Verify ledger figures reconcile: paid vs payment rows, statuses, negatives, and deposits vs splits.';

    public function handle(): int
    {
        $problems = 0;

        $problems += $this->auditObligations('levies', CondolenceLevy::query());
        $problems += $this->auditDeposits();

        // Member credit held must equal credit added minus credit consumed.
        $creditHeld = (float) Member::sum('credit_balance');
        $creditAdded = (float) Deposit::sum('credit_added');
        $creditConsumed = (float) Payment::where('payment_method', 'credit')->sum('amount');

        if (abs($creditHeld - ($creditAdded - $creditConsumed)) > 0.01) {
            $this->error("Credit held {$creditHeld} != added {$creditAdded} minus consumed {$creditConsumed}.");
            $problems++;
        }

        if ($problems === 0) {
            $this->info('Ledger reconciles: levies, deposits and credit all match.');
        } else {
            $this->error("Found {$problems} problem(s).");
        }

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Every deposit must equal its linked splits + opening applied + credit kept.
     * Split rows from direct entries or credit auto-debits carry no deposit
     * and are covered by the obligation checks above instead.
     */
    private function auditDeposits(): int
    {
        $problems = 0;
        $shown = 0;
        $total = Deposit::count();

        foreach (Deposit::orderBy('id')->cursor() as $deposit) {
            if ($deposit->reference) {
                $splits = (float) Payment::where('member_id', $deposit->member_id)
                    ->where('reference', $deposit->reference)->sum('amount');
            } else {
                $splits = (float) Payment::where('member_id', $deposit->member_id)
                    ->where('amount', $deposit->amount)
                    ->whereDate('paid_at', $deposit->paid_at)->sum('amount');
            }

            $accounted = $splits + (float) $deposit->opening_applied + (float) $deposit->credit_added;

            if (abs((float) $deposit->amount - $accounted) > 0.01) {
                if ($shown < 5) {
                    $this->error("Deposit #{$deposit->id} ({$deposit->reference}): amount {$deposit->amount} != splits {$splits} + opening {$deposit->opening_applied} + credit {$deposit->credit_added}.");
                }
                $problems++;
                $shown++;
            }
        }

        $this->info("Deposits checked: {$total}.");

        return $problems;
    }

    /**
     * @param  Builder  $query
     */
    private function auditObligations(string $label, $query): int
    {
        $problems = 0;
        $total = (clone $query)->count();
        $shown = 0;

        foreach ((clone $query)->cursor() as $row) {
            $paid = (float) $row->payments()->sum('amount');
            $expected = (float) $row->amount_expected;

            if (abs((float) $row->amount_paid - $paid) > 0.01) {
                if ($shown < 5) {
                    $this->error(ucfirst($label)." #{$row->id}: paid {$row->amount_paid} != payments sum {$paid}.");
                }
                $problems++;
                $shown++;
            }

            $want = $row->status === LevyStatus::Exempted->value
                ? LevyStatus::Exempted->value
                : ($paid <= 0 ? LevyStatus::Unpaid->value : ($paid < $expected ? LevyStatus::Partial->value : LevyStatus::Paid->value));

            if ($row->status !== $want) {
                if ($shown < 5) {
                    $this->error(ucfirst($label)." #{$row->id}: status {$row->status} should be {$want}.");
                }
                $problems++;
                $shown++;
            }

            if ((float) $row->amount_paid - $expected > 0.01) {
                if ($shown < 5) {
                    $this->error(ucfirst($label)." #{$row->id}: negative balance (paid {$row->amount_paid} > expected {$expected}).");
                }
                $problems++;
                $shown++;
            }
        }

        $this->info(ucfirst($label)." checked: {$total}.");

        return $problems;
    }
}
