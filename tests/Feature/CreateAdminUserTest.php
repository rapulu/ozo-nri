<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_admin_login(): void
    {
        $this->artisan('users:create-admin', [
            '--name' => 'New Secretary',
            '--email' => 'new.secretary@nzena-ozo.local',
            '--password' => 'secret-password',
        ])->assertExitCode(0);

        $user = User::where('email', 'new.secretary@nzena-ozo.local')->firstOrFail();

        $this->assertSame('New Secretary', $user->name);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertNull($user->member_id);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_it_links_an_admin_to_a_member(): void
    {
        $member = Member::factory()->create(['first_name' => 'Emelie', 'last_name' => 'Okika']);

        $this->artisan('users:create-admin', [
            '--name' => 'Emelie Okika',
            '--email' => 'emelie@nzena-ozo.local',
            '--password' => 'secret-password',
            '--member' => $member->id,
        ])->assertExitCode(0);

        $this->assertSame(
            $member->id,
            User::where('email', 'emelie@nzena-ozo.local')->firstOrFail()->member_id,
        );
    }

    public function test_it_refuses_an_existing_email_without_update(): void
    {
        User::factory()->create(['email' => 'taken@nzena-ozo.local']);

        $this->artisan('users:create-admin', [
            '--name' => 'Someone',
            '--email' => 'taken@nzena-ozo.local',
            '--password' => 'secret-password',
        ])->assertExitCode(1);

        $this->assertSame(1, User::where('email', 'taken@nzena-ozo.local')->count());
    }

    public function test_it_promotes_an_existing_login_with_update(): void
    {
        $user = User::factory()->create([
            'email' => 'member@nzena-ozo.local',
            'role' => User::ROLE_MEMBER,
        ]);

        $this->artisan('users:create-admin', [
            '--name' => 'Promoted Member',
            '--email' => 'member@nzena-ozo.local',
            '--password' => 'brand-new-password',
            '--update' => true,
        ])->assertExitCode(0);

        $user->refresh();

        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertSame('Promoted Member', $user->name);
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->artisan('users:create-admin', [
            '--name' => 'Weak Password',
            '--email' => 'weak@nzena-ozo.local',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'weak@nzena-ozo.local']);
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        $this->artisan('users:create-admin', [
            '--name' => 'Bad Email',
            '--email' => 'not-an-email',
            '--password' => 'secret-password',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['name' => 'Bad Email']);
    }

    public function test_it_reports_an_unmatched_member(): void
    {
        $this->artisan('users:create-admin', [
            '--name' => 'Orphan',
            '--email' => 'orphan@nzena-ozo.local',
            '--password' => 'secret-password',
            '--member' => 'Nobody Here',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'orphan@nzena-ozo.local']);
    }
}
