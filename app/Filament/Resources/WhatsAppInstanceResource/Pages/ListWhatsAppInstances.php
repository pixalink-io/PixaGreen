<?php

namespace App\Filament\Resources\WhatsAppInstanceResource\Pages;

use App\Filament\Resources\WhatsAppInstanceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppInstances extends ListRecords
{
    protected static string $resource = WhatsAppInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
