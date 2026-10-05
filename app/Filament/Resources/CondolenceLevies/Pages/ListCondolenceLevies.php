<?php

namespace App\Filament\Resources\CondolenceLevies\Pages;

use App\Filament\Resources\CondolenceLevies\CondolenceLevyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCondolenceLevies extends ListRecords
{
    protected static string $resource = CondolenceLevyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
