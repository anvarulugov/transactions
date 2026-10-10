<?php

use App\Actions\Transactions\CreateTransaction;
use App\Actions\Transactions\RecordRevision;
use App\DataObjects\TransactionInput;
use App\Enums\CategoryAppliesTo;
use App\Enums\RevisionAction;
use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('creates a transaction for the caller', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create(['applies_to' => CategoryAppliesTo::Expense]);

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '120000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'note' => 'taksi',
    ])->assertCreated()
        ->assertJsonPath('data.amount_minor', 120000)
        ->assertJsonPath('data.amount', '120000');

    $transaction = Transaction::sole();

    expect($transaction->user_id)->toBe($user->id)
        ->and($transaction->created_by)->toBe($user->id)
        ->and($transaction->department_id)->toBe($department->id)
        ->and($transaction->revisions()->where('action', RevisionAction::Created)->count())->toBe(1);
});

it('ignores a user_id sent by a staff caller', function () {
    $caller = User::factory()->create();
    $other = User::factory()->create();
    Sanctum::actingAs($caller);
    $category = Category::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'user_id' => $other->id,
    ])->assertCreated();

    expect(Transaction::sole()->user_id)->toBe($caller->id);
});

it('rejects a category that does not accept the type', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create(['applies_to' => CategoryAppliesTo::Expense]);

    $this->postJson('/api/transactions', [
        'type' => 'income',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertStatus(422)->assertJsonValidationErrors('category_id');
});

it('rejects an unsupported currency, a non positive amount and a future date', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $base = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', [...$base, 'currency' => 'XXX'])
        ->assertJsonValidationErrors('currency');
    $this->postJson('/api/transactions', [...$base, 'amount' => '0'])
        ->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'occurred_on' => today()->addDays(2)->toDateString()])
        ->assertJsonValidationErrors('occurred_on');
});

it('requires values for required dimensions and stores them', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch', 'is_required' => true]);
    $value = DimensionValue::factory()->create(['dimension_id' => $branch->id]);
    $base = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', $base)
        ->assertJsonValidationErrors('dimension_values');

    $this->postJson('/api/transactions', [...$base, 'dimension_values' => [$branch->id => $value->id]])
        ->assertCreated();

    expect(Transaction::sole()->dimensionValues->pluck('id')->all())->toBe([$value->id]);
});

it('rejects a dimension value that belongs to another dimension', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $foreignValue = DimensionValue::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'dimension_values' => [$branch->id => $foreignValue->id],
    ])->assertJsonValidationErrors('dimension_values');
});

it('snapshots the department and keeps it when the author transfers later', function () {
    $sales = Department::factory()->create();
    $warehouse = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $sales->id]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertCreated();

    $user->update(['department_id' => $warehouse->id]);

    expect(Transaction::sole()->department_id)->toBe($sales->id);
});

it('returns the same record for a repeated idempotency key', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $key = (string) Str::uuid();
    $payload = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', $payload)->assertCreated();
    $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', $payload)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Transaction::count())->toBe(1);
});

it('writes nothing when the idempotency key repeats', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $key = (string) Str::uuid();
    $payload = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', $payload)->assertCreated();

    $statements = [];
    DB::beforeExecuting(function (string $sql) use (&$statements): void {
        $statements[] = $sql;
    });

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', $payload)->assertOk();

    expect(array_filter($statements, fn (string $sql) => str_contains($sql, 'select')))->not->toBeEmpty()
        ->and(array_filter($statements, fn (string $sql) => str_contains($sql, 'insert into')))->toBe([]);
});

