<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'condolence_levy_id',
        'condolence_id',
        'member_id',
        'amount',
        'paid_at',
        'payment_method',
        'reference',
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

    /** @return BelongsTo<CondolenceLevy, $this> */
    public function levy(): BelongsTo
    {
        return $this->belongsTo(CondolenceLevy::class, 'condolence_levy_id');
    }

    /** @return BelongsTo<Condolence, $this> */
    public function condolence(): BelongsTo
    {
        return $this->belongsTo(Condolence::class);
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
        $recalculate = function (Payment $payment): void {
            $levy = $payment->levy()->first() ?? CondolenceLevy::find($payment->condolence_levy_id);

            if ($levy) {
                $levy->recalculate();
            }
        };

        static::created($recalculate);
        static::updated($recalculate);
        static::deleted($recalculate);
    }
}
