<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepositAllocation extends Model
{
    protected $fillable = [
        'deposit_id',
        'condolence_levy_id',
        'condolence_id',
        'amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
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
}
