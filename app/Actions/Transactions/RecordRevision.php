<?php

namespace App\Actions\Transactions;

use App\Enums\RevisionAction;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;

class RecordRevision
{
    public function handle(Transaction $transaction, RevisionAction $action, User $actor): void
    {
        $transaction->loadMissing('dimensionValues');

        $transaction->revisions()->create([
            'action' => $action,
            'actor_id' => $actor->id,
            'snapshot' => [
                'type' => $transaction->type->value,
                'amount_minor' => $transaction->amount_minor,
                'currency' => $transaction->currency,
                'occurred_on' => $transaction->occurred_on->toDateString(),
                'category_id' => $transaction->category_id,
                'note' => $transaction->note,
                'is_market_purchase' => $transaction->is_market_purchase,
                'receipt_id' => $transaction->receipt_id,
                'quantity_kg' => $transaction->quantity_kg,
                'quantity' => $transaction->quantity ?? $transaction->quantity_kg,
                'quantity_unit' => $transaction->quantity_unit ?? ($transaction->quantity_kg !== null ? 'kg' : null),
                'user_id' => $transaction->user_id,
                'department_id' => $transaction->department_id,
                'dimension_values' => $transaction->dimensionValues
                    ->mapWithKeys(fn (DimensionValue $value) => [
                        $value->pivot->getAttribute('dimension_id') => $value->id,
                    ])
                    ->all(),
                'deleted_at' => $transaction->deleted_at?->toIso8601String(),
            ],
        ]);
    }
}
