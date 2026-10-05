<?php

namespace App\Filament\Resources\Condolences\Pages;

use App\Filament\Resources\Condolences\CondolenceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCondolence extends CreateRecord
{
    protected static string $resource = CondolenceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->generateLevies();
    }
}
