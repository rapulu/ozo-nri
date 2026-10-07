<?php

namespace App\Models;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Arrear extends Model
{
    protected $attributes = [
        'amount_expected' => 0,
        'amount_paid' => 0,
        'status' => LevyStatus::Unpaid->value,
    ];

    protected $fillable = [
        'member_id',
        'reason',
        'title',
        'description',
        'amount_expected',
        'amount_paid',
        'status',
        'due_date',
        'condolence_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_expected' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Arrear $arrear): void {
            $arrear->member->applyCredit();
        });

        static::saving(function (Arrear $arrear): void {
            if ($arrear->reason === ArrearReason::Condolence->value && $arrear->condolence_id === null) {
                throw ValidationException::withMessages([
                    'condolence_id' => 'Pick the condolence this arrear is for.',
                ]);
            }
        });
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<Condolence, $this> */
    public function condolence(): BelongsTo
    {
        return $this->belongsTo(Condolence::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<ArrearPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(ArrearPayment::class);
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount_expected - (float) $this->amount_paid;
    }

    public function recalculate(): void
    {
        if ($this->status === LevyStatus::Exempted->value) {
            return;
        }

        $paid = (float) $this->payments()->sum('amount');
        $expected = (float) $this->amount_expected;

        $this->amount_paid = $paid;

        if ($paid <= 0) {
            $this->status = LevyStatus::Unpaid->value;
        } elseif ($paid < $expected) {
            $this->status = LevyStatus::Partial->value;
        } else {
            $this->status = LevyStatus::Paid->value;
        }

        $this->saveQuietly();
    }
}
