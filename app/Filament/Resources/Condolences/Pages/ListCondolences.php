<?php

namespace App\Filament\Resources\Condolences\Pages;

use App\Enums\MemberStatus;
use App\Filament\Resources\Condolences\CondolenceResource;
use App\Http\Controllers\ReportController;
use App\Models\Member;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListCondolences extends ListRecords
{
    protected static string $resource = CondolenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('matrixPdf')
                ->label('Matrix PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->modalHeading('Download matrix PDF')
                ->modalDescription('A full download can take up to ~30 seconds to prepare. After clicking Download, watch your browser downloads — please wait for the file to arrive before starting another download.')
                ->modalSubmitActionLabel('Download')
                ->schema([
                    Select::make('status')
                        ->options(['all' => 'All condolences', 'open' => 'Open only'])
                        ->default('all')
                        ->required(),
                    Select::make('members')
                        ->label('Members')
                        ->options(fn (): array => ReportController::memberRangeOptions(
                            Member::where('status', MemberStatus::Active->value)->count(),
                            ReportController::MATRIX_PDF_CHUNK_SIZE,
                            ReportController::MATRIX_PDF_ALL_LIMIT,
                        ))
                        ->default(fn (): string => array_key_first(ReportController::memberRangeOptions(
                            Member::where('status', MemberStatus::Active->value)->count(),
                            ReportController::MATRIX_PDF_CHUNK_SIZE,
                            ReportController::MATRIX_PDF_ALL_LIMIT,
                        )) ?? 'all')
                        ->required(),
                ])
                ->action(function (array $data, mixed $livewire): void {
                    $params = ['status' => $data['status']];

                    if ($data['members'] !== 'all') {
                        $params['members'] = $data['members'];
                    }

                    $livewire->redirect(route('reports.condolences-matrix', $params));
                }),
            Action::make('matrixCsv')
                ->label('Matrix CSV')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->modalHeading('Download matrix CSV')
                ->modalDescription('The full record for all members in one spreadsheet file — ready in seconds.')
                ->modalSubmitActionLabel('Download')
                ->schema([
                    Select::make('status')
                        ->options(['all' => 'All condolences', 'open' => 'Open only'])
                        ->default('all')
                        ->required(),
                ])
                ->action(function (array $data, mixed $livewire): void {
                    $livewire->redirect(route('reports.condolences-matrix-csv', ['status' => $data['status']]));
                }),
            CreateAction::make(),
        ];
    }
}
