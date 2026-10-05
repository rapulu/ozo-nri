<?php

namespace App\Filament\Widgets;

use App\Enums\CondolenceStatus;
use App\Enums\MemberStatus;
use App\Models\Condolence;
use App\Models\CondolenceLevy;
use App\Models\Member;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssociationStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activeMembers = Member::where('status', MemberStatus::Active->value)->count();
        $totalMembers = Member::count();
        $openCondolences = Condolence::where('status', CondolenceStatus::Open->value)->count();

        $expected = (float) CondolenceLevy::sum('amount_expected');
        $collected = (float) CondolenceLevy::sum('amount_paid');
        $outstanding = $expected - $collected;

        return [
            Stat::make('Active members', $activeMembers)
                ->description("{$totalMembers} total members")
                ->icon('heroicon-o-users'),
            Stat::make('Open condolences', $openCondolences)
                ->icon('heroicon-o-heart'),
            Stat::make('Total collected', '₦'.number_format($collected, 2))
                ->description('Across all condolences')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Outstanding', '₦'.number_format($outstanding, 2))
                ->description('Expected ₦'.number_format($expected, 2))
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
