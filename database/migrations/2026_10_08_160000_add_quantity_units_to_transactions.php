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
            $table->decimal('quantity', 12, 3)->nullable();
            $table->string('quantity_unit', 4)->nullable();
        });
        DB::statement("UPDATE transactions SET quantity = quantity_kg, quantity_unit = 'kg' WHERE quantity_kg IS NOT NULL");
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_quantity_unit_valid CHECK ((quantity IS NULL AND quantity_unit IS NULL) OR (quantity IS NOT NULL AND quantity > 0 AND quantity_unit IS NOT NULL AND quantity_unit IN ('kg', 'litr', 'dona')))");
    }
};
