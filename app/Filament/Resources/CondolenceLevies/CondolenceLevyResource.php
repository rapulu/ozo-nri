<?php

namespace App\Filament\Resources\CondolenceLevies;

use App\Filament\Resources\CondolenceLevies\Pages\CreateCondolenceLevy;
use App\Filament\Resources\CondolenceLevies\Pages\EditCondolenceLevy;
use App\Filament\Resources\CondolenceLevies\Pages\ListCondolenceLevies;
use App\Filament\Resources\CondolenceLevies\Pages\ViewCondolenceLevy;
use App\Filament\Resources\CondolenceLevies\Schemas\CondolenceLevyForm;
use App\Filament\Resources\CondolenceLevies\Schemas\CondolenceLevyInfolist;
use App\Filament\Resources\CondolenceLevies\Tables\CondolenceLeviesTable;
use App\Models\CondolenceLevy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CondolenceLevyResource extends Resource
{
    protected static ?string $model = CondolenceLevy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Levies / Obligations';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return CondolenceLevyForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CondolenceLevyInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CondolenceLeviesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCondolenceLevies::route('/'),
            'create' => CreateCondolenceLevy::route('/create'),
            'view' => ViewCondolenceLevy::route('/{record}'),
            'edit' => EditCondolenceLevy::route('/{record}/edit'),
        ];
    }
}
