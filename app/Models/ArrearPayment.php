<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
