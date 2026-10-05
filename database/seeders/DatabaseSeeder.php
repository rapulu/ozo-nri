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
            ]
        );

        Member::firstOrCreate(
            ['email' => 'member@nzena-ozo.local'],
            [
                'title' => 'Mazi',
                'first_name' => 'Demo',
                'middle_name' => null,
                'last_name' => 'Member',
                'password' => Hash::make('password'),
                'phone' => '08000000000',
                'address' => 'Demo address',
                'date_joined' => now()->toDateString(),
                'status' => MemberStatus::Active->value,
            ]
        );

        $activeCount = Member::where('status', MemberStatus::Active->value)->count();
        if ($activeCount < 20) {
            Member::factory(20 - $activeCount)->create([
                'status' => MemberStatus::Active->value,
            ]);
        }

        foreach ([
            ['email' => 'suspended.one@example.local', 'first_name' => 'Suspended', 'last_name' => 'One'],
            ['email' => 'suspended.two@example.local', 'first_name' => 'Suspended', 'last_name' => 'Two'],
        ] as $data) {
            Member::firstOrCreate(
                ['email' => $data['email']],
                [
                    'title' => 'Mazi',
                    'first_name' => $data['first_name'],
                    'middle_name' => null,
                    'last_name' => $data['last_name'],
                    'password' => Hash::make('password'),
                    'phone' => null,
                    'date_joined' => now()->toDateString(),
                    'status' => MemberStatus::Suspended->value,
                ]
            );
        }

        $deceasedData = [
            ['title' => 'Nze', 'first_name' => 'Chukwuemeka', 'last_name' => 'Obi', 'email' => 'late.obi@example.local'],
            ['title' => 'Ozo', 'first_name' => 'Nnamdi', 'last_name' => 'Eze', 'email' => 'late.eze@example.local'],
            ['title' => 'Ichie', 'first_name' => 'Okafor', 'last_name' => 'Udo', 'email' => 'late.udo@example.local'],
            ['title' => 'Mazi', 'first_name' => 'Emeka', 'last_name' => 'Nwosu', 'email' => 'late.nwosu@example.local'],
            ['title' => 'Nze', 'first_name' => 'Obinna', 'last_name' => 'Kanu', 'email' => 'late.kanu@example.local'],
        ];

        foreach ($deceasedData as $data) {
            Member::firstOrCreate(
                ['email' => $data['email']],
                [
                    'title' => $data['title'],
                    'first_name' => 'Late',
                    'middle_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'password' => Hash::make('password'),
                    'phone' => null,
                    'date_joined' => now()->subYears(5)->toDateString(),
                    'status' => MemberStatus::Deceased->value,
                ]
            );
        }
    }
}
