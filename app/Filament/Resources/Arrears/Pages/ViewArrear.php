<?php

namespace App\Filament\Resources\Arrears\Pages;

use App\Filament\Resources\Arrears\ArrearResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewArrear extends ViewRecord
{
    protected static string $resource = ArrearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
