<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        Sanctum::actingAs($admin, ['admin']);

        $this->product = Product::create([
            'name' => 'Gaming Keyboard',
            'sku' => 'KEYBOARD-001',
            'price' => '2500.00',
            'is_active' => true,
        ]);

        $this->product->inventory()->create(['quantity' => 0]);
    }

    private function adjustStock(int $quantity, string $reason = 'Test adjustment')
    {
        return $this->postJson(
            "/api/admin/products/{$this->product->id}/inventory/adjust",
            [
                'quantity_change' => $quantity,
                'reason' => $reason,
            ]
        );
    }

    public function test_admin_can_add_stock(): void
    {
        $this->adjustStock(50)
            ->assertOk()
            ->assertJsonPath('data.quantity', 50);

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 50,
        ]);
    }

    public function test_admin_can_remove_stock(): void
    {
        $this->adjustStock(50)->assertOk();

        $this->adjustStock(-15)
            ->assertOk()
            ->assertJsonPath('data.quantity', 35);
    }

    public function test_insufficient_stock_is_rejected(): void
    {
        $this->adjustStock(10)->assertOk();

        $this->adjustStock(-20)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Insufficient stock.');

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_inventory_movements_are_recorded(): void
    {
        $this->adjustStock(50)->assertOk();
        $this->adjustStock(-15)->assertOk();

        $this->assertDatabaseCount('inventory_movements', 2);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $this->product->id,
            'quantity_before' => 50,
            'quantity_change' => -15,
            'quantity_after' => 35,
        ]);
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $this->adjustStock(0)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity_change');

        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_non_admin_cannot_adjust_stock(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->adjustStock(10)->assertForbidden();

        $this->assertDatabaseHas('inventories', [
            'product_id' => $this->product->id,
            'quantity' => 0,
        ]);
    }
}
