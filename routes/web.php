<?php

use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

Route::get('/admin/receipts/{receipt}/image', [ReceiptController::class, 'image'])->middleware('auth')->whereNumber('receipt');

Route::get('/', function () {
    return view('welcome');
});

// Public by URL, with only the shared secret keeping it honest, so it carries a ceiling
// too. It sits far above what a few dozen staff can generate by tapping buttons: a
// rejected update is one Telegram will redeliver, and rationing real traffic to stop a
// flood would be the worse trade.
Route::post('telegram/webhook', TelegramWebhookController::class)
    ->middleware([VerifyTelegramWebhook::class, 'throttle:telegram-webhook'])
    ->name('telegram.webhook');
