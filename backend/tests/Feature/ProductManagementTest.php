<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        Sanctum::actingAs($admin, ['admin']);
    }

    private function createProduct(array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'name' => 'Wireless Mouse',
            'sku' => 'MOUSE-001',
            'description' => 'Wireless mouse',
            'price' => '850.00',
            'is_active' => true,
        ], $attributes));

        $product->inventory()->create(['quantity' => 0]);

        return $product;
    }

    public function test_admin_can_create_product(): void
    {
        $this->postJson('/api/admin/products', [
            'name' => 'Keyboard',
            'sku' => 'KEY-001',
            'price' => 1500,
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.sku', 'KEY-001')
            ->assertJsonPath('data.inventory.quantity', 0);

        $this->assertDatabaseHas('products', ['sku' => 'KEY-001']);
        $this->assertDatabaseCount('inventories', 1);
    }

    public function test_product_validation_rejects_invalid_data(): void
    {
        $this->postJson('/api/admin/products', [
            'name' => '',
            'sku' => '',
            'price' => -100,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sku', 'price']);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        $this->createProduct();

        $this->postJson('/api/admin/products', [
            'name' => 'Another Mouse',
            'sku' => 'MOUSE-001',
            'price' => 900,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('sku');
    }

    public function test_admin_can_update_product(): void
    {
        $product = $this->createProduct();

        $this->patchJson("/api/admin/products/{$product->id}", [
            'name' => 'Premium Mouse',
            'price' => 1200,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Premium Mouse')
            ->assertJsonPath('data.sku', 'MOUSE-001');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'price' => '1200.00',
        ]);
    }

    public function test_product_search_returns_matching_results(): void
    {
        $this->createProduct();

        $this->getJson('/api/admin/products?search=mouse')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.sku', 'MOUSE-001');
    }

    public function test_product_listing_is_paginated(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->createProduct([
                'sku' => sprintf('ITEM-%03d', $i),
            ]);
        }

        $this->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('total', 20);
    }

    public function test_admin_can_soft_delete_product(): void
    {
        $product = $this->createProduct();

        $this->deleteJson("/api/admin/products/{$product->id}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        $this->getJson("/api/admin/products/{$product->id}")
            ->assertNotFound();
    }

    public function test_non_admin_cannot_access_products(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/products')
            ->assertForbidden();
    }
}
