<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Department;
use App\Models\Dimension;
use App\Models\DimensionValue;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * A real CSV parser rather than an `explode("\n")`, so a field carrying a comma, a quote
 * or an embedded newline is read back as one cell instead of being torn across rows.
 *
 * @return array<int, array<int, string|null>>
 */
function parseCsv(string $csv): array
{
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $csv);
    rewind($handle);

    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }

    fclose($handle);

    return $rows;
}

it('exports the scoped and filtered transactions as csv', function () {
    $staff = User::factory()->create(['name' => 'Alisher']);
    $category = Category::factory()->create(['name' => 'Taksi']);
    Transaction::factory()->for($staff)->create([
        'category_id' => $category->id, 'amount_minor' => 120000, 'currency' => 'UZS', 'occurred_on' => today(),
    ]);
    Transaction::factory()->create(['occurred_on' => today()]);
    Sanctum::actingAs($staff);

    $response = $this->get('/api/exports/transactions')->assertOk();

    expect($response->headers->get('content-type'))->toContain('text/csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('date,type,amount,currency,category,staff,department,note')
        ->and($csv)->toContain('Taksi')
        ->and(substr_count(trim($csv), "\n"))->toBe(1);
});

it('exports everything for an owner', function () {
    Transaction::factory()->count(3)->create(['occurred_on' => today()]);
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $csv = $this->get('/api/exports/transactions')->assertOk()->streamedContent();

    expect(substr_count(trim($csv), "\n"))->toBe(3);
});

it('renders amounts with the currency of each row rather than a default one', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    Transaction::factory()->create(['amount_minor' => 5000, 'currency' => 'UZS', 'occurred_on' => today()]);
    Transaction::factory()->create(['amount_minor' => 150050, 'currency' => 'USD', 'occurred_on' => today()]);

    $rows = collect(parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent()))
        ->skip(1)
        ->keyBy(fn (array $row) => $row[3]);

    expect($rows['UZS'][2])->toBe('5000')
        ->and($rows['USD'][2])->toBe('1500.50');
});

it('neutralises a formula in a note or a category name', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $formulaCategory = Category::factory()->create(['name' => '=SUM(A1:A9)']);
    Transaction::factory()->create([
        'category_id' => $formulaCategory->id,
        'note' => '+cmd|/c calc',
        'occurred_on' => today(),
    ]);

    $rows = parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent());

    expect($rows[1][4])->toBe("'=SUM(A1:A9)")
        ->and($rows[1][7])->toBe("'+cmd|/c calc");
});

it('neutralises a note that starts with a tab or a carriage return', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    Transaction::factory()->create(['note' => "\tcmd /c calc", 'occurred_on' => today()]);
    Transaction::factory()->create(['note' => "\rcmd /c calc", 'occurred_on' => today()]);

    $notes = collect(parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent()))
        ->skip(1)
        ->pluck(7);

    expect($notes->all())->toEqualCanonicalizing(["'\tcmd /c calc", "'\rcmd /c calc"]);
});

it('keeps a note of exactly "0" instead of treating it as blank', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    Transaction::factory()->create(['note' => '0', 'occurred_on' => today()]);

    $rows = parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent());

    expect($rows[1][7])->toBe('0');
});

it('leaves a cell that does not start with a formula prefix untouched', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $category = Category::factory()->create(['name' => 'Ofis - markaz']);
    Transaction::factory()->create(['category_id' => $category->id, 'occurred_on' => today()]);

    $rows = parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent());

    expect($rows[1][4])->toBe('Ofis - markaz');
});

it('round trips a note holding a comma a quote and an embedded newline', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $note = "Taxi, \"receipt\" attached\nsecond line";
    Transaction::factory()->create(['note' => $note, 'occurred_on' => today()]);

    $rows = parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent());

    expect($rows[1][7])->toBe($note)
        ->and($rows)->toHaveCount(2);
});

it('adds one column per active dimension in sort then name order and blanks a missing value', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $project = Dimension::factory()->create(['key' => 'project', 'name' => 'Project', 'sort' => 1, 'is_active' => true]);
    $branch = Dimension::factory()->create(['key' => 'branch', 'name' => 'Branch', 'sort' => 2, 'is_active' => true]);
    Dimension::factory()->create(['key' => 'archived', 'is_active' => false]);

    $branchValue = DimensionValue::factory()->for($branch)->create(['name' => 'Tashkent']);

    $withBranch = Transaction::factory()->create(['occurred_on' => today()]);
    $withBranch->syncDimensionValues([$branch->id => $branchValue->id]);

    $withoutBranch = Transaction::factory()->create(['occurred_on' => today()]);

    $rows = parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent());

    expect($rows[0])->toBe(['date', 'type', 'amount', 'currency', 'category', 'staff', 'department', 'note', 'project', 'branch', 'quantity_kg', 'quantity', 'quantity_unit', 'is_market_purchase', 'receipt_id']);

    $withBranchRow = collect($rows)->firstWhere(fn (array $row) => ($row[8] ?? null) === '' && ($row[9] ?? null) === 'Tashkent');
    $withoutBranchRow = collect($rows)->firstWhere(fn (array $row) => ($row[8] ?? null) === '' && ($row[9] ?? null) === '');

    expect($withBranchRow)->not->toBeNull()
        ->and($withoutBranchRow)->not->toBeNull();
});

it('includes the department of a transaction and blanks it when the transaction has none', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));

    $department = Department::factory()->create(['name' => 'Sales']);
    Transaction::factory()->create(['department_id' => $department->id, 'occurred_on' => today()]);
    Transaction::factory()->create(['department_id' => null, 'occurred_on' => today()]);

    $rows = collect(parseCsv($this->get('/api/exports/transactions')->assertOk()->streamedContent()))->skip(1);

    expect($rows->pluck(6)->all())->toEqualCanonicalizing(['Sales', '']);
});

it('does not issue a query per row while streaming', function () {
    Sanctum::actingAs(User::factory()->create(['role' => UserRole::Owner]));
    Transaction::factory()->count(20)->create(['occurred_on' => today()]);

    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $this->get('/api/exports/transactions')->assertOk()->streamedContent();

    expect($queries)->toBeLessThan(10);
});
