
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the inventories table.
     */
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            // Unique identifier for each inventory record.
            $table->id();

            // Associate each inventory record with exactly one product.
            $table->foreignId('product_id')
                ->unique()
                ->constrained('products')
                ->restrictOnDelete();

            // Track available stock; negative quantities are not allowed.
            $table->unsignedInteger('quantity')->default(0);

            // Track when the inventory record was created or updated.
            $table->timestamps();
        });

        // Enforce non-negative inventory at the PostgreSQL database level.
        DB::statement(
            'ALTER TABLE inventories ADD CONSTRAINT inventories_quantity_nonnegative CHECK (quantity >= 0)'
        );
    }

    /**
     * Remove the inventories table during migration rollback.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
