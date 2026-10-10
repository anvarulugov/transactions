<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('image_path');
            $table->string('image_hash', 64);
            $table->text('ocr_text');
            $table->jsonb('draft');
            $table->jsonb('confirmed_items')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'image_hash']);
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->boolean('is_market_purchase')->default(false);
            $table->foreignId('receipt_id')->nullable()->constrained()->restrictOnDelete();
        });
    }
};
