<?php

namespace App\Http\Requests\Api\Concerns;

use App\Enums\TransactionType;
use App\Validation\TransactionFieldValidator;
use Illuminate\Contracts\Validation\Validator;

/**
 * The request side of the transaction rules: it reads the fields off the request and
 * hands them to `TransactionFieldValidator`, which owns the rules themselves. A writer
 * with no request behind it goes to that object directly, so every writer runs the same
 * checks.
 */
trait ValidatesTransactionFields
{
    /**
     * @param  'required'|'sometimes'  $presence
     * @return array<string, array<int, mixed>>
     */
    protected function transactionFieldRules(string $presence): array
    {
        return $this->transactionFieldValidator()->rules($presence);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quantity.string' => __('errors.quantity_invalid'),
            'quantity.regex' => __('errors.quantity_invalid'),
            'quantity.not_regex' => __('errors.quantity_invalid'),
            'quantity.required_with' => __('errors.quantity_invalid'),
            'quantity.prohibits' => __('errors.quantity_invalid'),
            'quantity_unit.required_with' => __('errors.quantity_unit_invalid'),
            'quantity_unit.in' => __('errors.quantity_unit_invalid'),
            'quantity_unit.string' => __('errors.quantity_unit_invalid'),
            'quantity_unit.prohibits' => __('errors.quantity_unit_invalid'),
            'quantity_kg.string' => __('errors.quantity_kg_invalid'),
            'quantity_kg.regex' => __('errors.quantity_kg_invalid'),
            'quantity_kg.not_regex' => __('errors.quantity_kg_invalid'),
        ];
    }

    /** @return array<int, int> */
    public function dimensionValues(): array
    {
        return TransactionFieldValidator::dimensionValues($this->input('dimension_values'));
    }

    protected function applyCreateChecks(Validator $validator): void
    {
        $this->transactionFieldValidator()->applyCreateChecks($validator, $this->validationData());
    }

    protected function validateAmountInMinorUnits(Validator $validator, string $amount, string $currency): void
    {
        $this->transactionFieldValidator()->checkAmountInMinorUnits($validator, $amount, $currency);
    }

    protected function validateCategoryAcceptsType(Validator $validator, ?int $categoryId, ?TransactionType $type): void
    {
        $this->transactionFieldValidator()->checkCategoryAcceptsType($validator, $categoryId, $type);
    }

    protected function validateDimensionValues(Validator $validator): void
    {
        $this->transactionFieldValidator()->checkDimensionValues($validator, $this->dimensionValues());
    }

    protected function transactionFieldValidator(): TransactionFieldValidator
    {
        return app(TransactionFieldValidator::class);
    }
}
