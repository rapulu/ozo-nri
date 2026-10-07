<?php

namespace App\Filament\Resources\Arrears\Pages;

use App\Filament\Resources\Arrears\ArrearResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArrears extends ListRecords
{
    protected static string $resource = ArrearResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
