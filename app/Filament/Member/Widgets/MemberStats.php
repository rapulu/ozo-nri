<?php

namespace App\Filament\Member\Widgets;

use App\Models\Arrear;
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

        $totals = $member->accountTotals();
        $unpaidCount = CondolenceLevy::where('member_id', $member->id)->whereIn('status', ['unpaid', 'partial'])->count()
            + Arrear::where('member_id', $member->id)->whereIn('status', ['unpaid', 'partial'])->count();

        $stats = [
            Stat::make('My total paid', '₦'.number_format($totals['paid'], 2))
                ->icon('heroicon-o-banknotes'),
            Stat::make('My outstanding', '₦'.number_format($totals['outstanding'], 2))
                ->description("{$unpaidCount} unpaid item(s)")
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Total expected', '₦'.number_format($totals['expected'], 2))
                ->icon('heroicon-o-clipboard-document-list'),
        ];

        if ((float) $member->credit_balance > 0) {
            $stats[] = Stat::make('Owed to me', '₦'.number_format((float) $member->credit_balance, 2))
                ->description('Eaten by future levies automatically')
                ->icon('heroicon-o-sparkles');
        }

        return $stats;
    }
}
