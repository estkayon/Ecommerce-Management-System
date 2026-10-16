
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // A unique identifier for each application setting.
            $table->string('key', 150)->unique();

            // Store structured values such as booleans or configuration objects.
            $table->jsonb('value');

            $table->string('group', 50)->default('general');
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
