<?php

namespace App\Filament\Resources\Condolences\Pages;

use App\Filament\Resources\Condolences\CondolenceResource;
use App\Http\Controllers\ReportController;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCondolence extends EditRecord
{
    protected static string $resource = CondolenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateLevies')
                ->label('Regenerate levies')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->generateLevies();

                    Notification::make()
                        ->title('Levies regenerated for all active members')
                        ->success()
                        ->send();
                }),
            Action::make('condolencePdf')
                ->label('Condolence PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->modalHeading('Download condolence record')
                ->modalDescription('A full record takes several seconds to prepare. After clicking Download, watch your browser downloads — please wait for the file to arrive before starting another download.')
                ->modalSubmitActionLabel('Download')
                ->schema([
                    Select::make('members')
                        ->label('Members')
                        ->options(fn (): array => ReportController::memberRangeOptions(
                            $this->record->levies()->count(),
                            ReportController::AREA_PDF_CHUNK_SIZE,
                            ReportController::AREA_PDF_ALL_LIMIT,
                        ))
                        ->default('all')
                        ->required(),
                ])
                ->action(function (array $data, mixed $livewire): void {
                    $params = [];

                    if ($data['members'] !== 'all') {
                        $params['members'] = $data['members'];
                    }

                    $livewire->redirect(route('reports.condolence', ['condolence' => $this->record, ...$params]));
                }),
            Action::make('condolenceCsv')
                ->label('Condolence CSV')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn (): string => route('reports.condolence-csv', ['condolence' => $this->record]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->generateLevies();
    }
}
