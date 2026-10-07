<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    protected $fillable = [
        'member_id',
        'amount',
        'paid_at',
        'payment_method',
        'reference',
        'reason',
        'opening_applied',
        'recorded_by',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'opening_applied' => 'decimal:2',
            'paid_at' => 'date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Rebuild bulk deposit rows from payments recorded before deposits existed.
     *
     * Rows sharing member + reference + date are one handover (one deposit
     * with the summed amount); rows without a reference become one deposit
     * each since they cannot be grouped safely.
     */
    public static function backfillFromPayments(): int
    {
        $created = 0;

        $groupKey = fn ($p): string => $p->member_id.'|'.($p->paid_at?->format('Y-m-d') ?? '').'|'.($p->reference ?? '');

        $makeDeposit = function ($rows) use (&$created): void {
            $first = $rows->first();

            Deposit::create([
                'member_id' => $first->member_id,
                'amount' => $rows->sum(fn ($p): float => (float) $p->amount),
                'paid_at' => $first->paid_at,
                'payment_method' => $first->payment_method,
                'reference' => $first->reference,
                'reason' => $first->reason ?? 'condolence',
                'recorded_by' => $first->recorded_by,
                'notes' => $rows->count() > 1 ? 'Backfilled: '.$rows->count().' split rows, bulk total' : $first->notes,
            ]);
            $created++;
        };

        foreach (['payments' => Payment::class, 'arrear_payments' => ArrearPayment::class] as $table => $model) {
            $model::whereNotNull('reference')->orderBy('id')->chunk(200, function ($rows) use ($groupKey, $makeDeposit): void {
                foreach ($rows->groupBy($groupKey) as $group) {
                    $makeDeposit($group);
                }
            });

            $model::whereNull('reference')->orderBy('id')->chunk(200, function ($rows) use ($makeDeposit): void {
                foreach ($rows as $payment) {
                    $makeDeposit(collect([$payment]));
                }
            });
        }

        return $created;
    }
}
