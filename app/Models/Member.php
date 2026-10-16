<?php

namespace App\Models;

use App\Enums\LevyStatus;
use App\Enums\PaymentMethod;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'address',
        'bank_name',
        'account_number',
        'account_name',
        'date_joined',
        'photo_path',
        'status',
        'opening_arrears',
        'notes',
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
            'date_joined' => 'date',
            'opening_arrears' => 'decimal:2',
            'credit_balance' => 'decimal:2',
        ];
    }

    /** @return HasOne<User, $this> */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return array<string, string>
     */
    public static function nigerianBanks(): array
    {
        $banks = [
            'Access Bank',
            'Citibank Nigeria',
            'Ecobank Nigeria',
            'Fidelity Bank',
            'First Bank of Nigeria',
            'First City Monument Bank',
            'Globus Bank',
            'Guaranty Trust Bank',
            'Jaiz Bank',
            'Keystone Bank',
            'Kuda Microfinance Bank',
            'Lotus Bank',
            'Moniepoint Microfinance Bank',
            'OPay Digital Services',
            'PalmPay',
            'Parallex Bank',
            'Polaris Bank',
            'PremiumTrust Bank',
            'Providus Bank',
            'Stanbic IBTC Bank',
            'Standard Chartered Bank',
            'Sterling Bank',
            'SunTrust Bank',
            'Union Bank of Nigeria',
            'United Bank for Africa',
            'Unity Bank',
            'Wema Bank',
            'Zenith Bank',
        ];

        return array_combine($banks, $banks);
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
     * Combined condolence levies + opening arrears.
     *
     * @return array{expected: float, paid: float, outstanding: float}
     */
    public function accountTotals(): array
    {
        $expected = (float) $this->levies()->sum('amount_expected')
            + (float) $this->opening_arrears;
        $paid = (float) $this->levies()->sum('amount_paid');

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
     * Spend the member's credit balance on outstanding levies, oldest first.
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
                    'reason' => 'condolence',
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
