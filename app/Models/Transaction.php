<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'type',
        'amount_minor',
        'currency',
        'occurred_on',
        'category_id',
        'note',
        'quantity_kg',
        'quantity',
        'quantity_unit',
        'is_market_purchase',
        'receipt_id',
        'created_by',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'occurred_on' => 'date',
            'amount_minor' => 'integer',
            'quantity_kg' => 'decimal:3',
            'quantity' => 'decimal:3',
            'is_market_purchase' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return HasMany<TransactionRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(TransactionRevision::class);
    }

    /** @return BelongsToMany<DimensionValue, $this> */
    public function dimensionValues(): BelongsToMany
    {
        return $this->belongsToMany(DimensionValue::class, 'transaction_dimension_values')
            ->withPivot('dimension_id');
    }

    /**
     * The pivot carries the dimension the value was chosen for, which is what enforces
     * one value per dimension. Creating and editing go through here so the two cannot
     * write the pivot differently.
     *
     * @param  array<int, int>  $values  dimension id => dimension value id
     */
    public function syncDimensionValues(array $values): void
    {
        $payload = [];

        foreach ($values as $dimensionId => $valueId) {
            $payload[$valueId] = ['dimension_id' => $dimensionId];
        }

        $this->dimensionValues()->sync($payload);
    }
}
