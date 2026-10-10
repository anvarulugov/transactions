<?php

namespace App\Http\Controllers\Api;

use App\Actions\Transactions\CreateTransaction;
use App\DataObjects\TransactionInput;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Category;
use App\Models\Receipt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Receipts\ReceiptOcr;
use App\Services\Receipts\ReceiptParser;
use App\Support\ReceiptMoney;
use App\Validation\TransactionFieldValidator;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class ReceiptController extends Controller
{
    public function store(Request $request, ReceiptOcr $ocr, ReceiptParser $parser): JsonResponse
    {
        $actor = $this->actor($request);
        abort_if($actor->role === UserRole::Owner, 403);
        $request->validate(['image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000']], ['image.required' => __('receipts.read_failed'), 'image.image' => __('receipts.read_failed'), 'image.mimes' => __('receipts.read_failed'), 'image.max' => __('receipts.read_failed'), 'image.dimensions' => __('receipts.read_failed')]);
        /** @var UploadedFile $file */
        $file = $request->file('image');
        $hash = hash_file('sha256', $file->getPathname());
        $existing = Receipt::query()->where('user_id', $actor->id)->where('image_hash', $hash)->first();
        if ($existing !== null) {
            return $this->draftResponse($existing);
        }
        $path = $file->store('receipts', 'local');
        if (! is_string($path)) {
            throw ValidationException::withMessages(['image' => __('receipts.read_failed')]);
        }
        try {
            $text = $ocr->read(Storage::disk('local')->path($path));
            $draft = $parser->parse($text);
            $categories = Category::query()->where('is_active', true)->get(['id', 'name', 'applies_to']);
            foreach ($draft['items'] as &$item) {
                $match = $categories->first(fn (Category $category): bool => in_array($category->applies_to->value, ['expense', 'both'], true) && mb_strtolower(trim($category->name)) === mb_strtolower(trim($item['name'])));
                $item['category_id'] = $match?->id;
            }
            unset($item);
            $receipt = Receipt::create(['user_id' => $actor->id, 'image_path' => $path, 'image_hash' => $hash, 'ocr_text' => $text, 'draft' => $draft]);
        } catch (UniqueConstraintViolationException $exception) {
            Storage::disk('local')->delete($path);
            $receipt = Receipt::query()->where('user_id', $actor->id)->where('image_hash', $hash)->first();
            if ($receipt === null) {
                throw $exception;
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);
            throw ValidationException::withMessages(['image' => __('receipts.read_failed')]);
        }

        return $this->draftResponse($receipt);
    }

    public function image(Request $request, Receipt $receipt): BinaryFileResponse
    {
        Gate::authorize('view', $receipt);
        abort_unless(Storage::disk('local')->exists($receipt->image_path), 404);

        return response()->file(Storage::disk('local')->path($receipt->image_path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function confirm(Request $request, Receipt $receipt, TransactionFieldValidator $fields, CreateTransaction $create): JsonResponse
    {
        $actor = $this->actor($request);
        abort_unless($receipt->user_id === $actor->id && $actor->role !== UserRole::Owner, 403);
        $data = $request->validate([
            'confirmed' => ['required', 'accepted'],
            'currency' => ['required', 'string', Rule::exists('currencies', 'code')->where('is_active', true)],
            'occurred_on' => ['required', 'date', 'before_or_equal:'.now()->addDay()->toDateString()],
            'total' => ['required', 'string'],
            'dimension_values' => ['sometimes', 'array'],
            'dimension_values.*' => ['integer'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.name' => ['required', 'string', 'max:150'],
            'items.*.amount' => ['required', 'string'],
            'items.*.category_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'string'],
            'items.*.quantity_unit' => ['required', Rule::in(['kg', 'litr', 'dona'])],
        ], array_fill_keys(['required', 'accepted', 'string', 'array', 'min', 'max', 'exists', 'in', 'integer', 'date', 'before_or_equal'], __('receipts.invalid_items')));
        $rows = [];
        $sum = 0;
        foreach ($data['items'] as $index => $item) {
            $row = [...$item, 'type' => 'expense', 'currency' => $data['currency'], 'occurred_on' => $data['occurred_on'], 'dimension_values' => $data['dimension_values'] ?? []];
            $validator = $fields->forCreate($row);
            if ($validator->fails()) {
                $errors = [];
                foreach ($validator->errors()->messages() as $key => $messages) {
                    $errors["items.{$index}.{$key}"] = [__('receipts.invalid_items')];
                }
                throw ValidationException::withMessages($errors);
            }
            $sum += ReceiptMoney::minor($item['amount'], $data['currency']);
            $rows[] = $row;
        }
        if ($sum !== ReceiptMoney::minor($data['total'], $data['currency'])) {
            throw ValidationException::withMessages(['total' => __('receipts.total_mismatch')]);
        }
        $transactions = DB::transaction(function () use ($receipt, $actor, $data, $rows, $create) {
            $locked = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);
            if ($locked->confirmed_at !== null) {
                return Transaction::query()->where('receipt_id', $receipt->id)->orderBy('id')->get();
            }
            $result = collect();
            foreach ($rows as $row) {
                $result->push($create->handle(new TransactionInput(
                    userId: $actor->id, departmentId: $actor->department_id, type: TransactionType::Expense,
                    amount: $row['amount'], currency: $row['currency'], occurredOn: CarbonImmutable::parse($row['occurred_on']),
                    categoryId: $row['category_id'], note: $row['name'], dimensionValues: TransactionFieldValidator::dimensionValues($row['dimension_values']),
                    idempotencyKey: (string) Str::uuid(), quantity: $row['quantity'], quantityUnit: $row['quantity_unit'],
                    isMarketPurchase: true, receiptId: $receipt->id,
                ), $actor));
            }
            $locked->update(['confirmed_at' => now(), 'confirmed_items' => $data]);

            return $result;
        });

        return response()->json(['data' => TransactionResource::collection($transactions), 'receipt_id' => $receipt->id]);
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }

    private function draftResponse(Receipt $receipt): JsonResponse
    {
        return response()->json(['data' => ['id' => $receipt->id, 'text' => $receipt->ocr_text, ...$receipt->draft, 'confirmed' => $receipt->confirmed_at !== null]]);
    }
}
