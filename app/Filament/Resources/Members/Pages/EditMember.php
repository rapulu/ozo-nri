<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Members\Actions\LogMemberPayment;
use App\Filament\Resources\Members\MemberResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LogMemberPayment::make(),
            DeleteAction::make(),
        ];
    }
}
