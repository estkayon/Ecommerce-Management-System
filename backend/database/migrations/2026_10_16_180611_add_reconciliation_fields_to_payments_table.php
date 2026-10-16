
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestampTz('last_checked_at')->nullable();
            $table->timestampTz('reconciliation_required_at')->nullable();

            $table->index(
                ['status', 'reconciliation_required_at'],
                'payments_reconciliation_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_reconciliation_index');
            $table->dropColumn([
                'last_checked_at',
                'reconciliation_required_at',
            ]);
        });
    }
};
