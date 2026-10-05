<?php

namespace App\Filament\Resources\Condolences;

use App\Filament\Resources\Condolences\Pages\CreateCondolence;
use App\Filament\Resources\Condolences\Pages\EditCondolence;
use App\Filament\Resources\Condolences\Pages\ListCondolences;
use App\Filament\Resources\Condolences\Pages\ViewCondolence;
use App\Filament\Resources\Condolences\RelationManagers\LeviesRelationManager;
use App\Filament\Resources\Condolences\Schemas\CondolenceForm;
use App\Filament\Resources\Condolences\Schemas\CondolenceInfolist;
use App\Filament\Resources\Condolences\Tables\CondolencesTable;
use App\Models\Condolence;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CondolenceResource extends Resource
{
    protected static ?string $model = Condolence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?string $navigationLabel = 'Condolences';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CondolenceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CondolenceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CondolencesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LeviesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCondolences::route('/'),
            'create' => CreateCondolence::route('/create'),
            'view' => ViewCondolence::route('/{record}'),
            'edit' => EditCondolence::route('/{record}/edit'),
        ];
    }
}
