<?php

use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\TelegramAuthController;
use App\Http\Controllers\Api\TransactionExportController;
use App\Http\Controllers\Api\TransactionsController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('health', HealthController::class)->name('health');

Route::post('auth/telegram', [TelegramAuthController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('auth.telegram');

// A ceiling on the authenticated surface, since a signed-in caller is one person with a
// phone and no legitimate reason to outrun it. The limiter keys on the authenticated user,
// so a whole office behind one address is not rationed collectively.
Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
    Route::get('me', fn (Request $request) => UserResource::make(
        $request->user()->loadMissing(['department', 'managedDepartments'])
    ))->name('me');

    Route::post('receipts', [ReceiptController::class, 'store'])->middleware('throttle:5,1');
    Route::get('receipts/{receipt}/image', [ReceiptController::class, 'image'])->whereNumber('receipt');
    Route::post('receipts/{receipt}/confirm', [ReceiptController::class, 'confirm'])->whereNumber('receipt');

    Route::get('bootstrap', BootstrapController::class)->name('bootstrap');

    Route::get('transactions', [TransactionsController::class, 'index'])->name('transactions.index');
    Route::post('transactions', [TransactionsController::class, 'store'])->name('transactions.store');

    Route::patch('transactions/{transaction}', [TransactionsController::class, 'update'])
        ->whereNumber('transaction')
        ->name('transactions.update');

    Route::delete('transactions/{transaction}', [TransactionsController::class, 'destroy'])
        ->whereNumber('transaction')
        ->name('transactions.destroy');

    Route::get('transactions/{transaction}/revisions', [TransactionsController::class, 'revisions'])
        ->whereNumber('transaction')
        ->name('transactions.revisions');

    Route::get('reports/summary', [ReportsController::class, 'summary'])->name('reports.summary');
    Route::get('reports/trend', [ReportsController::class, 'trend'])->name('reports.trend');

    // An export streams a whole scoped history in one request on a single worker server,
    // which costs far more than any other route here, so it carries a tighter ceiling of
    // its own on top of the group's.
    Route::get('exports/transactions', TransactionExportController::class)
        ->middleware('throttle:exports')
        ->name('exports.transactions');
});
