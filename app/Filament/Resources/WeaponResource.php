<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeaponResource\Pages;
use App\Filament\Resources\WeaponResource\RelationManagers;
use App\Models\Weapon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class WeaponResource extends Resource
{
    protected static ?string $model = Weapon::class;

    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?string $navigationLabel = 'Broń';

    protected static ?string $modelLabel = 'Broń';

    protected static ?string $pluralModelLabel = 'Broń';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->label('Nazwa'),

                Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull()
                    ->label('Opis'),

                Forms\Components\TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->minValue(0)
                    ->step(0.01)
                    ->label('Cena'),

                Forms\Components\FileUpload::make('photos')
                    ->multiple()
                    ->maxFiles(10)
                    ->maxSize(204800) // 20MB in KB
                    ->image()
                    ->imageEditor()
                    ->columnSpanFull()
                    ->label('Zdjęcia')
                    ->disk('s3')
                    ->directory('weapons'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nazwa'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    })
                    ->label('Opis'),

                Tables\Columns\TextColumn::make('price')
                    ->money('USD')
                    ->sortable()
                    ->label('Cena'),

                Tables\Columns\ImageColumn::make('photos')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->limitedRemainingText()
                    ->label('Zdjęcia')
                    ->getStateUsing(function ($record) {
                        if (!$record->photos) {
                            return [];
                        }
                        return collect($record->photos)->map(function ($photo) {
                            return Storage::disk('s3')->url($photo);
                        })->toArray();
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListWeapons::route('/'),
            'create' => Pages\CreateWeapon::route('/create'),
            'edit' => Pages\EditWeapon::route('/{record}/edit'),
        ];
    }
}
