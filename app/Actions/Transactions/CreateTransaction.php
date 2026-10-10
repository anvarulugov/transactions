<?php

namespace App\Actions\Transactions;

use App\DataObjects\TransactionInput;
use App\Enums\RevisionAction;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\ReceiptMoney;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateTransaction
{
    public function __construct(private readonly RecordRevision $recordRevision) {}

    public function handle(TransactionInput $input, User $actor): Transaction
    {
        $amountMinor = $this->convertAmount($input);

        $quantity = Quantity::normalize($input->quantity ?? $input->quantityKg);
        $unit = Quantity::unit($quantity, $input->quantityUnit ?? ($input->quantityKg !== null ? 'kg' : null));

        return DB::transaction(function () use ($input, $actor, $amountMinor, $quantity, $unit): Transaction {
            $transaction = Transaction::create([
                'user_id' => $input->userId,
                'department_id' => $input->departmentId,
                'type' => $input->type,
                'amount_minor' => $amountMinor,
                'currency' => $input->currency,
                'occurred_on' => $input->occurredOn,
                'category_id' => $input->categoryId,
                'note' => $input->note,
                'is_market_purchase' => $input->type === TransactionType::Expense && $input->isMarketPurchase,
                'receipt_id' => $input->receiptId,
                'quantity' => $quantity,
                'quantity_unit' => $unit,
                'quantity_kg' => $unit === 'kg' ? $quantity : null,
                'created_by' => $actor->id,
                'idempotency_key' => $input->idempotencyKey,
            ]);

            $transaction->syncDimensionValues($input->dimensionValues);

            $this->recordRevision->handle($transaction, RevisionAction::Created, $actor);

            return $transaction->load(['category', 'user', 'department', 'dimensionValues.dimension']);
        });
    }

    /**
     * Order independent invariants every caller must satisfy, HTTP or not. The form
     * request checks the same things so that HTTP callers get a 422 instead of this
     * exception, but the row can never be written without them holding.
     */
    private function convertAmount(TransactionInput $input): int
    {
        if (! Money::isSupported($input->currency)) {
            throw new InvalidArgumentException("Transaction currency `{$input->currency}` is not supported.");
        }

        if (preg_match(Money::AMOUNT_PATTERN, $input->amount) !== 1) {
            throw new InvalidArgumentException(
                "Transaction amount `{$input->amount}` is not a decimal of at most ".Money::MAX_INTEGER_DIGITS.' digits.'
            );
        }

        $amountMinor = $input->receiptId !== null
            ? ReceiptMoney::minor($input->amount, $input->currency)
            : Money::toMinor($input->amount, $input->currency);

        if ($amountMinor <= 0) {
            throw new InvalidArgumentException(
                "Transaction amount `{$input->amount}` {$input->currency} is not a positive amount in minor units."
            );
        }

        if ($amountMinor > Money::MAX_MINOR) {
            throw new InvalidArgumentException(
                "Transaction amount `{$input->amount}` {$input->currency} exceeds the maximum of ".Money::MAX_MINOR.' minor units.'
            );
        }

        return $amountMinor;
    }
}
