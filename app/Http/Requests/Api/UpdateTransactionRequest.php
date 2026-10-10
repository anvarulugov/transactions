<?php

namespace App\Http\Requests\Api;

use App\Enums\TransactionType;
use App\Http\Requests\Api\Concerns\ResolvesActor;
use App\Http\Requests\Api\Concerns\ValidatesTransactionFields;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    use ResolvesActor, ValidatesTransactionFields;

    public function authorize(): bool
    {
        return $this->actor()->can('update', $this->transaction());
    }

    /**
     * Only the fields an editor may touch. `user_id` and `department_id` are not listed
     * and are not read anywhere below, so a client cannot reassign a transaction to
     * another person or department by sending them.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->transactionFieldRules('sometimes');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateCurrencyComesWithAnAmount($validator);

            if ($this->filled('amount') && ! $validator->errors()->hasAny(['amount', 'currency'])) {
                $this->validateAmountInMinorUnits(
                    $validator,
                    $this->string('amount')->toString(),
                    $this->effectiveCurrency(),
                );
            }

            if (($this->has('type') || $this->has('category_id')) && ! $validator->errors()->hasAny(['type', 'category_id'])) {
                $this->validateCategoryAcceptsType($validator, $this->effectiveCategoryId(), $this->effectiveType());
            }

            if ($this->has('dimension_values')) {
                $this->validateDimensionValues($validator);
            }
        });
    }

    /** @return array<string, mixed> */
    public function changes(): array
    {
        $changes = [];

        if ($this->has('type')) {
            $changes['type'] = TransactionType::from($this->string('type')->toString());
        }

        if ($this->filled('amount')) {
            $changes['currency'] = $this->effectiveCurrency();
            $changes['amount_minor'] = Money::toMinor($this->string('amount')->toString(), $this->effectiveCurrency());
        }

        if ($this->has('occurred_on')) {
            $changes['occurred_on'] = CarbonImmutable::parse($this->string('occurred_on')->toString());
        }

        if ($this->has('category_id')) {
            $changes['category_id'] = $this->integer('category_id');
        }

        if ($this->has('note')) {
            $changes['note'] = $this->filled('note') ? $this->string('note')->toString() : null;
        }

        if ($this->has('quantity_kg')) {
            $changes['quantity_kg'] = $this->filled('quantity_kg') ? $this->string('quantity_kg')->toString() : null;
        }

        foreach (['quantity', 'quantity_unit'] as $field) {
            if ($this->has($field)) {
                $changes[$field] = $this->filled($field) ? $this->string($field)->toString() : null;
            }
        }

        if ($this->has('is_market_purchase')) {
            $changes['is_market_purchase'] = $this->boolean('is_market_purchase');
        }

        return $changes;
    }

    /** @return array<int, int>|null */
    public function submittedDimensionValues(): ?array
    {
        return $this->has('dimension_values') ? $this->dimensionValues() : null;
    }

    public function transaction(): Transaction
    {
        /** @var Transaction $transaction */
        $transaction = $this->route('transaction');

        return $transaction;
    }

    /**
     * A currency on its own would silently reinterpret the stored minor units, so it is
     * only accepted together with the amount it applies to.
     */
    private function validateCurrencyComesWithAnAmount(Validator $validator): void
    {
        if (! $this->has('currency') || $this->filled('amount')) {
            return;
        }

        $validator->errors()->add('amount', __('errors.amount_required_with_currency'));
    }

    private function effectiveCurrency(): string
    {
        return $this->filled('currency')
            ? $this->string('currency')->toString()
            : $this->transaction()->currency;
    }

    private function effectiveCategoryId(): int
    {
        return $this->filled('category_id')
            ? $this->integer('category_id')
            : $this->transaction()->category_id;
    }

    private function effectiveType(): ?TransactionType
    {
        if (! $this->filled('type')) {
            return $this->transaction()->type;
        }

        return TransactionType::tryFrom($this->string('type')->toString());
    }
}
