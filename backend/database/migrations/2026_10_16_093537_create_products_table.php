
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the products table.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {

            // Primary key for uniquely identifying each product.
            $table->id();

            // Basic product information.
            $table->string('name', 255);
            $table->string('sku', 100)->unique();
            $table->text('description')->nullable();

            // Store monetary values as decimals to avoid rounding errors.
            $table->decimal('price', 12, 2);

            // Control whether a product is available in the storefront.
            $table->boolean('is_active')->default(true);

            // Track product creation and modification times.
            $table->timestamps();

            // Support soft deletion to preserve historical order references.
            $table->softDeletes();

            // Improve filtering and sorting for the admin/storefront.
            $table->index(['is_active', 'created_at']);
        });
    }

    /**
     * Remove the products table when rolling back this migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
