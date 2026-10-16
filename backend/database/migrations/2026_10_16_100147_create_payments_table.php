
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            // Payment gateway and transaction identifiers.
            $table->string('gateway', 30);
            $table->string('transaction_id')->nullable();
            $table->string('gateway_payment_id')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');

            $table->string('status', 30)->default('pending');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('paid_at')->nullable();

            // Store non-sensitive gateway response metadata.
            $table->jsonb('metadata')->nullable();
            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->unique(['gateway', 'transaction_id']);
            $table->unique(['gateway', 'gateway_payment_id']);
            $table->index(['order_id', 'status']);
        });

        DB::statement('
            ALTER TABLE payments
            ADD CONSTRAINT payments_amount_check
            CHECK (amount >= 0)
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
