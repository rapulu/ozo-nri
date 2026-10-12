<?php

namespace App\Console\Commands;

use App\Models\Deposit;
use App\Models\DepositAllocation;
use App\Models\Payment;
use Illuminate\Console\Command;

class LinkDepositAllocations extends Command
{
    protected $signature = 'deposits:link-allocations';

    protected $description = 'Link existing deposits to the condolence levies their splits settled (idempotent).';

    public function handle(): int
    {
        $linked = 0;

        foreach (Deposit::orderBy('id')->cursor() as $deposit) {
            if ($deposit->reference) {
                $splits = Payment::where('member_id', $deposit->member_id)
                    ->where('reference', $deposit->reference)
                    ->get()
                    ->groupBy('condolence_levy_id');
            } else {
                $splits = Payment::where('member_id', $deposit->member_id)
                    ->where('amount', $deposit->amount)
                    ->whereDate('paid_at', $deposit->paid_at)
                    ->get()
                    ->groupBy('condolence_levy_id');
            }

            foreach ($splits as $levyId => $rows) {
                $first = $rows->first();
                DepositAllocation::firstOrCreate(
                    [
                        'deposit_id' => $deposit->id,
                        'condolence_levy_id' => $levyId,
                    ],
                    [
                        'condolence_id' => $first->condolence_id,
                        'amount' => $rows->sum(fn ($p): float => (float) $p->amount),
                    ]
                );
                $linked++;
            }
        }

        $this->info("Allocations linked: {$linked}.");

        return self::SUCCESS;
    }
}
