<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Condolences (plus their levies and payments) are intentionally not
     * seeded — the secretary creates them manually in /admin/condolences.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'secretary@nzena-ozo.local'],
            [
                'name' => 'Secretary',
                'password' => Hash::make('password'),
                'role' => User::ROLE_ADMIN,
                'member_id' => null,
            ]
        );

        $demoMember = Member::firstOrCreate(
            ['first_name' => 'Demo', 'last_name' => 'Member'],
            [
                'title' => 'Mazi',
                'middle_name' => null,
                'phone' => '08000000000',
                'address' => 'Demo address',
                'date_joined' => now()->toDateString(),
                'status' => MemberStatus::Active->value,
            ]
        );

        $demoUser = User::firstOrCreate(
            ['email' => 'member@nzena-ozo.local'],
            [
                'name' => 'Demo Member',
                'password' => Hash::make('password'),
                'role' => User::ROLE_MEMBER,
                'member_id' => $demoMember->id,
            ]
        );
        $demoUser->update(['role' => User::ROLE_MEMBER, 'member_id' => $demoMember->id]);

        $activeCount = Member::where('status', MemberStatus::Active->value)->count();
        if ($activeCount < 20) {
            Member::factory(20 - $activeCount)->create([
                'status' => MemberStatus::Active->value,
            ]);
        }

        foreach ([
            ['first_name' => 'Suspended', 'last_name' => 'One'],
            ['first_name' => 'Suspended', 'last_name' => 'Two'],
        ] as $data) {
            Member::firstOrCreate(
                ['first_name' => $data['first_name'], 'last_name' => $data['last_name']],
                [
                    'title' => 'Mazi',
                    'middle_name' => null,
                    'phone' => null,
                    'date_joined' => now()->toDateString(),
                    'status' => MemberStatus::Suspended->value,
                ]
            );
        }

        $deceasedData = [
            ['title' => 'Nze', 'first_name' => 'Chukwuemeka', 'last_name' => 'Obi'],
            ['title' => 'Ozo', 'first_name' => 'Nnamdi', 'last_name' => 'Eze'],
            ['title' => 'Ichie', 'first_name' => 'Okafor', 'last_name' => 'Udo'],
            ['title' => 'Mazi', 'first_name' => 'Emeka', 'last_name' => 'Nwosu'],
            ['title' => 'Nze', 'first_name' => 'Obinna', 'last_name' => 'Kanu'],
        ];

        foreach ($deceasedData as $data) {
            Member::firstOrCreate(
                ['first_name' => 'Late', 'last_name' => $data['last_name']],
                [
                    'title' => $data['title'],
                    'middle_name' => $data['first_name'],
                    'phone' => null,
                    'date_joined' => now()->subYears(5)->toDateString(),
                    'status' => MemberStatus::Deceased->value,
                ]
            );
        }
    }
}
