<?php

namespace App\Filament\Resources\Members\Actions;

use App\Enums\ArrearReason;
use App\Enums\LevyStatus;
use App\Enums\PaymentMethod;
use App\Models\Arrear;
use App\Models\ArrearPayment;
use App\Models\CondolenceLevy;
use App\Models\Deposit;
use App\Models\Member;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class LogMemberPayment extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'logPayment';
    }

    /**
     * Works from member pages (getRecord) and relation managers (getOwnerRecord).
     */
    public static function resolveMember(mixed $livewire): Member
    {
        if (method_exists($livewire, 'getRecord') && $livewire->getRecord() instanceof Member) {
            return $livewire->getRecord();
        }

        return $livewire->getOwnerRecord();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Log payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading(fn (mixed $livewire): string => 'Log payment — '.LogMemberPayment::resolveMember($livewire)->full_name)
            ->modalDescription('Deposit any amount: it spreads across outstanding condolence levies oldest-first, or pick one specific levy. Part-payments welcome.')
            ->modalSubmitActionLabel('Save payment')
            ->schema([
                Select::make('reason')
                    ->options(ArrearReason::options())
                    ->default(ArrearReason::Condolence->value)
                    ->required()
                    ->reactive(),
                Radio::make('allocation')
                    ->label('Apply deposit to')
                    ->options([
                        'auto' => 'Whole outstanding (levies, then arrears, then opening)',
                        'specific' => 'One specific levy',
                    ])
                    ->default('auto')
                    ->inline()
                    ->reactive()
                    ->visible(fn (callable $get): bool => $get('reason') === ArrearReason::Condolence->value),
                Select::make('condolence_levy_id')
                    ->label('Condolence levy')
                    ->options(fn (mixed $livewire): array => CondolenceLevy::query()
                        ->where('member_id', LogMemberPayment::resolveMember($livewire)->id)
                        ->with('condolence')
                        ->get()
                        ->mapWithKeys(fn (CondolenceLevy $l): array => [
                            $l->id => ($l->condolence?->title ?? '—').' — ₦'.number_format((float) $l->amount_expected - (float) $l->amount_paid, 0).' due ('.$l->status.')',
                        ])
                        ->toArray())
                    ->searchable()
                    ->visible(fn (callable $get): bool => $get('reason') === ArrearReason::Condolence->value && $get('allocation') === 'specific')
                    ->required(fn (callable $get): bool => $get('reason') === ArrearReason::Condolence->value && $get('allocation') === 'specific')
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $levy = $state ? CondolenceLevy::find($state) : null;
                        $set('amount', $levy ? max(0, (float) $levy->amount_expected - (float) $levy->amount_paid) : null);
                    }),
                Select::make('arrear_id')
                    ->label('Arrear')
                    ->options(fn (mixed $livewire, callable $get): array => Arrear::query()
                        ->where('member_id', LogMemberPayment::resolveMember($livewire)->id)
                        ->where('reason', $get('reason'))
                        ->whereIn('status', [LevyStatus::Unpaid->value, LevyStatus::Partial->value])
                        ->orderBy('due_date')
                        ->get()
                        ->mapWithKeys(fn (Arrear $a): array => [
                            $a->id => $a->title.' — ₦'.number_format((float) $a->amount_expected - (float) $a->amount_paid, 0).' due',
                        ])
                        ->toArray())
                    ->searchable()
                    ->visible(fn (callable $get): bool => $get('reason') !== ArrearReason::Condolence->value)
                    ->required(fn (callable $get): bool => $get('reason') !== ArrearReason::Condolence->value)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $arrear = $state ? Arrear::find($state) : null;
                        $set('amount', $arrear ? max(0, (float) $arrear->amount_expected - (float) $arrear->amount_paid) : null);
                    })
                    ->helperText('No open arrear of this reason? Create it first under Arrears.'),
                TextInput::make('amount')
                    ->label('Amount received (₦)')
                    ->required()
                    ->numeric()
                    ->stripCharacters([',', ' '])
                    ->minValue(1)
                    ->helperText(function (mixed $livewire, callable $get): ?string {
                        if ($get('reason') !== ArrearReason::Condolence->value || ($get('allocation') ?? 'auto') !== 'auto') {
                            return null;
                        }

                        $outstanding = LogMemberPayment::resolveMember($livewire)->accountTotals()['outstanding'];

                        return 'Total outstanding (levies + arrears + opening): ₦'.number_format($outstanding, 0).' — deposit any part of it.';
                    }),
                DatePicker::make('paid_at')
                    ->label('Date received')
                    ->required()
                    ->default(now())
                    ->maxDate(now()),
                Select::make('payment_method')
                    ->options(PaymentMethod::options())
                    ->required()
                    ->default(PaymentMethod::Cash->value),
                TextInput::make('reference')
                    ->label('Receipt / reference no.')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, mixed $livewire): void {
                /** @var Member $member */
                $member = LogMemberPayment::resolveMember($livewire);

                // Previous outstanding becomes the arrears figure of record for this handover.
                $outstandingBefore = $member->accountTotals()['outstanding'];

                $stampSnapshot = function (Deposit $deposit) use ($member, $outstandingBefore): void {
                    $deposit->update([
                        'outstanding_before' => $outstandingBefore,
                        'outstanding_after' => $member->refresh()->accountTotals()['outstanding'],
                    ]);
                };

                if ($data['reason'] === ArrearReason::Condolence->value) {
                    if (($data['allocation'] ?? 'auto') === 'auto') {
                        // Spread across everything owed: levies oldest-first,
                        // then open arrears, then the opening balance.
                        $outstanding = CondolenceLevy::where('condolence_levies.member_id', $member->id)
                            ->whereIn('condolence_levies.status', [LevyStatus::Unpaid->value, LevyStatus::Partial->value])
                            ->join('condolences', 'condolences.id', '=', 'condolence_levies.condolence_id')
                            ->orderBy('condolences.date_announced')
                            ->orderBy('condolence_levies.id')
                            ->select('condolence_levies.*')
                            ->get();

                        $openArrears = Arrear::where('member_id', $member->id)
                            ->whereIn('status', [LevyStatus::Unpaid->value, LevyStatus::Partial->value])
                            ->orderBy('due_date')
                            ->orderBy('id')
                            ->get();

                        $totalOwed = $outstanding->sum(fn (CondolenceLevy $l): float => max(0, (float) $l->amount_expected - (float) $l->amount_paid))
                            + $openArrears->sum(fn (Arrear $a): float => max(0, (float) $a->amount_expected - (float) $a->amount_paid))
                            + max(0, (float) $member->opening_arrears);

                        $remaining = (float) $data['amount'];

                        // One shared reference ties the split rows back to this single deposit.
                        $reference = $data['reference'] ?? ('DEP-'.$member->id.'-'.now()->format('YmdHis'));
                        $depositLabel = '₦'.number_format((float) $data['amount'], 0).' deposit';

                        $result = DB::transaction(function () use ($member, $data, $outstanding, $openArrears, $reference, $depositLabel, $remaining, $stampSnapshot): array {
                            $left = $remaining;
                            $splits = [];
                            foreach ($outstanding as $levy) {
                                if ($left <= 0) {
                                    break;
                                }

                                $due = max(0, (float) $levy->amount_expected - (float) $levy->amount_paid);

                                if ($due <= 0) {
                                    continue;
                                }

                                $splits[] = ['levy' => $levy, 'share' => min($left, $due)];
                                $left -= min($left, $due);
                            }

                            $arrearSplits = [];
                            foreach ($openArrears as $arrear) {
                                if ($left <= 0) {
                                    break;
                                }

                                $due = max(0, (float) $arrear->amount_expected - (float) $arrear->amount_paid);

                                if ($due <= 0) {
                                    continue;
                                }

                                $arrearSplits[] = ['arrear' => $arrear, 'share' => min($left, $due)];
                                $left -= min($left, $due);
                            }

                            $openingShare = min($left, max(0, (float) $member->opening_arrears));
                            $left -= $openingShare;

                            // Anything left over becomes member credit for future levies.
                            $creditShare = max(0, $left);
                            if ($creditShare > 0) {
                                Member::query()->whereKey($member->id)->increment('credit_balance', $creditShare);
                            }

                            $splitCount = count($splits) + count($arrearSplits) + ($openingShare > 0 ? 1 : 0);
                            $splitIndex = 0;

                            $deposit = Deposit::create([
                                'member_id' => $member->id,
                                'amount' => (float) $data['amount'],
                                'paid_at' => $data['paid_at'],
                                'payment_method' => $data['payment_method'],
                                'reference' => $reference,
                                'reason' => ArrearReason::Condolence->value,
                                'opening_applied' => $openingShare,
                                'credit_added' => $creditShare,
                                'recorded_by' => auth()->id(),
                                'notes' => $data['notes'] ?? null,
                            ]);

                            foreach ($splits as $split) {
                                $splitIndex++;
                                $note = trim(($data['notes'] ?? '').' [Split '.$splitIndex." of {$splitCount} — {$depositLabel}]");

                                Payment::create([
                                    'condolence_levy_id' => $split['levy']->id,
                                    'condolence_id' => $split['levy']->condolence_id,
                                    'member_id' => $member->id,
                                    'amount' => $split['share'],
                                    'paid_at' => $data['paid_at'],
                                    'payment_method' => $data['payment_method'],
                                    'reference' => $reference,
                                    'reason' => ArrearReason::Condolence->value,
                                    'recorded_by' => auth()->id(),
                                    'notes' => $note,
                                ]);
                            }

                            foreach ($arrearSplits as $split) {
                                $splitIndex++;
                                $note = trim(($data['notes'] ?? '').' [Split '.$splitIndex." of {$splitCount} — {$depositLabel}]");

                                ArrearPayment::create([
                                    'arrear_id' => $split['arrear']->id,
                                    'member_id' => $member->id,
                                    'amount' => $split['share'],
                                    'paid_at' => $data['paid_at'],
                                    'payment_method' => $data['payment_method'],
                                    'reference' => $reference,
                                    'reason' => $split['arrear']->reason,
                                    'recorded_by' => auth()->id(),
                                    'notes' => $note,
                                ]);
                            }

                            if ($openingShare > 0) {
                                $member->decrement('opening_arrears', $openingShare);
                            }

                            $stampSnapshot($deposit);

                            return [
                                'touched' => count($splits) + count($arrearSplits),
                                'openingShare' => $openingShare,
                                'creditShare' => $creditShare,
                            ];
                        });

                        $balance = max(0, $totalOwed - (float) $data['amount']);
                        $extra = [];
                        if ($result['openingShare'] > 0) {
                            $extra[] = 'opening balance';
                        }
                        if ($result['creditShare'] > 0) {
                            $extra[] = '₦'.number_format($result['creditShare'], 0).' kept as credit';
                        }

                        Notification::make()
                            ->title('₦'.number_format((float) $data['amount'], 0)." spread across {$result['touched']} items".($extra !== [] ? ' + '.implode(' + ', $extra) : '').' — outstanding ₦'.number_format($balance, 0))
                            ->body("All split rows share reference {$reference}.")
                            ->success()
                            ->send();

                        return;
                    }

                    $levy = CondolenceLevy::where('member_id', $member->id)->findOrFail($data['condolence_levy_id']);

                    $reference = $data['reference'] ?? ('DEP-'.$member->id.'-'.now()->format('YmdHis'));

                    $payment = DB::transaction(function () use ($member, $data, $levy, $reference, $stampSnapshot): Payment {
                        $deposit = Deposit::create([
                            'member_id' => $member->id,
                            'amount' => (float) $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $reference,
                            'reason' => ArrearReason::Condolence->value,
                            'recorded_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $payment = Payment::create([
                            'condolence_levy_id' => $levy->id,
                            'condolence_id' => $levy->condolence_id,
                            'member_id' => $member->id,
                            'amount' => $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $reference,
                            'reason' => ArrearReason::Condolence->value,
                            'recorded_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $stampSnapshot($deposit);

                        return $payment;
                    });

                    // The model caps the row at what is owed; anything above becomes credit.
                    $applied = (float) $payment->amount;
                    $credited = max(0, (float) $data['amount'] - $applied);

                    if ($credited > 0) {
                        Deposit::where('member_id', $member->id)->where('reference', $reference)->update(['credit_added' => $credited]);
                    }

                    $balance = (float) $levy->refresh()->amount_expected - (float) $levy->amount_paid;
                } else {
                    $arrear = Arrear::where('member_id', $member->id)->findOrFail($data['arrear_id']);

                    $reference = $data['reference'] ?? ('DEP-'.$member->id.'-'.now()->format('YmdHis'));

                    $arrearPayment = DB::transaction(function () use ($member, $data, $arrear, $reference, $stampSnapshot): ArrearPayment {
                        $deposit = Deposit::create([
                            'member_id' => $member->id,
                            'amount' => (float) $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $reference,
                            'reason' => $data['reason'],
                            'recorded_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $arrearPayment = ArrearPayment::create([
                            'arrear_id' => $arrear->id,
                            'member_id' => $member->id,
                            'amount' => $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $reference,
                            'reason' => $data['reason'],
                            'recorded_by' => auth()->id(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $stampSnapshot($deposit);

                        return $arrearPayment;
                    });

                    $applied = (float) $arrearPayment->amount;
                    $credited = max(0, (float) $data['amount'] - $applied);

                    if ($credited > 0) {
                        Deposit::where('member_id', $member->id)->where('reference', $reference)->update(['credit_added' => $credited]);
                    }

                    $balance = (float) $arrear->refresh()->amount_expected - (float) $arrear->amount_paid;
                }

                Notification::make()
                    ->title('Payment recorded — balance ₦'.number_format($balance, 0).($credited > 0 ? ', ₦'.number_format($credited, 0).' kept as credit' : ''))
                    ->success()
                    ->send();
            });
    }
}
