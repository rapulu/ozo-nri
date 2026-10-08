<?php

namespace App\Filament\Resources\Members\Pages;

use App\Enums\MemberStatus;
use App\Filament\Resources\Members\MemberResource;
use App\Models\Member;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'active' => Tab::make()
                ->query(fn (Builder $query): Builder => $query->where('status', MemberStatus::Active->value))
                ->badge(Member::where('status', MemberStatus::Active->value)->count()),
            'suspended' => Tab::make()
                ->query(fn (Builder $query): Builder => $query->where('status', MemberStatus::Suspended->value))
                ->badge(Member::where('status', MemberStatus::Suspended->value)->count()),
            'deceased' => Tab::make()
                ->query(fn (Builder $query): Builder => $query->where('status', MemberStatus::Deceased->value))
                ->badge(Member::where('status', MemberStatus::Deceased->value)->count()),
            'all' => Tab::make()
                ->badge(Member::count()),
        ];
    }
}
