<?php

namespace App\Validation;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Support\Concerns\ReadsArrayValues;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Rule;

/**
 * The domain rules a transaction has to satisfy whichever way it is written. The form
 * requests run them over the HTTP payload and the bot runs them over a draft payload,
 * through the same object, so a writer that never touches a form request still cannot
 * store a category that rejects its type or skip a required dimension.
 */
class TransactionFieldValidator
{
    use ReadsArrayValues;

    /**
     * The declarative half of those rules. Creating demands every field, editing takes
     * whichever ones it is given, and that presence marker is the only difference
     * between the two, so a change to the note length or the future date window cannot
     * reach one path without the other.
     *
     * @param  'required'|'sometimes'  $presence
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $presence): array
    {
        return [
            'type' => [$presence, Rule::enum(TransactionType::class)],
            'amount' => [$presence, 'regex:'.Money::AMOUNT_PATTERN, 'not_in:0,0.0,0.00'],
            'currency' => [$presence, 'string', 'size:3', Rule::exists('currencies', 'code')->where('is_active', true)],
            'occurred_on' => [$presence, 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'category_id' => [$presence, 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'is_market_purchase' => ['sometimes', 'boolean'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'quantity' => ['nullable', 'required_with:quantity_unit', 'prohibits:quantity_kg', 'string', 'regex:'.Quantity::PATTERN, 'not_regex:/^0(?:\.0+)?$/D'],
            'quantity_unit' => ['nullable', 'required_with:quantity', 'prohibits:quantity_kg', 'string', Rule::in(Quantity::UNITS)],
            'quantity_kg' => ['sometimes', 'nullable', 'string', 'regex:'.Quantity::PATTERN, 'not_regex:/^0(?:\.0+)?$/D'],
            'dimension_values' => ['sometimes', 'array'],
            'dimension_values.*' => ['integer'],
        ];
    }

    /**
     * A validator for a caller that has no form request to hang the rules on. It runs the
     * declarative rules and the same after callbacks a create goes through over HTTP.
     *
     * @param  array<string, mixed>  $data
     */
    public function forCreate(array $data): Validator
    {
        $validator = ValidatorFactory::make($data, $this->rules('required'));

        $this->applyCreateChecks($validator, $data);

        return $validator;
    }

    /**
     * Every check a create has to pass beyond the declarative rules, in one place so the
     * HTTP path and the bot path cannot end up running different ones. Each check is
     * skipped when the field it reads already failed, so a caller is told what is wrong
     * with the value it sent instead of what went wrong deeper in.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyCreateChecks(Validator $validator, array $data): void
    {
        $validator->after(function (Validator $validator) use ($data): void {
            if (! $validator->errors()->hasAny(['amount', 'currency'])) {
                $this->checkAmountInMinorUnits($validator, $this->string($data, 'amount'), $this->string($data, 'currency'));
            }

            if (! $validator->errors()->hasAny(['type', 'category_id'])) {
                $this->checkCategoryAcceptsType(
                    $validator,
                    $this->integer($data, 'category_id'),
                    TransactionType::tryFrom($this->string($data, 'type')),
                );
            }

            $this->checkDimensionValues($validator, self::dimensionValues($data['dimension_values'] ?? null));
        });
    }

    /**
     * A dimension map as the domain holds it: dimension id => dimension value id, with
     * anything that is not a pair of numbers dropped.
     *
     * @return array<int, int>
     */
    public static function dimensionValues(mixed $submitted): array
    {
        if (! is_array($submitted)) {
            return [];
        }

        $values = [];

        foreach ($submitted as $dimensionId => $valueId) {
            if (! is_numeric($dimensionId) || ! is_numeric($valueId)) {
                continue;
            }

            $values[(int) $dimensionId] = (int) $valueId;
        }

        return $values;
    }

