<?php

namespace App\Filament\Resources\Condolences\Schemas;

use App\Enums\CondolenceStatus;
use App\Enums\MemberStatus;
use App\Models\Condolence;
use App\Models\Member;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CondolenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Condolence details')
                    ->schema([
                        Select::make('deceased_member_id')
                            ->label('Deceased member')
                            ->options(function (mixed $record): array {
                                $usedIds = Condolence::query()
                                    ->when($record instanceof Condolence && $record->exists, fn ($query) => $query->where('id', '!=', $record->id))
                                    ->pluck('deceased_member_id');

                                return Member::query()
                                    ->where('status', MemberStatus::Deceased->value)
                                    ->whereNotIn('id', $usedIds)
                                    ->orderBy('last_name')
                                    ->orderBy('first_name')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m): array => [$m->id => $m->full_name])
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->unique(table: 'condolences', column: 'deceased_member_id', ignoreRecord: true)
                            ->rule(function (): Closure {
                                return function (string $attribute, mixed $value, Closure $fail): void {
                                    $member = Member::find($value);

                                    if (! $member || $member->status !== MemberStatus::Deceased->value) {
                                        $fail('A condolence can only be created for a member whose status is deceased.');
                                    }
                                };
                            })
                            ->helperText('Only deceased members without a condolence are listed. Mark the member as deceased first — one condolence per deceased member.'),
                        TextInput::make('amount_per_member')
                            ->label('Amount per member (₦)')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(5000),
                        DatePicker::make('date_announced')
                            ->required()
                            ->default(now()),
                        DatePicker::make('due_date')
                            ->afterOrEqual('date_announced'),
                        Select::make('status')
                            ->options(CondolenceStatus::options())
                            ->required()
                            ->default(CondolenceStatus::Open->value),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
