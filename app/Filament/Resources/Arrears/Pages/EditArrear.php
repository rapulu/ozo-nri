<?php

namespace App\Filament\Resources\Arrears\Pages;

use App\Filament\Resources\Arrears\ArrearResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArrear extends EditRecord
{
    protected static string $resource = ArrearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
