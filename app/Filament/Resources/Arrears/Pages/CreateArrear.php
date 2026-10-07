<?php

namespace App\Filament\Resources\Arrears\Pages;

use App\Filament\Resources\Arrears\ArrearResource;
use Filament\Resources\Pages\CreateRecord;

class CreateArrear extends CreateRecord
{
    protected static string $resource = ArrearResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
