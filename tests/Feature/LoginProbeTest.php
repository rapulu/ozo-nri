<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_login_succeeds_on_member_portal(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $user = User::factory()->create(['role' => User::ROLE_MEMBER, 'member_id' => $member->id]);

        Filament::setCurrentPanel(Filament::getPanel('member'));

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_member_login_fails_on_admin_portal(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $user = User::factory()->create(['role' => User::ROLE_MEMBER, 'member_id' => $member->id]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->set('data.email', $user->email)
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest('web');
    }

    public function test_admin_linked_to_active_member_keeps_both_portals(): void
    {
        $member = Member::factory()->create(['status' => MemberStatus::Active->value]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'member_id' => $member->id]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('member')));
    }

    public function test_admin_without_member_has_no_portal(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'member_id' => null]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('member')));
    }
}
