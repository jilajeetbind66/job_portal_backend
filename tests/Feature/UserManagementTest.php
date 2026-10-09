<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole('super_admin');

        return $user;
    }

    private function createPartnerAdmin(Partner $partner): User
    {
        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $user->assignRole('partner_admin');

        return $user;
    }

    private function createPartner(string $name, string $slug): Partner
    {
        return Partner::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'max_active_jobs' => 10,
        ]);
    }

    public function test_super_admin_can_list_users(): void
    {
        $admin = $this->createSuperAdmin();

        User::factory()->count(3)->create();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/admin/users');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    public function test_super_admin_can_create_candidate(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'New Candidate',
                'email' => 'candidate@example.com',
                'phone' => '9876543210',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'role' => 'candidate',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'New Candidate')
            ->assertJsonPath('data.role', 'candidate')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('users', [
            'email' => 'candidate@example.com',
            'partner_id' => null,
        ]);

        $user = User::where('email', 'candidate@example.com')->first();

        $this->assertTrue($user->hasRole('candidate'));
    }

    public function test_super_admin_can_create_partner_user(): void
    {
        $admin = $this->createSuperAdmin();

        $partner = $this->createPartner(
            'Acme Technologies',
            'acme-technologies'
        );

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Acme Recruiter',
                'email' => 'recruiter@example.com',
                'phone' => '9876543211',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'partner_id' => $partner->id,
                'role' => 'partner_recruiter',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.partner_id', $partner->id)
            ->assertJsonPath('data.role', 'partner_recruiter');

        $this->assertDatabaseHas('users', [
            'email' => 'recruiter@example.com',
            'partner_id' => $partner->id,
        ]);
    }

    public function test_partner_admin_can_create_user_only_for_own_partner(): void
    {
        $partnerA = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerB = $this->createPartner(
            'Partner B',
            'partner-b'
        );

        $partnerAdmin = $this->createPartnerAdmin($partnerA);

        $response = $this->actingAs($partnerAdmin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Partner A Recruiter',
                'email' => 'partner-a-recruiter@example.com',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'partner_id' => $partnerB->id,
                'role' => 'partner_recruiter',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.partner_id', $partnerA->id);

        $this->assertDatabaseHas('users', [
            'email' => 'partner-a-recruiter@example.com',
            'partner_id' => $partnerA->id,
        ]);
    }

    public function test_partner_admin_cannot_assign_partner_admin_role(): void
    {
        $partner = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerAdmin = $this->createPartnerAdmin($partner);

        $response = $this->actingAs($partnerAdmin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Another Admin',
                'email' => 'another-admin@example.com',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'role' => 'partner_admin',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'another-admin@example.com',
        ]);
    }

    public function test_partner_admin_cannot_create_super_admin(): void
    {
        $partner = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerAdmin = $this->createPartnerAdmin($partner);

        $response = $this->actingAs($partnerAdmin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Super Admin',
                'email' => 'super-admin@example.com',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'role' => 'super_admin',
            ]);

        $response->assertUnprocessable();
    }

    public function test_super_admin_can_view_user(): void
    {
        $admin = $this->createSuperAdmin();

        $user = User::factory()->create();

        $user->assignRole('candidate');

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/admin/users/{$user->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'candidate');
    }

    public function test_partner_admin_can_view_user_from_same_partner(): void
    {
        $partner = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerAdmin = $this->createPartnerAdmin($partner);

        $targetUser = User::factory()->create([
            'partner_id' => $partner->id,
        ]);

        $targetUser->assignRole('partner_recruiter');

        $response = $this->actingAs($partnerAdmin)
            ->getJson("/api/v1/admin/users/{$targetUser->id}");

        $response->assertOk();
    }

    public function test_partner_admin_cannot_view_user_from_another_partner(): void
    {
        $partnerA = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerB = $this->createPartner(
            'Partner B',
            'partner-b'
        );

        $partnerAdmin = $this->createPartnerAdmin($partnerA);

        $targetUser = User::factory()->create([
            'partner_id' => $partnerB->id,
        ]);

        $targetUser->assignRole('partner_recruiter');

        $response = $this->actingAs($partnerAdmin)
            ->getJson("/api/v1/admin/users/{$targetUser->id}");

        $response->assertForbidden();
    }

    public function test_super_admin_can_update_user(): void
    {
        $admin = $this->createSuperAdmin();

        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $user->assignRole('candidate');

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone' => '9999999999',
                'role' => 'candidate',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_partner_admin_can_update_user_from_same_partner(): void
    {
        $partner = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerAdmin = $this->createPartnerAdmin($partner);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'name' => 'Old Recruiter',
        ]);

        $user->assignRole('partner_recruiter');

        $response = $this->actingAs($partnerAdmin)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'name' => 'Updated Recruiter',
                'role' => 'partner_recruiter',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Recruiter');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Recruiter',
            'partner_id' => $partner->id,
        ]);
    }

    public function test_partner_admin_cannot_update_user_from_another_partner(): void
    {
        $partnerA = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerB = $this->createPartner(
            'Partner B',
            'partner-b'
        );

        $partnerAdmin = $this->createPartnerAdmin($partnerA);

        $user = User::factory()->create([
            'partner_id' => $partnerB->id,
        ]);

        $user->assignRole('partner_recruiter');

        $response = $this->actingAs($partnerAdmin)
            ->putJson("/api/v1/admin/users/{$user->id}", [
                'name' => 'Hacked Name',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'partner_id' => $partnerB->id,
        ]);
    }

    public function test_super_admin_can_suspend_user(): void
    {
        $admin = $this->createSuperAdmin();

        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$user->id}/status", [
                'status' => 'suspended',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'suspended',
        ]);
    }

    public function test_partner_admin_can_suspend_user_from_same_partner(): void
    {
        $partner = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerAdmin = $this->createPartnerAdmin($partner);

        $user = User::factory()->create([
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $user->assignRole('partner_recruiter');

        $response = $this->actingAs($partnerAdmin)
            ->patchJson("/api/v1/admin/users/{$user->id}/status", [
                'status' => 'suspended',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
    }

    public function test_partner_admin_cannot_suspend_user_from_another_partner(): void
    {
        $partnerA = $this->createPartner(
            'Partner A',
            'partner-a'
        );

        $partnerB = $this->createPartner(
            'Partner B',
            'partner-b'
        );

        $partnerAdmin = $this->createPartnerAdmin($partnerA);

        $user = User::factory()->create([
            'partner_id' => $partnerB->id,
            'status' => 'active',
        ]);

        $user->assignRole('partner_recruiter');

        $response = $this->actingAs($partnerAdmin)
            ->patchJson("/api/v1/admin/users/{$user->id}/status", [
                'status' => 'suspended',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_suspend_own_account(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->patchJson("/api/v1/admin/users/{$admin->id}/status", [
                'status' => 'suspended',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJson([
                'message' => 'You cannot suspend your own account.',
            ]);
    }

    public function test_guest_cannot_access_user_management(): void
    {
        $response = $this->getJson('/api/v1/admin/users');

        $response->assertUnauthorized();
    }

    public function test_candidate_cannot_access_user_management(): void
    {
        $candidate = User::factory()->create([
            'status' => 'active',
        ]);

        $candidate->assignRole('candidate');

        $response = $this->actingAs($candidate)
            ->getJson('/api/v1/admin/users');

        $response->assertForbidden();
    }

    public function test_create_user_requires_valid_data(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => '',
                'email' => 'wrong-email',
                'password' => '123',
                'password_confirmation' => '456',
                'role' => 'invalid-role',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'role',
            ]);
    }

    public function test_created_user_password_is_hashed(): void
    {
        $admin = $this->createSuperAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/admin/users', [
                'name' => 'Secure User',
                'email' => 'secure@example.com',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'role' => 'candidate',
            ]);

        $response->assertCreated();

        $user = User::where('email', 'secure@example.com')->firstOrFail();

        $this->assertNotSame('Password@123', $user->password);
        $this->assertTrue(
            Hash::check('Password@123', $user->password)
        );
    }
}