<?php

namespace App\Models;

use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use Database\Factories\CondolenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Condolence extends Model
{
    /** @use HasFactory<CondolenceFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'deceased_member_id',
        'amount_per_member',
        'date_announced',
        'due_date',
        'status',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_per_member' => 'decimal:2',
            'date_announced' => 'date',
            'due_date' => 'date',
        ];
    }

    /** @return BelongsTo<Member, $this> */
    public function deceasedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'deceased_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CondolenceLevy, $this> */
    public function levies(): HasMany
    {
        return $this->hasMany(CondolenceLevy::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Condolence $condolence): void {
            if ($condolence->deceased_member_id === null) {
                throw ValidationException::withMessages([
                    'deceased_member_id' => 'A condolence must be linked to a deceased member.',
                ]);
            }

            $member = Member::find($condolence->deceased_member_id);

            if (! $member || $member->status !== MemberStatus::Deceased->value) {
                throw ValidationException::withMessages([
                    'deceased_member_id' => 'A condolence can only be created for a member whose status is deceased.',
                ]);
            }

            $duplicate = static::query()
                ->where('deceased_member_id', $condolence->deceased_member_id)
                ->when($condolence->exists, fn ($query) => $query->where('id', '!=', $condolence->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'deceased_member_id' => 'A condolence already exists for this deceased member.',
                ]);
            }

            $condolence->title = 'Condolence levy – '.$member->full_name;
        });
    }

    /**
     * Generate levy obligations for every active member except the deceased.
     */
    public function generateLevies(): void
    {
        $memberIds = Member::query()
            ->where('status', MemberStatus::Active->value)
            ->where('id', '!=', $this->deceased_member_id)
            ->pluck('id');

        foreach ($memberIds as $memberId) {
            CondolenceLevy::firstOrCreate(
                [
                    'condolence_id' => $this->id,
                    'member_id' => $memberId,
                ],
                [
                    'amount_expected' => $this->amount_per_member,
                    'amount_paid' => 0,
                    'status' => LevyStatus::Unpaid->value,
                ]
            );
        }

        // Members holding credit have it eaten by the new levies automatically.
        Member::query()
            ->whereIn('id', $memberIds)
            ->where('credit_balance', '>', 0)
            ->each(fn (Member $member): float => $member->applyCredit());
    }

    public function totalExpected(): float
    {
        return (float) $this->levies()->sum('amount_expected');
    }

    public function totalCollected(): float
    {
        return (float) $this->levies()->sum('amount_paid');
    }

    public function totalOutstanding(): float
    {
        return $this->totalExpected() - $this->totalCollected();
    }
}
