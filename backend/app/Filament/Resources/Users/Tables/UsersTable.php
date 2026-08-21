<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(), TextColumn::make('email')->searchable(), TextColumn::make('status')->badge(), TextColumn::make('role')->badge(), TextColumn::make('wallet.balance_amount')->money('IDR')->label('Balance'), TextColumn::make('connected_sites_count')->counts('connectedSites')->label('Sites'), TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('manageBalance')
                    ->label('Kelola Saldo')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->modalHeading(fn (User $record) => 'Kelola Saldo: '.$record->email)
                    ->modalDescription(fn (User $record) => 'Saldo saat ini: Rp'.number_format((float) ($record->wallet?->balance_amount ?? 0), 0, ',', '.'))
                    ->schema([
                        Select::make('operation')->label('Perubahan Saldo')->options(['add' => 'Tambah Saldo', 'deduct' => 'Potong Saldo'])->default('add')->required(),
                        TextInput::make('amount')->label('Nominal')->numeric()->prefix('Rp')->minValue(1)->required(),
                        TextInput::make('description')->label('Catatan')->default('Penyesuaian saldo oleh admin')->maxLength(255)->required(),
                    ])
                    ->action(function (User $record, array $data): void {
                        try {
                            $transaction = app(WalletService::class)->adminAdjustment($record, (float) $data['amount'], $data['operation'], $data['description'], auth()->id());
                            Notification::make()->success()->title('Saldo berhasil diperbarui')->body('Saldo baru: Rp'.number_format((float) $transaction->balance_after, 0, ',', '.'))->send();
                        } catch (Throwable $exception) {
                            Notification::make()->danger()->title('Saldo gagal diperbarui')->body($exception->getMessage())->send();
                        }
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
