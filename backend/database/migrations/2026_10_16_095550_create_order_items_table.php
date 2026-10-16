
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Link each item to its order and product.
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->restrictOnDelete();

            // Preserve product details at the time of purchase.
            $table->string('product_name');
            $table->string('product_sku', 100);

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_price', 12, 2);

            $table->timestamps();

            $table->index('product_id');
        });

        // Enforce valid quantities and non-negative prices.
        DB::statement('
            ALTER TABLE order_items
            ADD CONSTRAINT order_items_values_check
            CHECK (
                quantity > 0
                AND unit_price >= 0
                AND total_price = unit_price * quantity
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
