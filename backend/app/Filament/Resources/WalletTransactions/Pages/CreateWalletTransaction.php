<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Models\User;
use App\Services\WalletService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWalletTransaction extends CreateRecord
{
    protected static string $resource = WalletTransactionResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(WalletService::class)->credit(User::findOrFail($data['user_id']), (float) $data['amount'], $data['type'], $data['description'], 'admin-'.str()->uuid(), auth()->id());
    }
}
