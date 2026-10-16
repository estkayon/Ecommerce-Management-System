
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->restrictOnDelete();

            $table->string('provider', 30)->default('carrybee');
            $table->string('status', 30)->default('pending');

            // Store provider identifiers after successful delivery creation.
            $table->string('consignment_id')->nullable();
            $table->string('tracking_number')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();

            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique(['provider', 'consignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