    /**
     * The entries of a dimension map whose value can still be chosen today: it exists, it
     * is active, and its own dimension is active.
     *
     * A retired value carried forward is not an error to report, it is a dead end. The
     * bot only prompts for dimensions whose key is absent, so a key that is present but
     * points at a retired value is never asked about, offers no button, and fails the
     * write on every attempt. Dropping it turns a permanent lockout into a question the
     * staff member can answer, or, for a dimension that is not required, into nothing.
     *
     * Whether the value belongs to the dimension it is filed under is deliberately not
     * decided here. That pairing is a validation rule, and `checkSubmittedDimensionValues`
     * refuses a mismatch rather than quietly discarding it; this only drops what nobody
     * could pick any more.
     *
     * @param  array<int, int>  $values
     * @return array<int, int>
     */
    public static function activeDimensionValues(array $values): array
    {
        if ($values === []) {
            return [];
        }

        $choosable = DimensionValue::query()
            ->whereIn('dimension_values.id', $values)
            ->where('dimension_values.is_active', true)
            ->whereRelation('dimension', 'is_active', true)
            ->pluck('id')
            ->map(fn (mixed $id) => (int) $id)
            ->all();

        return array_filter($values, fn (int $valueId) => in_array($valueId, $choosable, true));
    }

    /**
     * The same invariant `CreateTransaction` asserts, checked here so an HTTP caller
     * gets a 422 on the amount field instead of an unhandled exception. Amounts with
     * more decimals than the currency carries are still accepted and rounded; what is
     * rejected is an amount that rounds away to nothing, or one large enough to
     * threaten the column.
     */
    public function checkAmountInMinorUnits(Validator $validator, string $amount, string $currency): void
    {
        if (! Money::isSupported($currency)) {
            return;
        }

        $amountMinor = Money::toMinor($amount, $currency);

        if ($amountMinor <= 0) {
            $validator->errors()->add('amount', __('errors.amount_not_positive'));

            return;
        }

        if ($amountMinor > Money::MAX_MINOR) {
            $validator->errors()->add('amount', __('errors.amount_too_large'));
        }
    }

    public function checkCategoryAcceptsType(Validator $validator, ?int $categoryId, ?TransactionType $type): void
    {
        $category = $categoryId === null ? null : Category::find($categoryId);

        if (! $category instanceof Category || ! $type instanceof TransactionType) {
            return;
        }

        if ($category->acceptsType($type)) {
            return;
        }

        $validator->errors()->add('category_id', __('errors.category_rejects_type'));
    }

    /** @param array<int, int> $submitted */
    public function checkDimensionValues(Validator $validator, array $submitted): void
    {
        if ($validator->errors()->hasAny(['dimension_values'])) {
            return;
        }

        $dimensions = Dimension::query()->where('is_active', true)->get()->keyBy('id');

        $this->checkRequiredDimensions($validator, $dimensions, $submitted);
        $this->checkSubmittedDimensionValues($validator, $dimensions, $submitted);
    }

    /**
     * The dimensions an active required dimension has no value for. The bot asks for
     * these before it offers to save, instead of letting the write fail.
     *
     * @param  array<int, int>  $submitted
     * @return Collection<int, Dimension>
     */
    public function missingRequiredDimensions(array $submitted): Collection
    {
        return Dimension::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->reject(fn (Dimension $dimension) => array_key_exists($dimension->id, $submitted))
            ->values();
    }

    /**
     * @param  Collection<int, Dimension>  $dimensions
     * @param  array<int, int>  $submitted
     */
    private function checkRequiredDimensions(Validator $validator, Collection $dimensions, array $submitted): void
    {
        $missing = $dimensions
            ->filter(fn (Dimension $dimension) => $dimension->is_required)
            ->reject(fn (Dimension $dimension) => array_key_exists($dimension->id, $submitted));

        foreach ($missing as $dimension) {
            $validator->errors()->add('dimension_values', __('errors.dimension_required', [
                'dimension' => $dimension->name,
            ]));
        }
    }

    /**
     * @param  Collection<int, Dimension>  $dimensions
     * @param  array<int, int>  $submitted
     */
    private function checkSubmittedDimensionValues(Validator $validator, Collection $dimensions, array $submitted): void
    {
        if ($submitted === []) {
            return;
        }

        $values = DimensionValue::query()->whereIn('id', $submitted)->get()->keyBy('id');

        foreach ($submitted as $dimensionId => $valueId) {
            $value = $values->get($valueId);

            if ($dimensions->get($dimensionId) instanceof Dimension
                && $value instanceof DimensionValue
                && $value->is_active
                && $value->dimension_id === $dimensionId) {
                continue;
            }

            $validator->errors()->add('dimension_values', __('errors.dimension_value_invalid'));
        }
    }
}
