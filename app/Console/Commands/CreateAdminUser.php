<?php

namespace App\Console\Commands;

use App\Models\Member;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'users:create-admin
        {--name= : Full name of the admin}
        {--email= : Email address used to sign in}
        {--password= : Password (prompted securely when omitted)}
        {--member= : Optionally link a member by ID or name}
        {--update : Update an existing login with the same email instead of failing}';

    protected $description = 'Create (or update) a secretary/admin login for the admin panel.';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password');

        $member = $this->resolveMember($this->option('member'));
        if ($this->option('member') && ! $member) {
            $this->error("No member matched '{$this->option('member')}'.");

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing && ! $this->option('update')) {
            $this->error("A login for {$email} already exists. Pass --update to change it.");

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = $existing ?? new User;
        $user->fill([
            'name' => $name,
            'email' => $email,
            'role' => User::ROLE_ADMIN,
            'member_id' => $member?->id ?? $user->member_id,
        ]);

        if (! $existing || $this->option('password')) {
            $user->password = Hash::make($password);
        }

        $user->save();

        $this->info(($existing ? 'Updated' : 'Created')." admin login for {$user->email}.");

        if ($member) {
            $this->line("Linked member: {$member->full_name}");
        }

        return self::SUCCESS;
    }

    private function resolveMember(?string $value): ?Member
    {
        if (blank($value)) {
            return null;
        }

        $member = Member::find($value);

        return $member ?? Member::where('first_name', 'like', "%{$value}%")
            ->orWhere('last_name', 'like', "%{$value}%")
            ->orderBy('last_name')
            ->first();
    }
}
