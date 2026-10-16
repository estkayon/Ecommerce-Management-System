
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestampTz('expires_at')->nullable();

            // Speed up scheduled searches for expired pending orders.
            $table->index(
                ['status', 'payment_status', 'expires_at'],
                'orders_expiry_lookup_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_expiry_lookup_index');
            $table->dropColumn('expires_at');
        });
    }
};
