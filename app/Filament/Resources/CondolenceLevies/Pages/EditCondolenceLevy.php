<?php

namespace App\Filament\Resources\CondolenceLevies\Pages;

use App\Filament\Resources\CondolenceLevies\CondolenceLevyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCondolenceLevy extends EditRecord
{
    protected static string $resource = CondolenceLevyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
