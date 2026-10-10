<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->decimal('quantity_kg', 12, 3)->nullable();
        });

        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_quantity_kg_positive CHECK (quantity_kg IS NULL OR quantity_kg > 0)');
    }
};
