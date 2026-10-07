<?php

namespace App\Models;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use Database\Factories\MemberFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;

class Member extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'phone',
        'address',
        'date_joined',
        'photo_path',
        'status',
        'opening_arrears',
        'notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected $attributes = [
        'opening_arrears' => 0,
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'date_joined' => 'date',
            'opening_arrears' => 'decimal:2',
            'credit_balance' => 'decimal:2',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'member') {
            return false;
        }

        return $this->status === MemberStatus::Active->value;
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->title,
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    public function getDisplayNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
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

    /** @return HasMany<Arrear, $this> */
    public function arrears(): HasMany
    {
        return $this->hasMany(Arrear::class);
    }

    /** @return HasMany<ArrearPayment, $this> */
    public function arrearPayments(): HasMany
    {
        return $this->hasMany(ArrearPayment::class);
    }

    /** @return HasMany<Deposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    /** @return HasMany<Condolence, $this> */
    public function condolencesAsDeceased(): HasMany
    {
        return $this->hasMany(Condolence::class, 'deceased_member_id');
    }

    /**
     * Combined condolence levies + general arrears + opening arrears.
     *
     * @return array{expected: float, paid: float, outstanding: float}
     */
    public function accountTotals(): array
    {
        $expected = (float) $this->levies()->sum('amount_expected')
            + (float) $this->arrears()->sum('amount_expected')
            + (float) $this->opening_arrears;
        $paid = (float) $this->levies()->sum('amount_paid')
            + (float) $this->arrears()->sum('amount_paid');

        return ['expected' => $expected, 'paid' => $paid, 'outstanding' => $expected - $paid];
    }

    /**
     * Only what is still owed on condolence levies (paid-off levies excluded).
     */
    public function leviesOwing(): float
    {
        return $this->levies()->get()->sum(
            fn (CondolenceLevy $levy): float => max(0, (float) $levy->amount_expected - (float) $levy->amount_paid)
        );
    }

    /**
     * Spend the member's credit balance on outstanding obligations,
     * oldest first: condolence levies, then general arrears.
     *
     * @return float amount of credit consumed
     */
    public function applyCredit(): float
    {
        return DB::transaction(function (): float {
            $credit = max(0, (float) static::query()->whereKey($this->id)->value('credit_balance'));

            if ($credit <= 0) {
                return 0.0;
            }

            $applied = 0.0;
            $reference = 'CREDIT-'.$this->id.'-'.now()->format('YmdHis');

            $levies = CondolenceLevy::where('condolence_levies.member_id', $this->id)
                ->whereIn('condolence_levies.status', [LevyStatus::Unpaid->value, LevyStatus::Partial->value])
                ->join('condolences', 'condolences.id', '=', 'condolence_levies.condolence_id')
                ->orderBy('condolences.date_announced')
                ->orderBy('condolence_levies.id')
                ->select('condolence_levies.*')
                ->get();

            foreach ($levies as $levy) {
                if ($credit <= 0) {
                    break;
                }

                $due = max(0, (float) $levy->amount_expected - (float) $levy->amount_paid);

                if ($due <= 0) {
                    continue;
                }

                $take = min($credit, $due);

                Payment::create([
                    'condolence_levy_id' => $levy->id,
                    'condolence_id' => $levy->condolence_id,
                    'member_id' => $this->id,
                    'amount' => $take,
                    'paid_at' => now()->toDateString(),
                    'payment_method' => PaymentMethod::Credit->value,
                    'reference' => $reference,
                    'reason' => ArrearReason::Condolence->value,
                    'notes' => 'Auto-applied from credit balance.',
                ]);

                $credit -= $take;
                $applied += $take;
            }

            $arrears = Arrear::where('member_id', $this->id)
                ->whereIn('status', [LevyStatus::Unpaid->value, LevyStatus::Partial->value])
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            foreach ($arrears as $arrear) {
                if ($credit <= 0) {
                    break;
                }

                $due = max(0, (float) $arrear->amount_expected - (float) $arrear->amount_paid);

                if ($due <= 0) {
                    continue;
                }

                $take = min($credit, $due);

                ArrearPayment::create([
                    'arrear_id' => $arrear->id,
                    'member_id' => $this->id,
                    'amount' => $take,
                    'paid_at' => now()->toDateString(),
                    'payment_method' => PaymentMethod::Credit->value,
                    'reference' => $reference,
                    'reason' => $arrear->reason,
                    'notes' => 'Auto-applied from credit balance.',
                ]);

                $credit -= $take;
                $applied += $take;
            }

            if ($applied > 0) {
                static::query()->whereKey($this->id)->decrement('credit_balance', $applied);
            }

            return $applied;
        });
    }
}
