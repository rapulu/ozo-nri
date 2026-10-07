<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ArrearPayment extends Model
{
    protected $fillable = [
        'arrear_id',
        'member_id',
        'amount',
        'paid_at',
        'payment_method',
        'reference',
        'reason',
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
            'paid_at' => 'date',
        ];
    }

    /** @return BelongsTo<Arrear, $this> */
    public function arrear(): BelongsTo
    {
        return $this->belongsTo(Arrear::class);
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

    protected static function booted(): void
    {
        static::saving(function (ArrearPayment $payment): void {
            $arrear = Arrear::find($payment->arrear_id);

            if (! $arrear) {
                return;
            }

            $othersPaid = (float) $arrear->payments()
                ->when($payment->exists, fn ($query) => $query->where('id', '!=', $payment->id))
                ->sum('amount');
            $balance = max(0, (float) $arrear->amount_expected - $othersPaid);

            if ($balance <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nothing is owed on this arrear.',
                ]);
            }

            // Anything above the balance becomes member credit instead of debt.
            if ((float) $payment->amount > $balance) {
                $excess = (float) $payment->amount - $balance;
                $payment->amount = $balance;
                Member::query()->whereKey($arrear->member_id)->increment('credit_balance', $excess);
            }
        });

        $recalculate = function (ArrearPayment $payment): void {
            $arrear = $payment->arrear()->first() ?? Arrear::find($payment->arrear_id);

            if ($arrear) {
                $arrear->recalculate();
            }
        };

        static::created($recalculate);
        static::updated($recalculate);
        static::deleted($recalculate);
    }
}
