<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatsAppInstanceResource\Pages;
use App\Models\WhatsAppInstance;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WhatsAppInstanceResource extends Resource
{
    protected static ?string $model = WhatsAppInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'WhatsApp Instances';

    protected static ?string $modelLabel = 'WhatsApp Instance';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('webhook_url')
                    ->url()
                    ->maxLength(255)
                    ->label('Webhook URL'),

                Forms\Components\Select::make('status')
                    ->options([
                        'creating' => 'Creating',
                        'running' => 'Running',
                        'stopped' => 'Stopped',
                        'error' => 'Error',
                    ])
                    ->disabled()
                    ->default('creating'),

                Forms\Components\TextInput::make('port')
                    ->numeric()
                    ->disabled(),

                Forms\Components\TextInput::make('container_id')
                    ->disabled()
                    ->label('Container ID'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'creating',
                        'success' => 'running',
                        'danger' => 'error',
                        'secondary' => 'stopped',
                    ]),

                Tables\Columns\TextColumn::make('port')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_activity')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Activity'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'creating' => 'Creating',
                        'running' => 'Running',
                        'stopped' => 'Stopped',
                        'error' => 'Error',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('start')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn (WhatsAppInstance $record) => $record->isStopped())
                    ->action(function (WhatsAppInstance $record) {
                        app(\App\Services\DockerService::class)->startContainer($record);
                    }),

                Tables\Actions\Action::make('stop')
                    ->icon('heroicon-o-stop')
                    ->color('danger')
                    ->visible(fn (WhatsAppInstance $record) => $record->isRunning())
                    ->action(function (WhatsAppInstance $record) {
                        app(\App\Services\DockerService::class)->stopContainer($record);
                    }),

                Tables\Actions\Action::make('view_api')
                    ->icon('heroicon-o-link')
                    ->url(fn (WhatsAppInstance $record) => $record->getApiUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (WhatsAppInstance $record) => $record->isRunning()),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListWhatsAppInstances::route('/'),
            'create' => Pages\CreateWhatsAppInstance::route('/create'),
            'edit' => Pages\EditWhatsAppInstance::route('/{record}/edit'),
        ];
    }
}
