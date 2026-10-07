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
use Illuminate\Validation\ValidationException;

class LogMemberPayment extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'logPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Log payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->modalHeading(fn (mixed $livewire): string => 'Log payment — '.$livewire->getRecord()->full_name)
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
                        ->where('member_id', $livewire->getRecord()->id)
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
                        ->where('member_id', $livewire->getRecord()->id)
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

                        $outstanding = $livewire->getRecord()->accountTotals()['outstanding'];

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
                $member = $livewire->getRecord();

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

                        if ($totalOwed <= 0) {
                            throw ValidationException::withMessages([
                                'amount' => 'This member has no outstanding balance.',
                            ]);
                        }

                        if ((float) $data['amount'] > $totalOwed) {
                            throw ValidationException::withMessages([
                                'amount' => '₦'.number_format((float) $data['amount'], 0).' exceeds the ₦'.number_format($totalOwed, 0).' outstanding.',
                            ]);
                        }

                        $remaining = (float) $data['amount'];

                        // One shared reference ties the split rows back to this single deposit.
                        $reference = $data['reference'] ?? ('DEP-'.$member->id.'-'.now()->format('YmdHis'));
                        $depositLabel = '₦'.number_format((float) $data['amount'], 0).' deposit';

                        $splits = [];
                        foreach ($outstanding as $levy) {
                            if ($remaining <= 0) {
                                break;
                            }

                            $due = max(0, (float) $levy->amount_expected - (float) $levy->amount_paid);

                            if ($due <= 0) {
                                continue;
                            }

                            $splits[] = ['levy' => $levy, 'share' => min($remaining, $due)];
                            $remaining -= min($remaining, $due);
                        }

                        $arrearSplits = [];
                        foreach ($openArrears as $arrear) {
                            if ($remaining <= 0) {
                                break;
                            }

                            $due = max(0, (float) $arrear->amount_expected - (float) $arrear->amount_paid);

                            if ($due <= 0) {
                                continue;
                            }

                            $arrearSplits[] = ['arrear' => $arrear, 'share' => min($remaining, $due)];
                            $remaining -= min($remaining, $due);
                        }

                        $openingShare = min($remaining, max(0, (float) $member->opening_arrears));
                        $remaining -= $openingShare;

                        $splitCount = count($splits) + count($arrearSplits) + ($openingShare > 0 ? 1 : 0);
                        $splitIndex = 0;

                        Deposit::create([
                            'member_id' => $member->id,
                            'amount' => (float) $data['amount'],
                            'paid_at' => $data['paid_at'],
                            'payment_method' => $data['payment_method'],
                            'reference' => $reference,
                            'reason' => ArrearReason::Condolence->value,
                            'opening_applied' => $openingShare,
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

                        $touched = count($splits) + count($arrearSplits);
                        $balance = $totalOwed - (float) $data['amount'];

                        Notification::make()
                            ->title('₦'.number_format((float) $data['amount'], 0)." spread across {$touched} items".($openingShare > 0 ? ' + opening balance' : '').' — outstanding ₦'.number_format($balance, 0))
                            ->body("All split rows share reference {$reference}.")
                            ->success()
                            ->send();

                        return;
                    }

                    $levy = CondolenceLevy::where('member_id', $member->id)->findOrFail($data['condolence_levy_id']);

                    $reference = $data['reference'] ?? ('DEP-'.$member->id.'-'.now()->format('YmdHis'));

                    Deposit::create([
                        'member_id' => $member->id,
                        'amount' => (float) $data['amount'],
                        'paid_at' => $data['paid_at'],
                        'payment_method' => $data['payment_method'],
                        'reference' => $reference,
                        'reason' => ArrearReason::Condolence->value,
                        'recorded_by' => auth()->id(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    Payment::create([
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

                    $balance = (float) $levy->refresh()->amount_expected - (float) $levy->amount_paid;
                } else {
                    $arrear = Arrear::where('member_id', $member->id)->findOrFail($data['arrear_id']);

                    $reference = $data['reference'] ?? ('DEP-'.$member->id.'-'.now()->format('YmdHis'));

                    Deposit::create([
                        'member_id' => $member->id,
                        'amount' => (float) $data['amount'],
                        'paid_at' => $data['paid_at'],
                        'payment_method' => $data['payment_method'],
                        'reference' => $reference,
                        'reason' => $data['reason'],
                        'recorded_by' => auth()->id(),
                        'notes' => $data['notes'] ?? null,
                    ]);

                    ArrearPayment::create([
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

                    $balance = (float) $arrear->refresh()->amount_expected - (float) $arrear->amount_paid;
                }

                Notification::make()
                    ->title('Payment recorded — balance ₦'.number_format($balance, 0))
                    ->success()
                    ->send();
            });
    }
}
