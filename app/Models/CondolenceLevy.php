<?php

namespace App\Models;

use App\Enums\LevyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CondolenceLevy extends Model
{
    protected $fillable = [
        'condolence_id',
        'member_id',
        'amount_expected',
        'amount_paid',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_expected' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
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

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'condolence_levy_id');
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
