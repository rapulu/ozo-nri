<?php

namespace App\Filament\Resources\Members\Actions;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class CreateMemberUser extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'createLogin';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label('Create login')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading(fn (mixed $livewire): string => 'Create login — '.LogMemberPayment::resolveMember($livewire)->full_name)
            ->modalDescription('The member signs in with this email and password, and sees only their own profile.')
            ->modalSubmitActionLabel('Create login')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->rule('unique:users,email')
                    ->required()
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->rule(Password::default())
                    ->required()
                    ->maxLength(255),
            ])
            ->fillForm(fn (mixed $livewire): array => [
                'name' => LogMemberPayment::resolveMember($livewire)->full_name,
            ])
            ->visible(fn (mixed $livewire): bool => LogMemberPayment::resolveMember($livewire)->status === MemberStatus::Active->value
                && LogMemberPayment::resolveMember($livewire)->user === null)
            ->action(function (array $data, mixed $livewire): void {
                /** @var Member $member */
                $member = LogMemberPayment::resolveMember($livewire);

                User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => User::ROLE_MEMBER,
                    'member_id' => $member->id,
                ]);

                Notification::make()
                    ->title('Login created — share the email and password with the member')
                    ->success()
                    ->send();
            });
    }
}
