<?php

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('stores edits clears and exports kilograms independently from money', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Sanctum::actingAs($user);
    $category = Category::factory()->create(['name' => 'Grechka']);
    $response = $this->postJson('/api/transactions', [
        'type' => 'expense',
        'amount' => '120000',
        'currency' => 'UZS',
        'occurred_on' => today()->toDateString(),
        'category_id' => $category->id,
        'quantity_kg' => '2.5',
    ])->assertCreated()->assertJsonPath('data.quantity_kg', '2.500');
    $id = $response->json('data.id');
    $this->getJson('/api/transactions')->assertOk()->assertJsonPath('data.0.quantity_kg', '2.500');
    $this->patchJson("/api/transactions/{$id}", ['quantity_kg' => '0.125'])
        ->assertOk()->assertJsonPath('data.quantity_kg', '0.125')->assertJsonPath('data.amount_minor', 120000);
    $transaction = Transaction::findOrFail($id);
    expect($transaction->revisions()->latest('id')->first()->snapshot['quantity_kg'])->toBe('0.125');
    $csv = $this->get('/api/exports/transactions')->assertOk()->streamedContent();
    expect($csv)->toContain('quantity_kg')->toContain('0.125');
    $this->patchJson("/api/transactions/{$id}", ['quantity_kg' => null])
        ->assertOk()->assertJsonPath('data.quantity_kg', null)->assertJsonPath('data.amount_minor', 120000);
});

it('rejects invalid kilogram quantities without writing a transaction', function (string $quantity) {
    Sanctum::actingAs(User::factory()->create());
    $category = Category::factory()->create();
    $this->postJson('/api/transactions', [
        'type' => 'expense', 'amount' => '1000', 'currency' => 'UZS',
        'occurred_on' => today()->toDateString(), 'category_id' => $category->id,
        'quantity_kg' => $quantity,
    ])->assertUnprocessable()->assertJsonValidationErrors('quantity_kg');
    expect(Transaction::count())->toBe(0);
})->with(['0', '0.000', '-1', '1.0001', '1000000000', 'abc', '2,5']);

it('preserves legacy entries and a quantity during a note-only edit', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    Sanctum::actingAs($user);
    $transaction = Transaction::factory()->create(['user_id' => $user->id]);
    $this->patchJson("/api/transactions/{$transaction->id}", ['note' => 'Legacy'])
        ->assertOk()->assertJsonPath('data.quantity_kg', null);
    $transaction->update(['quantity_kg' => '3.250']);
    $this->patchJson("/api/transactions/{$transaction->id}", ['note' => 'Updated'])
        ->assertOk()->assertJsonPath('data.quantity_kg', '3.250');
});
