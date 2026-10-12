<?php

namespace App\Models;

use App\Enums\LevyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /** @return BelongsToMany<Deposit, $this> */
    public function deposits(): BelongsToMany
    {
        return $this->belongsToMany(Deposit::class, 'deposit_allocations', 'condolence_levy_id', 'deposit_id')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function getBalanceAttribute(): float
    {
        return (float) $this->amount_expected - (float) $this->amount_paid;
    }

    /**
     * Running-balance chain, mirroring the ledger: arrears carried into this
     * condolence = opening balance still standing plus older levies minus
     * what has been paid against them. Live figures, so the last row always
     * equals the member's current outstanding.
     */
    public function carriedArrears(): float
    {
        $announced = $this->condolence->date_announced?->format('Y-m-d') ?? '9999-12-31';
        $memberId = $this->member_id;

        $olderIds = CondolenceLevy::where('member_id', $memberId)
            ->join('condolences', 'condolences.id', '=', 'condolence_levies.condolence_id')
            ->where(function ($query) use ($announced): void {
                $query->whereDate('condolences.date_announced', '<', $announced)
                    ->orWhere(function ($query) use ($announced): void {
                        $query->whereDate('condolences.date_announced', $announced)
                            ->where('condolence_levies.id', '<', $this->id);
                    });
            })
            ->pluck('condolence_levies.id');

        $opening = max(0, (float) $this->member->opening_arrears);
        $olderExpected = (float) CondolenceLevy::whereIn('id', $olderIds)->sum('amount_expected');
        $paidOlder = (float) Payment::where('member_id', $memberId)
            ->whereIn('condolence_levy_id', $olderIds)
            ->sum('amount');

        return max(0, round($opening + $olderExpected - $paidOlder, 2));
    }

    /**
     * New outstanding after this condolence: arrears + levy − received.
     */
    public function runningOutstanding(): float
    {
        return max(0, round(
            $this->carriedArrears() + (float) $this->amount_expected - (float) $this->payments()->sum('amount'),
            2
        ));
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
