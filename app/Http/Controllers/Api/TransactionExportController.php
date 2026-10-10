<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TransactionFilterRequest;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Support\Money;
use App\Support\TransactionScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionExportController extends Controller
{
    /** How many rows one chunk of the export reads from the database at a time. */
    private const CHUNK_SIZE = 500;

    /** A cell starting with one of these is read as a formula by Excel and Google Sheets. */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function __invoke(TransactionFilterRequest $request): StreamedResponse
    {
        $dimensions = $this->activeDimensions();

        $query = Transaction::query()->with(['category', 'user', 'department', 'dimensionValues.dimension']);

        TransactionScope::apply($query, $request->actor());
        $request->filters()->apply($query);

        return response()->streamDownload(
            function () use ($query, $dimensions): void {
                $this->stream($query, $dimensions);
            },
            'transactions.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  Collection<int, Dimension>  $dimensions
     */
    private function stream(Builder $query, Collection $dimensions): void
    {
        $handle = fopen('php://output', 'w');

        if ($handle === false) {
            return;
        }

        fputcsv($handle, $this->header($dimensions));

        $query->chunkById(
            self::CHUNK_SIZE,
            function (Collection $transactions) use ($handle, $dimensions): void {
                foreach ($transactions as $transaction) {
                    fputcsv($handle, $this->row($transaction, $dimensions));
                }
            },
            'transactions.id',
            'id',
        );

        fclose($handle);
    }

    /** @return Collection<int, Dimension> */
    private function activeDimensions(): Collection
    {
        return Dimension::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, Dimension>  $dimensions
     * @return array<int, string>
     */
    private function header(Collection $dimensions): array
    {
        $header = ['date', 'type', 'amount', 'currency', 'category', 'staff', 'department', 'note'];

        foreach ($dimensions as $dimension) {
            $header[] = $dimension->key;
        }

        $header[] = 'quantity_kg';
        $header[] = 'quantity';
        $header[] = 'quantity_unit';
        $header[] = 'is_market_purchase';
        $header[] = 'receipt_id';

        return $header;
    }

    /**
     * @param  Collection<int, Dimension>  $dimensions
     * @return array<int, string>
     */
    private function row(Transaction $transaction, Collection $dimensions): array
    {
        $valuesByDimension = $transaction->dimensionValues
            ->keyBy(fn (DimensionValue $value): int => (int) $value->pivot->getAttribute('dimension_id'));

        $row = [
            $transaction->occurred_on->toDateString(),
            $transaction->type->value,
            Money::toDecimal($transaction->amount_minor, $transaction->currency),
            $transaction->currency,
            $this->neutralize($transaction->category->name),
            $this->neutralize($transaction->user->name),
            $transaction->department ? $this->neutralize($transaction->department->name) : '',
            $transaction->note !== null ? $this->neutralize($transaction->note) : '',
        ];

        foreach ($dimensions as $dimension) {
            $value = $valuesByDimension->get($dimension->id);
            $row[] = $value instanceof DimensionValue ? $this->neutralize($value->name) : '';
        }

        $row[] = $transaction->quantity_kg ?? '';
        $row[] = $transaction->quantity ?? $transaction->quantity_kg ?? '';
        $row[] = $transaction->quantity_unit ?? ($transaction->quantity_kg !== null ? 'kg' : '');

        $row[] = $transaction->is_market_purchase ? '1' : '0';
        $row[] = $transaction->receipt_id ?? '';

        return $row;
    }

    private function neutralize(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
