<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function createAdmin(): User
    {
        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => 'TestPassword123!',
        ]);

        $admin->is_admin = true;
        $admin->save();

        return $admin;
    }

    public function test_admin_can_login(): void
    {
        $this->createAdmin();

        $this->postJson('/api/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'TestPassword123!',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonStructure(['token', 'token_type', 'user']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->createAdmin();

        $this->postJson('/api/admin/login', [
            'email' => 'admin@test.com',
            'password' => 'WrongPassword!',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_non_admin_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'customer@test.com',
            'password' => 'TestPassword123!',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => 'customer@test.com',
            'password' => 'TestPassword123!',
        ])->assertUnauthorized();
    }

    public function test_admin_can_access_protected_profile(): void
    {
        $admin = $this->createAdmin();

        Sanctum::actingAs($admin, ['admin']);

        $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('email', 'admin@test.com');
    }

    
    public function test_logout_revokes_access_token(): void
    {
        $admin = $this->createAdmin();
        $token = $admin->createToken('admin-api', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/admin/logout')
            ->assertOk();

        // Confirm the token has been permanently removed.
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

}
