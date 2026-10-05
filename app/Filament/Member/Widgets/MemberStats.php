<?php

namespace App\Filament\Member\Widgets;

use App\Models\CondolenceLevy;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MemberStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $member = Auth::guard('member')->user();

        if (! $member) {
            return [];
        }

        $levies = CondolenceLevy::where('member_id', $member->id);
        $expected = (float) (clone $levies)->sum('amount_expected');
        $paid = (float) (clone $levies)->sum('amount_paid');
        $outstanding = $expected - $paid;
        $unpaidCount = (clone $levies)->whereIn('status', ['unpaid', 'partial'])->count();

        return [
            Stat::make('My total paid', '₦'.number_format($paid, 2))
                ->icon('heroicon-o-banknotes'),
            Stat::make('My outstanding', '₦'.number_format($outstanding, 2))
                ->description("{$unpaidCount} unpaid condolence(s)")
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Total expected', '₦'.number_format($expected, 2))
                ->icon('heroicon-o-clipboard-document-list'),
        ];
    }
}
