<?php

namespace App\Actions\Transactions;

use App\Enums\RevisionAction;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateTransaction
{
    /**
     * The columns an edit may reach. `user_id` and `department_id` are deliberately
     * absent: who a transaction belongs to, and the department snapshot taken from
     * them, are settled at creation and never re-decided by an editor.
     */
    private const UPDATABLE = ['type', 'amount_minor', 'currency', 'occurred_on', 'category_id', 'note', 'quantity_kg', 'quantity', 'quantity_unit', 'is_market_purchase'];

    public function __construct(private readonly RecordRevision $recordRevision) {}

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<int, int>|null  $dimensionValues  null when the caller left the dimensions alone
     */
    public function handle(Transaction $transaction, array $changes, ?array $dimensionValues, User $actor): Transaction
    {
        $this->assertAmountIsWritable($transaction, $changes);

        if (array_key_exists('quantity_kg', $changes) && ! array_key_exists('quantity', $changes)) {
            $changes['quantity'] = $changes['quantity_kg'];
            $changes['quantity_unit'] = $changes['quantity_kg'] !== null ? 'kg' : null;
        }

        if (array_key_exists('quantity', $changes) || array_key_exists('quantity_unit', $changes)) {
            $quantity = Quantity::normalize($changes['quantity'] ?? null);
            $unit = Quantity::unit($quantity, $changes['quantity_unit'] ?? null);
            $changes['quantity'] = $quantity;
            $changes['quantity_unit'] = $unit;
            $changes['quantity_kg'] = $unit === 'kg' ? $quantity : null;
        }

        return DB::transaction(function () use ($transaction, $changes, $dimensionValues, $actor): Transaction {
            $transaction->fill(Arr::only($changes, self::UPDATABLE))->save();

            if ($dimensionValues !== null) {
                $transaction->syncDimensionValues($dimensionValues);
                $transaction->load('dimensionValues');
            }

            $this->recordRevision->handle($transaction, RevisionAction::Updated, $actor);

            return $transaction->load(['category', 'user', 'department', 'dimensionValues.dimension']);
        });
    }

    /**
     * The invariants `CreateTransaction` asserts on the way in, asserted here too. The form
     * request checks the same things so an HTTP caller gets a 422 rather than this
     * exception, but a write should not depend on its caller having checked. There is one
     * caller today; the same asymmetry is what let the bot write around the field rules
     * until that was closed, and this action fills `amount_minor` straight from `$changes`.
     *
     * @param  array<string, mixed>  $changes
     */
    private function assertAmountIsWritable(Transaction $transaction, array $changes): void
    {
        $currency = $changes['currency'] ?? $transaction->currency;

        if (! is_string($currency) || ! Money::isSupported($currency)) {
            throw new InvalidArgumentException('Transaction currency is not supported.');
        }

        // A currency on its own silently reinterprets the minor units already stored.
        if (array_key_exists('currency', $changes) && ! array_key_exists('amount_minor', $changes)) {
            throw new InvalidArgumentException('Transaction currency cannot change without the amount it applies to.');
        }

        if (! array_key_exists('amount_minor', $changes)) {
            return;
        }

        $amountMinor = $changes['amount_minor'];

        if (! is_int($amountMinor)) {
            throw new InvalidArgumentException('Transaction amount must be an integer number of minor units.');
        }

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                "Transaction amount `{$amountMinor}` {$currency} is not a positive amount in minor units."
            );
        }

        if ($amountMinor > Money::MAX_MINOR) {
            throw new InvalidArgumentException(
                "Transaction amount `{$amountMinor}` {$currency} exceeds the maximum of ".Money::MAX_MINOR.' minor units.'
            );
        }
    }
}
