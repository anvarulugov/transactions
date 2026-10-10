<?php

namespace App\DataObjects;

use App\Enums\TransactionType;
use Carbon\CarbonImmutable;

readonly class TransactionInput
{
    /**
     * `$departmentId` is the department snapshot of the user the transaction belongs
     * to, never the department of whoever performs the write. Callers resolve it from
     * `$userId` so the action never has to infer it.
     *
     * @param  array<int, int>  $dimensionValues
     */
    public function __construct(
        public int $userId,
        public ?int $departmentId,
        public TransactionType $type,
        public string $amount,
        public string $currency,
        public CarbonImmutable $occurredOn,
        public int $categoryId,
        public ?string $note = null,
        public array $dimensionValues = [],
        public ?string $idempotencyKey = null,
        public ?string $quantityKg = null,
        public ?string $quantity = null,
        public ?string $quantityUnit = null,
        public bool $isMarketPurchase = false,
        public ?int $receiptId = null,
    ) {}
}