it('returns the winning record when a concurrent request claimed the key first', function () {
    $caller = User::factory()->create();
    Sanctum::actingAs($caller);
    $category = Category::factory()->create();
    $key = (string) Str::uuid();
    $winnerId = null;

    $racer = new class(app(RecordRevision::class)) extends CreateTransaction
    {
        /** @var callable */
        public $concurrentWrite;

        public function handle(TransactionInput $input, User $actor): Transaction
        {
            ($this->concurrentWrite)();

            return parent::handle($input, $actor);
        }
    };

    $racer->concurrentWrite = function () use ($caller, $category, $key, &$winnerId): void {
        $winnerId = DB::table('transactions')->insertGetId([
            'user_id' => $caller->id,
            'department_id' => null,
            'type' => 'expense',
            'amount_minor' => 1000,
            'currency' => 'UZS',
            'occurred_on' => today()->toDateString(),
            'category_id' => $category->id,
            'created_by' => $caller->id,
            'idempotency_key' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };

    app()->instance(CreateTransaction::class, $racer);

    $response = $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertOk();

    expect($response->json('data.id'))->toBe($winnerId)
        ->and(Transaction::count())->toBe(1)
        ->and(Transaction::sole()->revisions()->count())->toBe(0);
});

it('rolls the whole write back when recording the revision fails', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create();
    $value = DimensionValue::factory()->create(['dimension_id' => $branch->id]);

    app()->instance(RecordRevision::class, new class extends RecordRevision
    {
        public function handle(Transaction $transaction, RevisionAction $action, User $actor): void
        {
            throw new RuntimeException('revision failed');
        }
    });

    $input = new TransactionInput(
        userId: $user->id,
        departmentId: $user->department_id,
        type: TransactionType::Expense,
        amount: '1000',
        currency: 'UZS',
        occurredOn: CarbonImmutable::parse('2026-07-20'),
        categoryId: $category->id,
        dimensionValues: [$branch->id => $value->id],
    );

    expect(fn () => app(CreateTransaction::class)->handle($input, $user))
        ->toThrow(RuntimeException::class);

    expect(Transaction::withTrashed()->count())->toBe(0)
        ->and(DB::table('transaction_dimension_values')->count())->toBe(0);
});

it('rejects an amount too wide to survive the conversion to minor units', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $base = [
        'type' => 'expense',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', [...$base, 'amount' => '99999999999999999999'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'amount' => '9223372036854775808'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'amount' => '18446744073709555712'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'amount' => '1000000000000000000'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'amount' => '99999999999999.99', 'currency' => 'USD'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');

    expect(Transaction::count())->toBe(0);
});

it('rejects an amount that rounds away to nothing', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $base = [
        'type' => 'expense',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', [...$base, 'amount' => '0.4', 'currency' => 'UZS'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
    $this->postJson('/api/transactions', [...$base, 'amount' => '0.001', 'currency' => 'USD'])
        ->assertStatus(422)->assertJsonValidationErrors('amount');

    expect(Transaction::count())->toBe(0);
});

it('rounds an amount carrying more decimals than the currency', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '12.345',
        'currency' => 'USD',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertCreated()->assertJsonPath('data.amount_minor', 1235);

    expect(Transaction::sole()->amount_minor)->toBe(1235);
});

it('guards the amount and the currency inside the action itself', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create();

    $input = fn (string $amount, string $currency) => new TransactionInput(
        userId: $user->id,
        departmentId: $user->department_id,
        type: TransactionType::Expense,
        amount: $amount,
        currency: $currency,
        occurredOn: CarbonImmutable::parse('2026-07-20'),
        categoryId: $category->id,
    );

    $action = app(CreateTransaction::class);

    expect(fn () => $action->handle($input('0.4', 'UZS'), $user))
        ->toThrow(InvalidArgumentException::class, 'is not a positive amount in minor units')
        ->and(fn () => $action->handle($input('18446744073709555712', 'UZS'), $user))
        ->toThrow(InvalidArgumentException::class, 'is not a decimal of at most 15 digits')
        ->and(fn () => $action->handle($input('99999999999999.99', 'USD'), $user))
        ->toThrow(InvalidArgumentException::class, 'exceeds the maximum')
        ->and(fn () => $action->handle($input('1000', 'XXX'), $user))
        ->toThrow(InvalidArgumentException::class, 'is not supported')
        ->and(Transaction::count())->toBe(0);
});

it('snapshots the department of the owner rather than the actor', function () {
    $sales = Department::factory()->create();
    $warehouse = Department::factory()->create();
    $owner = User::factory()->create(['department_id' => $sales->id]);
    $actor = User::factory()->create(['department_id' => $warehouse->id]);
    $category = Category::factory()->create();

    $transaction = app(CreateTransaction::class)->handle(new TransactionInput(
        userId: $owner->id,
        departmentId: $owner->department_id,
        type: TransactionType::Expense,
        amount: '1000',
        currency: 'UZS',
        occurredOn: CarbonImmutable::parse('2026-07-20'),
        categoryId: $category->id,
    ), $actor);

    expect($transaction->department_id)->toBe($sales->id)
        ->and($transaction->user_id)->toBe($owner->id)
        ->and($transaction->created_by)->toBe($actor->id)
        ->and($transaction->revisions()->sole()->snapshot['department_id'])->toBe($sales->id);
});

