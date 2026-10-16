<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Positive values add stock; negative values reduce stock.
            $table->integer('quantity_change');

            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');

            $table->string('type', 30);
            $table->string('reason', 255)->nullable();

            // Optional reference to an order or another business operation.
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->timestampTz('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Ensure recorded stock changes are mathematically consistent.
        DB::statement(
            'ALTER TABLE inventory_movements
             ADD CONSTRAINT inventory_movements_quantity_check
             CHECK (quantity_after::bigint = quantity_before::bigint + quantity_change::bigint)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
