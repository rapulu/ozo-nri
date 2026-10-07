<?php

namespace App\Filament\Resources\Arrears;

use App\Filament\Resources\Arrears\Pages\CreateArrear;
use App\Filament\Resources\Arrears\Pages\EditArrear;
use App\Filament\Resources\Arrears\Pages\ListArrears;
use App\Filament\Resources\Arrears\Pages\ViewArrear;
use App\Filament\Resources\Arrears\RelationManagers\ArrearPaymentsRelationManager;
use App\Filament\Resources\Arrears\Schemas\ArrearForm;
use App\Filament\Resources\Arrears\Schemas\ArrearInfolist;
use App\Filament\Resources\Arrears\Tables\ArrearsTable;
use App\Models\Arrear;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ArrearResource extends Resource
{
    protected static ?string $model = Arrear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationCircle;

    protected static ?string $navigationLabel = 'Arrears';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ArrearForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ArrearInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArrearsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ArrearPaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArrears::route('/'),
            'create' => CreateArrear::route('/create'),
            'view' => ViewArrear::route('/{record}'),
            'edit' => EditArrear::route('/{record}/edit'),
        ];
    }
}