it('rethrows a unique violation that no idempotency key can explain', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();

    $failing = new class(app(RecordRevision::class)) extends CreateTransaction
    {
        public function handle(TransactionInput $input, User $actor): Transaction
        {
            throw new UniqueConstraintViolationException('pgsql', 'insert into "transactions"', [], new Exception('duplicate key'));
        }
    };

    app()->instance(CreateTransaction::class, $failing);
    $this->withoutExceptionHandling();

    expect(fn () => $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('converts a minor unit currency through the money helper', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '12.34',
        'currency' => 'USD',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertCreated()
        ->assertJsonPath('data.amount_minor', 1234)
        ->assertJsonPath('data.amount', '12.34')
        ->assertJsonPath('data.currency', 'USD');

    expect(Transaction::sole()->amount_minor)->toBe(1234);
});

it('returns the documented resource shape', function () {
    $department = Department::factory()->create(['name' => 'Sotuv']);
    $user = User::factory()->create(['department_id' => $department->id, 'name' => 'Alisher']);
    Sanctum::actingAs($user);
    $category = Category::factory()->create(['name' => 'Transport']);
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $value = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'name' => 'Chilonzor']);

    $response = $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => '2026-07-20',
        'category_id' => $category->id,
        'note' => 'taksi',
        'dimension_values' => [$branch->id => $value->id],
    ])->assertCreated();

    $transaction = Transaction::sole();

    expect($response->json('data'))->toMatchArray([
        'id' => $transaction->id,
        'type' => 'expense',
        'amount_minor' => 1000,
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => '2026-07-20',
        'note' => 'taksi',
        'category' => ['id' => $category->id, 'name' => 'Transport'],
        'user' => ['id' => $user->id, 'name' => 'Alisher'],
        'department' => ['id' => $department->id, 'name' => 'Sotuv'],
        'dimension_values' => [[
            'dimension_id' => $branch->id,
            'dimension_key' => 'branch',
            'value_id' => $value->id,
            'value_name' => 'Chilonzor',
        ]],
    ])->and($response->json('data.created_at'))->not->toBeNull()
        ->and($response->json('data.updated_at'))->not->toBeNull();
});

it('records a created revision holding the full snapshot', function () {
    $department = Department::factory()->create();
    $user = User::factory()->create(['department_id' => $department->id]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $value = DimensionValue::factory()->create(['dimension_id' => $branch->id]);

    $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => '2026-07-20',
        'category_id' => $category->id,
        'note' => 'taksi',
        'dimension_values' => [$branch->id => $value->id],
    ])->assertCreated();

    $revision = Transaction::sole()->revisions()->sole();

    expect($revision->action)->toBe(RevisionAction::Created)
        ->and($revision->actor_id)->toBe($user->id)
        ->and($revision->created_at)->not->toBeNull()
        ->and($revision->snapshot)->toEqual([
            'type' => 'expense',
            'amount_minor' => 1000,
            'currency' => 'UZS',
            'occurred_on' => '2026-07-20',
            'category_id' => $category->id,
            'note' => 'taksi',
            'user_id' => $user->id,
            'department_id' => $department->id,
            'dimension_values' => [$branch->id => $value->id],
            'deleted_at' => null,
            'is_market_purchase' => false,
            'receipt_id' => null,
            'quantity_kg' => null,
            'quantity' => null,
            'quantity_unit' => null,
        ]);
});

it('rejects an inactive category and an inactive dimension value', function () {
    Sanctum::actingAs(User::factory()->create());
    $inactiveCategory = Category::factory()->create(['is_active' => false]);
    $category = Category::factory()->create();
    $branch = Dimension::factory()->create(['key' => 'branch']);
    $inactiveValue = DimensionValue::factory()->create(['dimension_id' => $branch->id, 'is_active' => false]);
    $base = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ];

    $this->postJson('/api/transactions', [...$base, 'category_id' => $inactiveCategory->id])
        ->assertJsonValidationErrors('category_id');

    $this->postJson('/api/transactions', [...$base, 'dimension_values' => [$branch->id => $inactiveValue->id]])
        ->assertJsonValidationErrors('dimension_values');

    expect(Transaction::count())->toBe(0);
});

it('does not hand another caller record back for a shared idempotency key', function () {
    $owner = User::factory()->create();
    $category = Category::factory()->create();
    $key = (string) Str::uuid();

    $existing = Transaction::factory()->create([
        'user_id' => $owner->id,
        'created_by' => $owner->id,
        'category_id' => $category->id,
        'idempotency_key' => $key,
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertStatus(422)->assertJsonValidationErrors('idempotency_key');

    expect(Transaction::count())->toBe(1)
        ->and(Transaction::sole()->id)->toBe($existing->id);
});

it('rejects a malformed idempotency key instead of failing on the database', function () {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();

    $this->withHeader('Idempotency-Key', 'not-a-uuid')->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
    ])->assertStatus(422)->assertJsonValidationErrors('idempotency_key');

    expect(Transaction::count())->toBe(0);
});

it('refuses guests and deactivated callers', function () {
    $payload = [
        'type' => 'expense',
        'amount' => '1000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => Category::factory()->create()->id,
    ];

    $this->postJson('/api/transactions', $payload)->assertUnauthorized();

    Sanctum::actingAs(User::factory()->create(['status' => UserStatus::Blocked]));

    $this->postJson('/api/transactions', $payload)->assertForbidden();

    expect(Transaction::count())->toBe(0);
});
