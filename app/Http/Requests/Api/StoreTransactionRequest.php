<?php

namespace App\Http\Requests\Api;

use App\DataObjects\TransactionInput;
use App\Enums\TransactionType;
use App\Http\Requests\Api\Concerns\ResolvesActor;
use App\Http\Requests\Api\Concerns\ValidatesTransactionFields;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    use ResolvesActor, ValidatesTransactionFields;

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            ...$this->transactionFieldRules('required'),
            'idempotency_key' => ['nullable', 'uuid'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');

        $this->merge(['idempotency_key' => is_string($key) && $key !== '' ? $key : null]);
    }

    public function withValidator(Validator $validator): void
    {
        $this->applyCreateChecks($validator);
    }

    public function toInput(User $actor): TransactionInput
    {
        return new TransactionInput(
            userId: $actor->id,
            departmentId: $actor->department_id,
            type: TransactionType::from($this->string('type')->toString()),
            amount: $this->string('amount')->toString(),
            currency: $this->string('currency')->toString(),
            occurredOn: CarbonImmutable::parse($this->string('occurred_on')->toString()),
            categoryId: $this->integer('category_id'),
            note: $this->filled('note') ? $this->string('note')->toString() : null,
            dimensionValues: $this->dimensionValues(),
            idempotencyKey: $this->idempotencyKey(),
            quantityKg: $this->filled('quantity_kg') ? $this->string('quantity_kg')->toString() : null,
            quantity: $this->filled('quantity') ? $this->string('quantity')->toString() : null,
            isMarketPurchase: $this->boolean('is_market_purchase'),
            quantityUnit: $this->filled('quantity_unit') ? $this->string('quantity_unit')->toString() : null,
        );
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->input('idempotency_key');

        return is_string($key) && $key !== '' ? $key : null;
    }
}
