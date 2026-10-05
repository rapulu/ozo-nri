<?php

namespace App\Filament\Resources\Payments\Schemas;

use App\Enums\PaymentMethod;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Member;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment')
                    ->schema([
                        Select::make('condolence_id')
                            ->label('Condolence')
                            ->options(fn (): array => Condolence::query()->pluck('title', 'id')->toArray())
                            ->searchable()
                            ->required()
                            ->reactive(),
                        Select::make('member_id')
                            ->label('Member')
                            ->options(fn (): array => Member::query()->where('status', 'active')->orderBy('last_name')->get()->mapWithKeys(fn (Member $m): array => [$m->id => $m->full_name])->toArray())
                            ->searchable()
                            ->required()
                            ->reactive(),
                        Select::make('condolence_levy_id')
                            ->label('Levy obligation')
                            ->options(function (callable $get): array {
                                $condolenceId = $get('condolence_id');
                                $memberId = $get('member_id');

                                if (! $condolenceId || ! $memberId) {
                                    return CondolenceLevy::query()->with(['condolence', 'member'])->limit(50)->get()->mapWithKeys(fn (CondolenceLevy $l): array => [$l->id => ($l->condolence?->title ?? '#'.$l->condolence_id).' — '.($l->member?->full_name ?? '#'.$l->member_id)])->toArray();
                                }

                                return CondolenceLevy::query()
                                    ->where('condolence_id', $condolenceId)
                                    ->where('member_id', $memberId)
                                    ->with(['condolence', 'member'])
                                    ->get()
                                    ->mapWithKeys(fn (CondolenceLevy $l): array => [$l->id => ($l->condolence?->title ?? '').' — '.($l->member?->full_name ?? '').' ('.$l->status.')'])->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->helperText('Select the levy this payment settles. Create the condolence first so levies exist.'),
                        TextInput::make('amount')
                            ->required()
                            ->numeric()
                            ->minValue(1),
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
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
