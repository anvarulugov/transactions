<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('round trips quantities with their selected unit and exports them', function (string $unit) {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create();
    $response = $this->postJson('/api/transactions', [
        'type' => 'expense', 'amount' => '120000', 'currency' => 'UZS',
        'occurred_on' => today()->toDateString(), 'category_id' => $category->id,
        'quantity' => '2.5', 'quantity_unit' => $unit,
    ])->assertCreated()->assertJsonPath('data.quantity', '2.500')
        ->assertJsonPath('data.quantity_unit', $unit)
        ->assertJsonPath('data.quantity_kg', $unit === 'kg' ? '2.500' : null);
    $id = $response->json('data.id');
    $this->getJson('/api/transactions')->assertOk()->assertJsonPath('data.0.quantity_unit', $unit);
    $this->patchJson("/api/transactions/{$id}", ['note' => 'Updated'])
        ->assertOk()->assertJsonPath('data.quantity', '2.500')->assertJsonPath('data.quantity_unit', $unit);
    $csv = $this->get('/api/exports/transactions')->assertOk()->streamedContent();
    expect($csv)->toContain('quantity,quantity_unit')->toContain('2.500,'.$unit);
    $this->patchJson("/api/transactions/{$id}", ['quantity' => '10', 'quantity_unit' => 'dona'])
        ->assertOk()->assertJsonPath('data.quantity', '10.000')->assertJsonPath('data.quantity_unit', 'dona')
        ->assertJsonPath('data.quantity_kg', null)->assertJsonPath('data.amount_minor', 120000);
    $snapshot = Transaction::findOrFail($id)->revisions()->latest('id')->first()->snapshot;
    expect($snapshot['quantity'])->toBe('10.000')->and($snapshot['quantity_unit'])->toBe('dona');
    $this->patchJson("/api/transactions/{$id}", ['quantity' => null, 'quantity_unit' => null])
        ->assertOk()->assertJsonPath('data.quantity', null)->assertJsonPath('data.quantity_unit', null)
        ->assertJsonPath('data.quantity_kg', null)->assertJsonPath('data.amount_minor', 120000);
})->with(['kg', 'litr', 'dona']);

it('rejects missing invalid and conflicting units without writing', function (array $fields) {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $this->postJson('/api/transactions', [
        'type' => 'expense', 'amount' => '1000', 'currency' => 'UZS',
        'occurred_on' => today()->toDateString(), 'category_id' => $category->id,
        ...$fields,
    ])->assertUnprocessable();
    expect(Transaction::count())->toBe(0);
})->with([
    [['quantity' => '2']],
    [['quantity_unit' => 'litr']],
    [['quantity' => '2', 'quantity_unit' => 'tonna']],
    [['quantity' => '0', 'quantity_unit' => 'dona']],
    [['quantity' => '2', 'quantity_unit' => 'litr', 'quantity_kg' => '2']],
]);

it('keeps kilogram clients compatible and switches units without stale kilograms', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    $this->patchJson("/api/transactions/{$transaction->id}", ['quantity_kg' => '3'])
        ->assertOk()->assertJsonPath('data.quantity', '3.000')->assertJsonPath('data.quantity_unit', 'kg');
    $this->patchJson("/api/transactions/{$transaction->id}", ['quantity' => '3', 'quantity_unit' => 'litr'])
        ->assertOk()->assertJsonPath('data.quantity_unit', 'litr')->assertJsonPath('data.quantity_kg', null);
    $this->patchJson("/api/transactions/{$transaction->id}", ['quantity_kg' => '1'])
        ->assertOk()->assertJsonPath('data.quantity', '1.000')->assertJsonPath('data.quantity_unit', 'kg');
});
