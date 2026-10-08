<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndTenantFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_candidate_can_register_and_get_authenticated_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'John Candidate',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'john@example.com')
            ->assertJsonPath('user.role', 'candidate');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'partner_id' => null,
            'status' => 'active',
        ]);

        $this->assertAuthenticated();
    }

    public function test_active_user_can_fetch_current_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Active User',
            'email' => 'active@example.com',
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', 'active@example.com')
            ->assertJsonPath('user.status', 'active');
    }

    public function test_suspended_user_is_blocked(): void
    {
        $user = User::factory()->create([
            'name' => 'Suspended User',
            'email' => 'suspended@example.com',
            'status' => 'suspended',
        ]);

        $user->assignRole('candidate');

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account is suspended.',
            ]);
    }

    public function test_suspended_partner_user_is_blocked(): void
    {
        $partner = Partner::create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'status' => 'suspended',
        ]);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your partner account is suspended.',
            ]);
    }

    public function test_user_can_login_with_valid_credentials_and_last_login_is_updated(): void
    {
        $user = User::factory()->create([
            'name' => 'Login User',
            'email' => 'login@example.com',
            'password' => 'password123',
            'status' => 'active',
            'last_login_at' => null,
        ]);

        $user->assignRole('candidate');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Login successful.',
            ])
            ->assertJsonPath('user.email', 'login@example.com')
            ->assertJsonPath('user.role', 'candidate');

        $this->assertAuthenticated();

        $user->refresh();

        $this->assertNotNull($user->last_login_at);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong-password@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'wrong-password@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invalid email or password.',
            ]);

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'suspended-login@example.com',
            'password' => 'password123',
            'status' => 'suspended',
        ]);

        $user->assignRole('candidate');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'suspended-login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account is suspended.',
            ]);

        $this->assertGuest();
    }

    public function test_suspended_partner_user_cannot_login(): void
    {
        $partner = Partner::create([
            'name' => 'Suspended Partner',
            'slug' => 'suspended-partner',
            'status' => 'suspended',
        ]);

        $user = User::factory()->create([
            'email' => 'partner-login@example.com',
            'password' => 'password123',
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $user->assignRole('partner_recruiter');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'partner-login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your partner account is suspended.',
            ]);

        $this->assertGuest();
    }

    // public function test_authenticated_user_can_logout(): void
    // {
    //     $user = User::factory()->create([
    //         'email' => 'logout@example.com',
    //         'status' => 'active',
    //     ]);

    //     $user->assignRole('candidate');

    //     $this->actingAs($user);

    //     $this->assertAuthenticated();

    //     $response = $this->postJson('/api/v1/auth/logout');

    //     $response->assertOk()
    //         ->assertJson([
    //             'message' => 'Logout successful.',
    //         ]);

    //     $this->assertGuest();
    // }

public function test_authenticated_user_can_logout(): void
{
    $user = User::factory()->create([
        'email' => 'logout@example.com',
        'password' => 'password123',
        'status' => 'active',
    ]);

    $user->assignRole('candidate');

    $loginResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'logout@example.com',
        'password' => 'password123',
    ]);

    $loginResponse->assertOk();

    $this->assertAuthenticated();

    $logoutResponse = $this->postJson('/api/v1/auth/logout');

    $logoutResponse->assertOk()
        ->assertJson([
            'message' => 'Logout successful.',
        ]);

    $this->assertGuest('web');
}


    public function test_registration_rejects_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
            ]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email',
            ]);
    }

    public function test_login_rejects_missing_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'email',
                'password',
            ]);

        $this->assertGuest();
    }

    public function test_guest_cannot_access_current_profile(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertUnauthorized();
    }
}