<?php

namespace App\Filament\Resources;

use App\Enums\TransactionType;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\TransactionResource\Pages\ViewTransaction;
use App\Filament\Resources\TransactionResource\RelationManagers\RevisionsRelationManager;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\TransactionScope;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Read only, for admins too: the only paths that may write a transaction are the API
 * and the bot, since those are the ones that run the domain validation and append to
 * the revision log. Nothing here registers a create or edit page.
 */
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /**
     * Built on `Transaction::query()` rather than `parent::getEloquentQuery()`: the parent
     * only adds tenancy scoping, and this panel has none, so calling it would add nothing
     * but a generic return type Pint cannot render without mangling the docblock.
     *
     * @return Builder<Transaction>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = Transaction::query();
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        return TransactionScope::apply($query, $user);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('occurred_on')
                ->date(),
            TextEntry::make('type'),
            TextEntry::make('user.name')
                ->label('Staff'),
            TextEntry::make('department.name')
                ->label('Department')
                ->placeholder('—'),
            TextEntry::make('category.name')
                ->label('Category'),
            TextEntry::make('amount_minor')
                ->label('Amount')
                ->formatStateUsing(fn (Transaction $record): string => Money::toDecimal($record->amount_minor, $record->currency).' '.$record->currency),
            TextEntry::make('is_market_purchase')->label('Bozorlik')->formatStateUsing(fn (bool $state): string => $state ? 'Ha' : 'Yo‘q'),
            TextEntry::make('receipt_id')->label('Xarid cheki')->placeholder('—')->url(fn (Transaction $record): ?string => $record->receipt_id ? '/admin/receipts/'.$record->receipt_id.'/image' : null)->openUrlInNewTab(),
            TextEntry::make('quantity')
                ->label('Miqdor')
                ->formatStateUsing(fn (Transaction $record): ?string => $record->quantity !== null ? rtrim(rtrim($record->quantity, '0'), '.').' '.$record->quantity_unit : null)
                ->placeholder('—'),
            TextEntry::make('note')
                ->placeholder('—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('occurred_on')
                    ->date()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Staff'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—'),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('amount_minor')
                    ->label('Amount')
                    ->formatStateUsing(fn (Transaction $record): string => Money::toDecimal($record->amount_minor, $record->currency).' '.$record->currency)
                    ->sortable(),
                TextColumn::make('is_market_purchase')->label('Bozorlik')->formatStateUsing(fn (bool $state): string => $state ? 'Ha' : '—'),
                TextColumn::make('receipt_id')->label('Chek')->placeholder('—')->url(fn (Transaction $record): ?string => $record->receipt_id ? '/admin/receipts/'.$record->receipt_id.'/image' : null)->openUrlInNewTab(),
                TextColumn::make('quantity')
                    ->label('Miqdor')
                    ->formatStateUsing(fn (Transaction $record): ?string => $record->quantity !== null ? rtrim(rtrim($record->quantity, '0'), '.').' '.$record->quantity_unit : null)
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('note')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->filters([
                Filter::make('occurred_on')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('to'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('occurred_on', '>=', $date))
                        ->when($data['to'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('occurred_on', '<=', $date))),
                SelectFilter::make('type')
                    ->options(TransactionType::class),
                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                SelectFilter::make('currency')
                    ->options(fn (): array => Currency::query()->orderBy('sort')->pluck('code', 'code')->all()),
                SelectFilter::make('user_id')
                    ->label('Staff')
                    ->relationship('user', 'name'),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
            'view' => ViewTransaction::route('/{record}'),
        ];
    }
}
