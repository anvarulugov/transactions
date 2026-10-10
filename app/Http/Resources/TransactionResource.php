<?php

namespace App\Http\Resources;

use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount_minor' => $this->amount_minor,
            'amount' => Money::toDecimal($this->amount_minor, $this->currency),
            'currency' => $this->currency,
            'occurred_on' => $this->occurred_on->toDateString(),
            'note' => $this->note,
            'is_market_purchase' => $this->is_market_purchase,
            'receipt_id' => $this->receipt_id,
            'quantity_kg' => $this->quantity_kg,
            'quantity' => $this->quantity ?? $this->quantity_kg,
            'quantity_unit' => $this->quantity_unit ?? ($this->quantity_kg !== null ? 'kg' : null),
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ] : null,
            'dimension_values' => $this->dimensionValues->map(fn (DimensionValue $value) => [
                'dimension_id' => $value->pivot->getAttribute('dimension_id'),
                'dimension_key' => $value->dimension->key,
                'value_id' => $value->id,
                'value_name' => $value->name,
            ])->all(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
