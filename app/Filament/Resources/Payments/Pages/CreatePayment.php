<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\CondolenceLevy;
use Filament\Resources\Pages\CreateRecord;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['recorded_by'] = auth()->id();

        $levy = CondolenceLevy::find($data['condolence_levy_id']);

        if ($levy) {
            $data['condolence_id'] = $levy->condolence_id;
            $data['member_id'] = $levy->member_id;
        }

        return $data;
    }
}
