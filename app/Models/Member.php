<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Database\Factories\MemberFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
}
